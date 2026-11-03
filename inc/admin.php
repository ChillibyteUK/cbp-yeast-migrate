<?php
/**
 * Tools page and AJAX batch endpoint.
 *
 * @package cbp-yeast-migrate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Tools → Yoast to Yeast page.
 *
 * @return void
 */
function cbp_yeast_migrate_add_page() {
	add_management_page(
		__( 'Yoast to Yeast', 'cbp-yeast-migrate' ),
		__( 'Yoast to Yeast', 'cbp-yeast-migrate' ),
		'manage_options',
		'cbp-yeast-migrate',
		'cbp_yeast_migrate_render_page'
	);
}
add_action( 'admin_menu', 'cbp_yeast_migrate_add_page' );

/**
 * Loads the page script.
 *
 * @param string $hook Admin page hook.
 * @return void
 */
function cbp_yeast_migrate_enqueue( $hook ) {
	if ( 'tools_page_cbp-yeast-migrate' !== $hook ) {
		return;
	}

	wp_enqueue_script(
		'cbp-yeast-migrate',
		CBP_YEAST_MIGRATE_URL . 'assets/admin.js',
		array(),
		CBP_YEAST_MIGRATE_VERSION,
		true
	);

	wp_localize_script(
		'cbp-yeast-migrate',
		'cbpYeastMigrate',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cbp_yeast_migrate' ),
			'total'   => cbp_yeast_migrate_count(),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'cbp_yeast_migrate_enqueue' );

/**
 * Renders the Tools page.
 *
 * @return void
 */
function cbp_yeast_migrate_render_page() {
	$yeast_active = function_exists( 'cbp_yeast_seo_post_types' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Yoast to Yeast Migration', 'cbp-yeast-migrate' ); ?></h1>
		<?php if ( ! $yeast_active ) { ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Yeast SEO is not active. Activate it before running a live migration, so the copied values are picked up on the front end.', 'cbp-yeast-migrate' ); ?></p></div>
		<?php } ?>
		<p><?php esc_html_e( 'Copies page titles, meta descriptions, Open Graph/Twitter images, canonical URLs and index status from Yoast SEO post meta into Yeast SEO. Yoast can be deactivated first; its data is read straight from the database and is never changed or deleted.', 'cbp-yeast-migrate' ); ?></p>
		<p>
			<?php
			/* translators: %d: number of posts */
			echo esc_html( sprintf( __( '%d posts have Yoast data to look at.', 'cbp-yeast-migrate' ), cbp_yeast_migrate_count() ) );
			?>
		</p>
		<p>
			<label><input type="checkbox" id="cbp-ym-dry" checked> <?php esc_html_e( 'Dry run (preview only, nothing is written)', 'cbp-yeast-migrate' ); ?></label><br>
			<label><input type="checkbox" id="cbp-ym-overwrite"> <?php esc_html_e( 'Overwrite values already set in Yeast (off: existing Yeast values are kept)', 'cbp-yeast-migrate' ); ?></label>
		</p>
		<p><button type="button" class="button button-primary" id="cbp-ym-run"><?php esc_html_e( 'Run', 'cbp-yeast-migrate' ); ?></button> <span id="cbp-ym-status"></span></p>
		<table class="widefat striped" id="cbp-ym-results" style="display:none;">
			<thead><tr><th>ID</th><th><?php esc_html_e( 'Post', 'cbp-yeast-migrate' ); ?></th><th><?php esc_html_e( 'Changes', 'cbp-yeast-migrate' ); ?></th><th><?php esc_html_e( 'Notes', 'cbp-yeast-migrate' ); ?></th></tr></thead>
			<tbody></tbody>
		</table>
	</div>
	<?php
}

/**
 * Handles one AJAX batch.
 *
 * @return void
 */
function cbp_yeast_migrate_ajax_batch() {
	check_ajax_referer( 'cbp_yeast_migrate', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Forbidden', 403 );
	}

	$after     = isset( $_POST['after'] ) ? absint( $_POST['after'] ) : 0;
	$dry_run   = ! empty( $_POST['dry_run'] ) && 'false' !== $_POST['dry_run'];
	$overwrite = ! empty( $_POST['overwrite'] ) && 'false' !== $_POST['overwrite'];

	wp_send_json_success( cbp_yeast_migrate_batch( $after, 50, $dry_run, $overwrite ) );
}
add_action( 'wp_ajax_cbp_yeast_migrate_batch', 'cbp_yeast_migrate_ajax_batch' );
