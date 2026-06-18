<?php
/**
 * Cron schedules + the dispatch tick that processes due trackers.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Cron;

use Serpcheap\RankTracking\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scheduler {

	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'schedules' ) );
		add_action( 'serpcheap_dispatch', array( $this, 'on_dispatch' ) );
	}

	/**
	 * @param array<string,array<string,mixed>> $schedules
	 * @return array<string,array<string,mixed>>
	 */
	public function schedules( array $schedules ): array {
		$schedules['serpcheap_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes (serp.cheap)', 'serpcheap-cheapest-keyword-rank-tracker' ),
		);
		return $schedules;
	}

	public function on_dispatch(): void {
		( new Runner( Plugin::instance() ) )->run_due();
	}
}
