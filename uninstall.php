<?php
/**
 * Removes every trace of EO-BOTS: tables, options and each user's EO-BOT.
 *
 * @package EoBots
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'eobots_conversations', 'eobots_messages', 'eobots_journal' ) as $eobots_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$eobots_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
}

delete_option( 'eobots_db_version' );
delete_transient( 'eobots_site_profile' );
delete_metadata( 'user', 0, 'eobots_avatar', '', true );

// Snippets: posts, built PHP files and settings.
foreach ( get_posts(
	array(
		'post_type'      => 'eobots_snippet',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
) as $eobots_snippet ) {
	wp_delete_post( $eobots_snippet, true );
}
foreach ( (array) glob( WP_CONTENT_DIR . '/eo-bots-snippets/*' ) as $eobots_file ) {
	wp_delete_file( $eobots_file );
}
foreach ( array( '.htaccess' ) as $eobots_file ) {
	wp_delete_file( WP_CONTENT_DIR . '/eo-bots-snippets/' . $eobots_file );
}
@rmdir( WP_CONTENT_DIR . '/eo-bots-snippets' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
delete_option( 'eobots_snippets' );
delete_option( 'eobots_snippet_alert' );
delete_option( 'eobots_safe_key' );
delete_option( 'eobots_notes' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'eobots\_lock\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
