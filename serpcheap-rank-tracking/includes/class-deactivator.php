<?php
/**
 * Deactivation: clear scheduled events. (Tables/options are kept; removed on uninstall.)
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'serpcheap_dispatch' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'serpcheap_dispatch' );
		}
		wp_clear_scheduled_hook( 'serpcheap_dispatch' );
	}
}
