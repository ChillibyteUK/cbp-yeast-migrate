<?php
/**
 * Plugin Name: Yoast to Yeast Migration
 * Description: Copies per-post SEO data (titles, meta descriptions, social images, canonicals, index status) from Yoast SEO's post meta into Yeast SEO.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Author: Chillibyte - DS
 * Text Domain: cbp-yeast-migrate
 *
 * @package cbp-yeast-migrate
 */

defined( 'ABSPATH' ) || exit;

define( 'CBP_YEAST_MIGRATE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CBP_YEAST_MIGRATE_URL', plugin_dir_url( __FILE__ ) );
define( 'CBP_YEAST_MIGRATE_VERSION', '1.0.0' );

require_once CBP_YEAST_MIGRATE_PATH . 'inc/variables.php';
require_once CBP_YEAST_MIGRATE_PATH . 'inc/mapper.php';
require_once CBP_YEAST_MIGRATE_PATH . 'inc/runner.php';
require_once CBP_YEAST_MIGRATE_PATH . 'inc/admin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once CBP_YEAST_MIGRATE_PATH . 'inc/cli.php';
}
