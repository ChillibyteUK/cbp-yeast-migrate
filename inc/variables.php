<?php
/**
 * Resolves Yoast %%variables%% in stored titles and descriptions.
 *
 * Yeast stores literal strings, so anything Yoast would have expanded at
 * render time has to be expanded here before it is copied across.
 *
 * @package cbp-yeast-migrate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Maps Yoast's separator option value to the character it renders.
 *
 * @return string[]
 */
function cbp_yeast_migrate_separators() {
	return array(
		'sc-dash'   => '-',
		'sc-ndash'  => '–',
		'sc-mdash'  => '—',
		'sc-colon'  => ':',
		'sc-middot' => '·',
		'sc-bull'   => '•',
		'sc-star'   => '*',
		'sc-smstar' => '⋆',
		'sc-pipe'   => '|',
		'sc-tilde'  => '~',
		'sc-laquo'  => '«',
		'sc-raquo'  => '»',
		'sc-lt'     => '<',
		'sc-gt'     => '>',
	);
}

/**
 * Returns Yoast's title/meta settings.
 *
 * @return array
 */
function cbp_yeast_migrate_yoast_titles() {
	$titles = get_option( 'wpseo_titles', array() );
	return is_array( $titles ) ? $titles : array();
}

/**
 * Returns the separator character Yoast is configured to use.
 *
 * @return string
 */
function cbp_yeast_migrate_separator() {
	$titles     = cbp_yeast_migrate_yoast_titles();
	$separators = cbp_yeast_migrate_separators();
	$key        = isset( $titles['separator'] ) ? $titles['separator'] : 'sc-dash';

	return isset( $separators[ $key ] ) ? $separators[ $key ] : '-';
}

/**
 * Returns the category names for a post, primary category first.
 *
 * @param int $post_id Post ID.
 * @return string[]
 */
function cbp_yeast_migrate_category_names( $post_id ) {
	$terms = get_the_terms( $post_id, 'category' );

	if ( ! is_array( $terms ) ) {
		return array();
	}

	$primary_id = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
	$names      = array();

	foreach ( $terms as $term ) {
		if ( $primary_id && (int) $term->term_id === $primary_id ) {
			array_unshift( $names, $term->name );
		} else {
			$names[] = $term->name;
		}
	}

	return $names;
}

/**
 * Builds the variable => value map for one post.
 *
 * @param WP_Post $item Post object.
 * @return array
 */
function cbp_yeast_migrate_variable_map( $item ) {
	$post_id    = (int) $item->ID;
	$type       = get_post_type_object( $item->post_type );
	$categories = cbp_yeast_migrate_category_names( $post_id );
	$tags       = wp_list_pluck( (array) get_the_terms( $post_id, 'post_tag' ), 'name' );
	$excerpt    = '' !== trim( $item->post_excerpt ) ? $item->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $item->post_content ) ), 30, '' );
	$parent     = $item->post_parent ? get_the_title( $item->post_parent ) : '';

	return array(
		'title'            => get_the_title( $post_id ),
		'parent_title'     => $parent,
		'sitename'         => get_bloginfo( 'name' ),
		'sitedesc'         => get_bloginfo( 'description' ),
		'sep'              => cbp_yeast_migrate_separator(),
		'page'             => '',
		'pagenumber'       => '',
		'pagetotal'        => '',
		'primary_category' => $categories ? $categories[0] : '',
		'category'         => implode( ', ', $categories ),
		'tag'              => implode( ', ', array_filter( (array) $tags, 'is_string' ) ),
		'excerpt'          => $excerpt,
		'excerpt_only'     => $item->post_excerpt,
		'name'             => get_the_author_meta( 'display_name', $item->post_author ),
		'date'             => get_the_date( '', $post_id ),
		'modified'         => get_the_modified_date( '', $post_id ),
		'id'               => (string) $post_id,
		'focuskw'          => (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
		'pt_single'        => $type ? $type->labels->singular_name : '',
		'pt_plural'        => $type ? $type->labels->name : '',
		'currentyear'      => wp_date( 'Y' ),
		'currentmonth'     => wp_date( 'F' ),
		'currentday'       => wp_date( 'j' ),
	);
}

/**
 * Expands Yoast variables in a string for one post.
 *
 * @param string  $text Raw Yoast value.
 * @param WP_Post $item Post object.
 * @return string Expanded text. May still contain %%vars%% if any were unknown.
 */
function cbp_yeast_migrate_expand( $text, $item ) {
	if ( false === strpos( $text, '%%' ) ) {
		return $text;
	}

	$map  = cbp_yeast_migrate_variable_map( $item );
	$text = preg_replace_callback(
		'/%%([a-z_0-9\-]+)%%/i',
		function ( $matches ) use ( $map, $item ) {
			$name = $matches[1];

			if ( array_key_exists( $name, $map ) ) {
				return $map[ $name ];
			}

			if ( 0 === strpos( $name, 'cf_' ) ) {
				return (string) get_post_meta( $item->ID, substr( $name, 3 ), true );
			}

			return $matches[0];
		},
		$text
	);

	if ( false !== strpos( $text, '%%' ) && class_exists( 'WPSEO_Replace_Vars' ) ) {
		$replacer = new WPSEO_Replace_Vars();
		$text     = $replacer->replace( $text, $item );
	}

	return trim( preg_replace( '/\s+/', ' ', $text ) );
}
