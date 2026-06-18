<?php
/**
 * CRUD for the trackers table. All queries use $wpdb->prepare.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Data;

use Serpcheap\RankTracking\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TrackersRepository {

	private function table(): string {
		return Plugin::table( 'trackers' );
	}

	/**
	 * @param array<string,mixed> $data
	 * @return int new tracker id (0 on failure).
	 */
	public function create( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$row = array(
			'target_type' => $data['target_type'],
			'target_ref'  => isset( $data['target_ref'] ) ? (int) $data['target_ref'] : null,
			'taxonomy'    => isset( $data['taxonomy'] ) ? $data['taxonomy'] : null,
			'target_url'  => $data['target_url'],
			'keyword'     => $data['keyword'],
			'gl'          => $data['gl'],
			'hl'          => isset( $data['hl'] ) ? $data['hl'] : null,
			'match_type'  => $data['match_type'],
			'schedule'    => $data['schedule'],
			'interval_minutes' => isset( $data['interval_minutes'] ) ? (int) $data['interval_minutes'] : Plugin::interval_minutes( $data['schedule'] ),
			'pages'       => isset( $data['pages'] ) ? \Serpcheap\RankTracking\Credits::clamp_pages( (int) $data['pages'] ) : \Serpcheap\RankTracking\Credits::default_pages(),
			'status'      => 'active',
			'next_run'    => $now,
			'created_at'  => $now,
		);

		$ok = $wpdb->insert( $this->table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/** @return array<string,mixed>|null */
	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/** @return array<int,array<string,mixed>> */
	public function for_target( string $type, ?int $ref, ?string $taxonomy = null ): array {
		global $wpdb;
		if ( null === $ref ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
				$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE target_type = %s ORDER BY id DESC', $type ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					'SELECT * FROM ' . $this->table() . ' WHERE target_type = %s AND target_ref = %d ORDER BY id DESC',
					$type,
					$ref
				),
				ARRAY_A
			);
		}
		return $rows ? $rows : array();
	}

	/** @return array<int,array<string,mixed>> */
	public function all(): array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . $this->table() . ' ORDER BY id DESC', ARRAY_A ); // phpcs:ignore WordPress.DB
		return $rows ? $rows : array();
	}

	/** Trackers due to run now (cron). @return array<int,array<string,mixed>> */
	public function due( int $limit = 20 ): array {
		global $wpdb;
		$now  = current_time( 'mysql', true );
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE status = 'active' AND ( next_run IS NULL OR next_run <= %s ) ORDER BY next_run ASC LIMIT %d",
				$now,
				$limit
			),
			ARRAY_A
		);
		return $rows ? $rows : array();
	}

	public function count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table() ); // phpcs:ignore WordPress.DB
	}

	public function update_result( int $id, ?int $rank, string $next_run ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			$this->table(),
			array(
				'current_rank' => $rank,
				'last_checked' => current_time( 'mysql', true ),
				'next_run'     => $next_run,
			),
			array( 'id' => $id )
		);
	}

	/** Update the schedule/interval and recompute next_run. */
	public function update_schedule( int $id, string $schedule, int $interval_minutes ): void {
		global $wpdb;
		$next_run = $interval_minutes > 0
			? gmdate( 'Y-m-d H:i:s', time() + ( $interval_minutes * 60 ) )
			: '2099-01-01 00:00:00';
		$wpdb->update( // phpcs:ignore WordPress.DB
			$this->table(),
			array(
				'schedule'         => $schedule,
				'interval_minutes' => $interval_minutes,
				'next_run'         => $next_run,
			),
			array( 'id' => $id )
		);
	}

	public function update_pages( int $id, int $pages ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			$this->table(),
			array( 'pages' => \Serpcheap\RankTracking\Credits::clamp_pages( $pages ) ),
			array( 'id' => $id )
		);
	}

	public function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( $this->table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( Plugin::table( 'rank_history' ), array( 'tracker_id' => $id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( Plugin::table( 'alerts' ), array( 'tracker_id' => $id ) ); // phpcs:ignore WordPress.DB
	}
}
