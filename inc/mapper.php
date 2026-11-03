<?php
/**
 * Maps one post's Yoast meta onto Yeast meta.
 *
 * @package cbp-yeast-migrate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Yoast post-meta keys this plugin reads.
 *
 * @return string[]
 */
function cbp_yeast_migrate_source_keys() {
	return array(
		'_yoast_wpseo_title',
		'_yoast_wpseo_metadesc',
		'_yoast_wpseo_opengraph-title',
		'_yoast_wpseo_opengraph-description',
		'_yoast_wpseo_opengraph-image',
		'_yoast_wpseo_opengraph-image-id',
		'_yoast_wpseo_twitter-image',
		'_yoast_wpseo_twitter-image-id',
		'_yoast_wpseo_canonical',
		'_yoast_wpseo_meta-robots-noindex',
		'_yoast_wpseo_meta-robots-nofollow',
	);
}

/**
 * Human labels for the Yeast keys written, used in reports.
 *
 * @return string[]
 */
function cbp_yeast_migrate_target_labels() {
	return array(
		'cbp_yeast_seo_title'            => 'Page title',
		'cbp_yeast_seo_meta_description' => 'Meta description',
		'cbp_yeast_seo_og_title'         => 'Open Graph title',
		'cbp_yeast_seo_og_description'   => 'Open Graph description',
		'cbp_yeast_seo_og_image'         => 'Open Graph image',
		'cbp_yeast_seo_twitter_image'    => 'Twitter/X image',
		'cbp_yeast_seo_canonical'        => 'Canonical URL',
		'cbp_yeast_seo_robots_index'     => 'Indexing',
	);
}

/**
 * Reads a Yoast image, preferring the stored URL and falling back to the attachment ID.
 *
 * @param int    $post_id  Post ID.
 * @param string $url_key  Meta key holding the URL.
 * @param string $id_key   Meta key holding the attachment ID.
 * @return string
 */
function cbp_yeast_migrate_read_image( $post_id, $url_key, $id_key ) {
	$url = trim( (string) get_post_meta( $post_id, $url_key, true ) );

	if ( '' !== $url ) {
		return esc_url_raw( $url );
	}

	$attachment_id = (int) get_post_meta( $post_id, $id_key, true );
	$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';

	return $url ? esc_url_raw( $url ) : '';
}

/**
 * Works out what a post's Yeast indexing value should be.
 *
 * Yoast stores 1 = noindex, 2 = index, 0 = follow the post type default.
 *
 * @param WP_Post $item Post object.
 * @return string 'noindex' or 'index'.
 */
function cbp_yeast_migrate_read_robots( $item ) {
	$value = (string) get_post_meta( $item->ID, '_yoast_wpseo_meta-robots-noindex', true );

	if ( '1' === $value ) {
		return 'noindex';
	}

	if ( '2' === $value ) {
		return 'index';
	}

	$titles = cbp_yeast_migrate_yoast_titles();

	return ! empty( $titles[ 'noindex-' . $item->post_type ] ) ? 'noindex' : 'index';
}

/**
 * Builds the set of Yeast values to write for one post.
 *
 * Nothing is written here. Existing Yeast values are left alone unless
 * $overwrite is true, and empty Yoast values never blank a Yeast value.
 *
 * @param int  $post_id   Post ID.
 * @param bool $overwrite Whether to replace non-empty Yeast values.
 * @return array {changes: array<string,string>, notes: string[]}
 */
function cbp_yeast_migrate_map_post( $post_id, $overwrite = false ) {
	$item    = get_post( $post_id );
	$changes = array();
	$notes   = array();

	if ( ! $item ) {
		return array(
			'changes' => $changes,
			'notes'   => array( 'Post not found.' ),
		);
	}

	$candidates = array();

	foreach ( array(
		'cbp_yeast_seo_title'            => '_yoast_wpseo_title',
		'cbp_yeast_seo_meta_description' => '_yoast_wpseo_metadesc',
		'cbp_yeast_seo_og_title'         => '_yoast_wpseo_opengraph-title',
		'cbp_yeast_seo_og_description'   => '_yoast_wpseo_opengraph-description',
	) as $target => $source ) {
		$raw = trim( (string) get_post_meta( $post_id, $source, true ) );

		if ( '' === $raw ) {
			continue;
		}

		$value = cbp_yeast_migrate_expand( $raw, $item );

		if ( false !== strpos( $value, '%%' ) ) {
			$notes[] = 'Skipped ' . cbp_yeast_migrate_target_labels()[ $target ] . ': unresolved variable in "' . $raw . '".';
			continue;
		}

		$candidates[ $target ] = ( 'cbp_yeast_seo_meta_description' === $target || 'cbp_yeast_seo_og_description' === $target )
			? sanitize_textarea_field( $value )
			: sanitize_text_field( $value );
	}

	$candidates['cbp_yeast_seo_og_image']      = cbp_yeast_migrate_read_image( $post_id, '_yoast_wpseo_opengraph-image', '_yoast_wpseo_opengraph-image-id' );
	$candidates['cbp_yeast_seo_twitter_image'] = cbp_yeast_migrate_read_image( $post_id, '_yoast_wpseo_twitter-image', '_yoast_wpseo_twitter-image-id' );
	$candidates['cbp_yeast_seo_canonical']     = esc_url_raw( trim( (string) get_post_meta( $post_id, '_yoast_wpseo_canonical', true ) ) );

	if ( 'noindex' === cbp_yeast_migrate_read_robots( $item ) ) {
		$candidates['cbp_yeast_seo_robots_index'] = 'noindex';
	}

	foreach ( $candidates as $target => $value ) {
		if ( '' === $value ) {
			continue;
		}

		$existing = trim( (string) get_post_meta( $post_id, $target, true ) );

		if ( $existing === $value ) {
			continue;
		}

		$is_set = '' !== $existing && ! ( 'cbp_yeast_seo_robots_index' === $target && 'index' === $existing );

		if ( $is_set && ! $overwrite ) {
			$notes[] = 'Kept existing ' . cbp_yeast_migrate_target_labels()[ $target ] . '.';
			continue;
		}

		$changes[ $target ] = $value;
	}

	if ( '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true ) ) {
		$notes[] = 'Nofollow was set in Yoast; Yeast has no nofollow control, so it was not migrated.';
	}

	return array(
		'changes' => $changes,
		'notes'   => $notes,
	);
}
