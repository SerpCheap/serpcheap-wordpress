<?php
/**
 * Uninstall: remove plugin tables, options and scheduled events.
 *
 * @package Serpcheap\RankTracking
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'serpcheap_trackers',
	$wpdb->prefix . 'serpcheap_rank_history',
);
foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $table );
}

delete_option( 'serpcheap_connection' );
delete_option( 'serpcheap_default_schedule' );
delete_option( 'serpcheap_db_version' );

wp_clear_scheduled_hook( 'serpcheap_dispatch' );
