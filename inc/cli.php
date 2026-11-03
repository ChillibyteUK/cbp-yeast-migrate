<?php
/**
 * WP-CLI command.
 *
 * @package cbp-yeast-migrate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Migrates Yoast SEO post meta to Yeast SEO.
 */
class CBP_Yeast_Migrate_CLI {

	/**
	 * Runs the migration.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change without writing anything.
	 *
	 * [--overwrite]
	 * : Replace values already set in Yeast.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cbp-yeast-migrate run --dry-run
	 *     wp cbp-yeast-migrate run
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 * @return void
	 */
	public function run( $args, $assoc_args ) {
		$dry_run   = ! empty( $assoc_args['dry-run'] );
		$overwrite = ! empty( $assoc_args['overwrite'] );
		$after     = 0;
		$changed   = 0;
		$seen      = 0;

		do {
			$batch = cbp_yeast_migrate_batch( $after, 100, $dry_run, $overwrite );
			$after = $batch['last_id'];

			foreach ( $batch['rows'] as $row ) {
				++$seen;

				if ( $row['changes'] ) {
					++$changed;
					WP_CLI::log( sprintf( '#%d %s: %s', $row['id'], $row['title'], implode( ', ', wp_list_pluck( $row['changes'], 'field' ) ) ) );
				}

				foreach ( $row['notes'] as $note ) {
					WP_CLI::warning( sprintf( '#%d %s', $row['id'], $note ) );
				}
			}
		} while ( ! $batch['done'] );

		WP_CLI::success( sprintf( '%s %d of %d posts.', $dry_run ? 'Would update' : 'Updated', $changed, $seen ) );
	}
}

WP_CLI::add_command( 'cbp-yeast-migrate', 'CBP_Yeast_Migrate_CLI' );
