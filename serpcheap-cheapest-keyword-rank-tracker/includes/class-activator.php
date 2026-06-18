<?php
/**
 * Activation: create custom tables, seed defaults, schedule cron.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {

	public static function activate(): void {
		self::create_tables();

		if ( false === get_option( 'serpcheap_default_schedule' ) ) {
			add_option( 'serpcheap_default_schedule', 'daily' );
		}

		if ( false === get_option( 'serpcheap_default_pages' ) ) {
			add_option( 'serpcheap_default_pages', 1 );
		}

		if ( ! wp_next_scheduled( 'serpcheap_dispatch' ) ) {
			wp_schedule_event( time() + 60, 'serpcheap_five_minutes', 'serpcheap_dispatch' );
		}
	}

	private static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$trackers        = Plugin::table( 'trackers' );
		$history         = Plugin::table( 'rank_history' );

		dbDelta(
			"CREATE TABLE {$trackers} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				target_type VARCHAR(10) NOT NULL,
				target_ref BIGINT UNSIGNED NULL,
				taxonomy VARCHAR(32) NULL,
				target_url TEXT NOT NULL,
				keyword VARCHAR(255) NOT NULL,
				gl VARCHAR(2) NOT NULL DEFAULT 'us',
				hl VARCHAR(8) NULL,
				match_type VARCHAR(10) NOT NULL DEFAULT 'domain',
				schedule VARCHAR(10) NOT NULL DEFAULT 'daily',
				interval_minutes INT NOT NULL DEFAULT 1440,
				pages TINYINT UNSIGNED NOT NULL DEFAULT 1,
				current_rank SMALLINT NULL,
				last_checked DATETIME NULL,
				next_run DATETIME NULL,
				status VARCHAR(10) NOT NULL DEFAULT 'active',
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY target_idx (target_type, target_ref, taxonomy),
				KEY next_run_idx (status, next_run),
				KEY keyword_idx (keyword(64))
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$history} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				tracker_id BIGINT UNSIGNED NOT NULL,
				checked_at DATETIME NOT NULL,
				rank SMALLINT NULL,
				found TINYINT(1) NOT NULL DEFAULT 0,
				pages_scanned SMALLINT NULL,
				cost INT NULL,
				balance INT NULL,
				PRIMARY KEY  (id),
				KEY tracker_time_idx (tracker_id, checked_at)
			) {$charset_collate};"
		);

		$alerts = Plugin::table( 'alerts' );
		dbDelta(
			"CREATE TABLE {$alerts} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				tracker_id BIGINT UNSIGNED NOT NULL,
				type VARCHAR(20) NOT NULL,
				severity VARCHAR(10) NOT NULL DEFAULT 'warning',
				old_rank SMALLINT NULL,
				new_rank SMALLINT NULL,
				message TEXT NOT NULL,
				is_read TINYINT(1) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY tracker_idx (tracker_id),
				KEY unread_idx (is_read, created_at)
			) {$charset_collate};"
		);

		update_option( 'serpcheap_db_version', self::DB_VERSION );
	}

	const DB_VERSION = '4';

	/** Run idempotent dbDelta when the schema version changed (boot-time migration). */
	public static function maybe_upgrade(): void {
		if ( get_option( 'serpcheap_db_version' ) !== self::DB_VERSION ) {
			self::create_tables();
		}
	}
}
