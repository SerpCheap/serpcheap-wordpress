<?php
/**
 * CRUD for the rank-history table (sparklines, Δ7d, charts).
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Data;

use Serpcheap\RankTracking\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HistoryRepository {

	private function table(): string {
		return Plugin::table( 'rank_history' );
	}

	/** @param array<string,mixed> $row */
	public function insert( int $tracker_id, array $row ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$this->table(),
			array(
				'tracker_id'    => $tracker_id,
				'checked_at'    => isset( $row['checked_at'] ) ? $row['checked_at'] : current_time( 'mysql', true ),
				'rank'          => isset( $row['rank'] ) ? $row['rank'] : null,
				'found'         => ! empty( $row['found'] ) ? 1 : 0,
				'pages_scanned' => isset( $row['pages_scanned'] ) ? (int) $row['pages_scanned'] : null,
				'cost'          => isset( $row['cost'] ) ? $row['cost'] : null,
				'balance'       => isset( $row['balance'] ) ? $row['balance'] : null,
			)
		);
	}

	/** @param array<int,array<string,mixed>> $rows */
	public function insert_many( int $tracker_id, array $rows ): void {
		foreach ( $rows as $row ) {
			$this->insert( $tracker_id, $row );
		}
	}

	/**
	 * Most recent N points (oldest-first), for sparklines.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function recent( int $tracker_id, int $limit = 30 ): array {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT rank, found, checked_at FROM {$this->table()} WHERE tracker_id = %d ORDER BY checked_at DESC LIMIT %d",
				$tracker_id,
				$limit
			),
			ARRAY_A
		);
		$rows = $rows ? array_reverse( $rows ) : array();
		return $rows;
	}

	/**
	 * Batched per-tracker aggregates (sparkline, dated points, 7d baseline rank,
	 * latest balance, last/avg cost) in a constant number of queries — replaces
	 * the per-tracker N+1 on the list view.
	 *
	 * @param array<int,int> $ids
	 * @return array<int,array<string,mixed>>
	 */
	public function aggregates_for( array $ids ): array {
		global $wpdb;
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		$out = array();
		foreach ( $ids as $id ) {
			$out[ $id ] = array(
				'sparkline'      => array(),
				'points'         => array(),
				'delta_baseline' => null,
				'balance'        => null,
				'cost_last'      => null,
				'cost_avg'       => null,
			);
		}
		if ( ! $ids ) {
			return $out;
		}

		if ( ! $this->supports_window() ) {
			return $this->aggregates_fallback( $ids );
		}

		$in     = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );

		$recent = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT tracker_id, rank, checked_at, cost, balance FROM (
					SELECT tracker_id, rank, checked_at, cost, balance,
						ROW_NUMBER() OVER ( PARTITION BY tracker_id ORDER BY checked_at DESC ) AS rn
					FROM {$this->table()} WHERE tracker_id IN ( {$in} )
				) w WHERE rn <= 30 ORDER BY tracker_id ASC, checked_at ASC",
				$ids
			),
			ARRAY_A
		);

		$grouped = array();
		foreach ( (array) $recent as $r ) {
			$grouped[ (int) $r['tracker_id'] ][] = $r;
		}
		foreach ( $grouped as $id => $rows ) {
			$costs = array();
			foreach ( $rows as $r ) {
				$rank                       = null === $r['rank'] ? null : (int) $r['rank'];
				$out[ $id ]['sparkline'][]  = $rank;
				$out[ $id ]['points'][]     = array( 'date' => gmdate( 'Y-m-d', strtotime( $r['checked_at'] ) ), 'rank' => $rank );
				if ( null !== $r['balance'] ) {
					$out[ $id ]['balance'] = (int) $r['balance'];
				}
				if ( null !== $r['cost'] ) {
					$out[ $id ]['cost_last'] = (int) $r['cost'];
					$costs[]                 = (int) $r['cost'];
				}
			}
			$out[ $id ]['cost_avg'] = $costs ? (int) round( array_sum( $costs ) / count( $costs ) ) : null;
		}

		$baseline = $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT tracker_id, rank FROM (
					SELECT tracker_id, rank,
						ROW_NUMBER() OVER ( PARTITION BY tracker_id ORDER BY checked_at DESC ) AS rn
					FROM {$this->table()} WHERE tracker_id IN ( {$in} ) AND rank IS NOT NULL AND checked_at <= %s
				) w WHERE rn = 1",
				array_merge( $ids, array( $cutoff ) )
			),
			ARRAY_A
		);
		foreach ( (array) $baseline as $b ) {
			$out[ (int) $b['tracker_id'] ]['delta_baseline'] = (int) $b['rank'];
		}

		return $out;
	}

	/** @param array<int,int> $ids @return array<int,array<string,mixed>> */
	private function aggregates_fallback( array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$rows  = $this->recent( $id, 30 );
			$spark = array();
			$pts   = array();
			foreach ( $rows as $h ) {
				$rank    = null !== $h['rank'] ? (int) $h['rank'] : null;
				$spark[] = $rank;
				$pts[]   = array( 'date' => isset( $h['checked_at'] ) ? gmdate( 'Y-m-d', strtotime( $h['checked_at'] ) ) : '', 'rank' => $rank );
			}
			$out[ $id ] = array(
				'sparkline'      => $spark,
				'points'         => $pts,
				'delta_baseline' => null,
				'balance'        => $this->latest_balance( $id ),
				'cost_last'      => null,
				'cost_avg'       => null,
			);
		}
		return $out;
	}

	private function supports_window(): bool {
		global $wpdb;
		static $supported = null;
		if ( null !== $supported ) {
			return $supported;
		}
		$ver = (string) $wpdb->get_var( 'SELECT VERSION()' );
		if ( false !== stripos( $ver, 'mariadb' ) ) {
			$supported = version_compare( preg_replace( '/[^0-9.].*$/', '', $ver ), '10.2', '>=' );
		} else {
			$supported = version_compare( preg_replace( '/[^0-9.].*$/', '', $ver ), '8.0.2', '>=' );
		}
		return $supported;
	}

	/** Latest known credit balance for a tracker (from its most recent check). */
	public function latest_balance( int $tracker_id ): ?int {
		global $wpdb;
		$bal = $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT balance FROM {$this->table()} WHERE tracker_id = %d AND balance IS NOT NULL ORDER BY checked_at DESC LIMIT 1",
				$tracker_id
			)
		);
		return null === $bal ? null : (int) $bal;
	}

	/**
	 * Rank change vs ~7 days ago (negative = improved/moved up). Null if no baseline.
	 */
	public function delta_7d( int $tracker_id, ?int $current_rank ): ?int {
		global $wpdb;
		if ( null === $current_rank ) {
			return null;
		}
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
		$past   = $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT rank FROM {$this->table()} WHERE tracker_id = %d AND rank IS NOT NULL AND checked_at <= %s ORDER BY checked_at DESC LIMIT 1",
				$tracker_id,
				$cutoff
			)
		);
		if ( null === $past ) {
			return null;
		}
		return (int) $current_rank - (int) $past;
	}
}
