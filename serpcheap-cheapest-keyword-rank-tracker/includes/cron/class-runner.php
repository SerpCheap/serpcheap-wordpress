<?php
/**
 * Executes rank checks: resolve URL → API client → write history → update tracker.
 * Used by both the cron dispatch and "refresh now".
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Cron;

use Serpcheap\RankTracking\Alerts;
use Serpcheap\RankTracking\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Runner {

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/** Process a batch of due trackers (cron). */
	public function run_due( int $limit = 20 ): int {
		$due = $this->plugin->trackers()->due( $limit );
		$n   = 0;
		foreach ( $due as $tracker ) {
			$this->run( $tracker );
			++$n;
		}
		return $n;
	}

	/**
	 * Run a single tracker now.
	 *
	 * @param array<string,mixed> $tracker
	 * @return array<string,mixed>|null the API result, or null if unresolvable.
	 */
	public function run( array $tracker ): ?array {
		$id  = (int) $tracker['id'];
		$url = $this->plugin->resolver()->resolve(
			$tracker['target_type'],
			isset( $tracker['target_ref'] ) ? (int) $tracker['target_ref'] : null,
			isset( $tracker['taxonomy'] ) ? $tracker['taxonomy'] : null,
			$tracker['target_url']
		);

		if ( ! $url ) {
			$this->plugin->trackers()->update_result( $id, null, $this->next_run( $tracker ) );
			return null;
		}

		$pages = isset( $tracker['pages'] ) ? \Serpcheap\RankTracking\Credits::clamp_pages( (int) $tracker['pages'] ) : \Serpcheap\RankTracking\Credits::default_pages();
		$opts  = array(
			'gl'         => $tracker['gl'],
			'match_type' => $tracker['match_type'],
			'pages'      => (int) apply_filters( 'serpcheap_rank_pages', $pages, $tracker ),
		);
		if ( ! empty( $tracker['hl'] ) ) {
			$opts['hl'] = $tracker['hl'];
		}

		try {
			$result = $this->plugin->client()->rank( $url, $tracker['keyword'], $opts );
		} catch ( \Throwable $e ) {
			// Leave the tracker as-is; retry on the next tick.
			return null;
		}

		$rank  = isset( $result['rank'] ) && null !== $result['rank'] ? (int) $result['rank'] : null;
		$stats = isset( $result['stats'] ) && is_array( $result['stats'] ) ? $result['stats'] : array();

		$this->plugin->history()->insert(
			$id,
			array(
				'checked_at'    => current_time( 'mysql', true ),
				'rank'          => $rank,
				'found'         => ! empty( $result['found'] ),
				'pages_scanned' => isset( $result['pages_scanned'] ) ? (int) $result['pages_scanned'] : null,
				'cost'          => isset( $stats['cost'] ) ? (int) $stats['cost'] : null,
				'balance'       => isset( $stats['balance'] ) ? (int) $stats['balance'] : null,
			)
		);

		if ( ! empty( $tracker['last_checked'] ) ) {
			$old = isset( $tracker['current_rank'] ) && null !== $tracker['current_rank'] ? (int) $tracker['current_rank'] : null;
			( new Alerts( $this->plugin ) )->evaluate( $tracker, $old, $rank );
		}

		$this->plugin->trackers()->update_result( $id, $rank, $this->next_run( $tracker ) );

		return $result;
	}

	/** @param array<string,mixed> $tracker */
	private function next_run( array $tracker ): string {
		$minutes = isset( $tracker['interval_minutes'] )
			? (int) $tracker['interval_minutes']
			: Plugin::interval_minutes( isset( $tracker['schedule'] ) ? $tracker['schedule'] : 'daily' );

		if ( $minutes <= 0 ) {
			return '2099-01-01 00:00:00';
		}
		return gmdate( 'Y-m-d H:i:s', time() + ( $minutes * 60 ) );
	}
}
