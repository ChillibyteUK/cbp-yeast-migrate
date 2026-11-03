<?php
/**
 * Batch selection and writing.
 *
 * @package cbp-yeast-migrate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post types to migrate: Yeast's own list when active, else public types.
 *
 * @return string[]
 */
function cbp_yeast_migrate_post_types() {
	if ( function_exists( 'cbp_yeast_seo_post_types' ) ) {
		return array_values( cbp_yeast_seo_post_types() );
	}

	$types = get_post_types( array( 'public' => true ), 'names' );
	unset( $types['attachment'] );

	return array_values( $types );
}

/**
 * Builds the shared WHERE clause (and its parameters) selecting candidate posts.
 *
 * A post is a candidate if it has any meaningful Yoast meta, or belongs to a
 * post type Yoast defaults to noindex.
 *
 * @return array {0: string sql, 1: array params}
 */
function cbp_yeast_migrate_candidate_sql() {
	$database = $GLOBALS['wpdb'];
	$types    = cbp_yeast_migrate_post_types();
	$keys     = cbp_yeast_migrate_source_keys();
	$titles   = cbp_yeast_migrate_yoast_titles();
	$noindex  = array();

	foreach ( $types as $type ) {
		if ( ! empty( $titles[ 'noindex-' . $type ] ) ) {
			$noindex[] = $type;
		}
	}

	$params = array_merge( $types, $keys );
	$sql    = "p.post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ")
		AND p.post_status NOT IN ('auto-draft','trash')
		AND ( EXISTS (
			SELECT 1 FROM {$database->postmeta} m
			WHERE m.post_id = p.ID
			AND m.meta_key IN (" . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ")
			AND m.meta_value NOT IN ('', '0')
		)";

	if ( $noindex ) {
		$sql   .= ' OR p.post_type IN (' . implode( ',', array_fill( 0, count( $noindex ), '%s' ) ) . ')';
		$params = array_merge( $params, $noindex );
	}

	return array( $sql . ' )', $params );
}

/**
 * Counts candidate posts.
 *
 * @return int
 */
function cbp_yeast_migrate_count() {
	$database         = $GLOBALS['wpdb'];
	list( $where, $params ) = cbp_yeast_migrate_candidate_sql();

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	return (int) $database->get_var( $database->prepare( "SELECT COUNT(*) FROM {$database->posts} p WHERE $where", $params ) );
}

/**
 * Processes one batch of candidate posts after a cursor.
 *
 * @param int  $after     Last post ID handled by the previous batch.
 * @param int  $limit     Batch size.
 * @param bool $dry_run   When true, nothing is written.
 * @param bool $overwrite Replace non-empty Yeast values.
 * @return array {rows: array, last_id: int, done: bool}
 */
function cbp_yeast_migrate_batch( $after, $limit, $dry_run, $overwrite ) {
	$database         = $GLOBALS['wpdb'];
	list( $where, $params ) = cbp_yeast_migrate_candidate_sql();
	$params[]         = (int) $after;
	$params[]         = (int) $limit;

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	$ids = $database->get_col( $database->prepare( "SELECT p.ID FROM {$database->posts} p WHERE $where AND p.ID > %d ORDER BY p.ID ASC LIMIT %d", $params ) );
	$rows    = array();
	$last_id = (int) $after;
	$labels  = cbp_yeast_migrate_target_labels();

	foreach ( $ids as $id ) {
		$id      = (int) $id;
		$result  = cbp_yeast_migrate_map_post( $id, $overwrite );
		$last_id = $id;

		if ( ! $dry_run ) {
			foreach ( $result['changes'] as $meta_key => $value ) {
				update_post_meta( $id, $meta_key, wp_slash( $value ) );
			}
		}

		$rows[] = array(
			'id'      => $id,
			'title'   => get_the_title( $id ),
			'changes' => array_map(
				function ( $meta_key, $value ) use ( $labels ) {
					return array(
						'field' => $labels[ $meta_key ],
						'value' => $value,
					);
				},
				array_keys( $result['changes'] ),
				$result['changes']
			),
			'notes'   => $result['notes'],
		);
	}

	return array(
		'rows'    => $rows,
		'last_id' => $last_id,
		'done'    => count( $ids ) < $limit,
	);
}
