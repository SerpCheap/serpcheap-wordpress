<?php
/**
 * Mock API client — deterministic, offline rank data so the whole UX is testable
 * without the real serp.cheap API or an OAuth connection. Same response shape as
 * the live /v1/rank endpoint.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MockClient implements ClientInterface {

	public function rank( string $url, string $keyword, array $opts = array() ): array {
		$day    = (int) floor( time() / DAY_IN_SECONDS );
		$result = $this->rank_for_day( $url, $keyword, $opts, $day );

		$cost                       = $result['found'] ? 6 : 6;
		$result['stats']['cost']    = $cost;
		$result['stats']['balance'] = $this->spend( $cost );

		return $result;
	}

	/**
	 * Backfill a believable rank history for the past N days (demo only).
	 *
	 * @param array<string,mixed> $opts
	 * @return array<int,array<string,mixed>> rows ready for HistoryRepository::insert().
	 */
	public function history( string $url, string $keyword, array $opts = array(), int $days = 14 ): array {
		$rows  = array();
		$today = (int) floor( time() / DAY_IN_SECONDS );
		for ( $i = $days; $i >= 1; $i-- ) {
			$day = $today - $i;
			$r   = $this->rank_for_day( $url, $keyword, $opts, $day );
			$rows[] = array(
				'checked_at'    => gmdate( 'Y-m-d H:i:s', ( $day * DAY_IN_SECONDS ) + ( 9 * HOUR_IN_SECONDS ) ),
				'rank'          => $r['rank'],
				'found'         => $r['found'] ? 1 : 0,
				'pages_scanned' => $r['pages_scanned'],
				'cost'          => null,
				'balance'       => null,
			);
		}
		return $rows;
	}

	/**
	 * @param array<string,mixed> $opts
	 * @return array<string,mixed>
	 */
	private function rank_for_day( string $url, string $keyword, array $opts, int $day ): array {
		$gl    = isset( $opts['gl'] ) ? $opts['gl'] : 'us';
		$pages = isset( $opts['pages'] ) ? max( 1, (int) $opts['pages'] ) : 1;
		$seed  = crc32( strtolower( $url ) . '|' . strtolower( $keyword ) . '|' . $gl );
		$base  = ( $seed % 28 ) + 2;          // 2..29 baseline position
		$h     = crc32( $seed . ':' . $day );
		$wobble = ( $h % 9 ) - 4;             // -4..+4 per-day drift
		$rank  = max( 1, min( 100, $base + $wobble ) );

		$found = true;
		if ( 0 === ( $seed % 23 ) ) {
			$found = false;                  // ~chronically outside top 100
		} elseif ( $base >= 27 && 0 === ( $h % 5 ) ) {
			$found = false;                  // occasional drop-out on hard keywords
		}

		$rank_value = $found ? (int) $rank : null;

		return array(
			'url'           => $url,
			'search'        => $keyword,
			'gl'            => $gl,
			'match_type'    => isset( $opts['match_type'] ) ? $opts['match_type'] : 'domain',
			'pages_scanned' => $pages,
			'found'         => $found,
			'rank'          => $rank_value,
			'matches'       => $found ? array(
				array(
					'rank'             => $rank_value,
					'page'             => (int) ceil( $rank_value / 10 ),
					'position_on_page' => ( ( $rank_value - 1 ) % 10 ) + 1,
					'link'             => $url,
					'title'            => ucwords( $keyword ),
				),
			) : array(),
			'organic'       => array(),
			'partial'       => false,
			'pages_failed'  => array(),
			'stats'         => array(
				'balance'      => 0,
				'cost'         => 0,
				'pages_cached' => 0,
				'pages_fresh'  => $pages,
			),
		);
	}

	/** Decrement the demo balance stored on the fake connection. */
	private function spend( int $cost ): int {
		$conn    = get_option( 'serpcheap_connection', array() );
		$balance = isset( $conn['balance'] ) ? (int) $conn['balance'] : 100000;
		$balance = max( 0, $balance - $cost );
		$conn['balance'] = $balance;
		update_option( 'serpcheap_connection', $conn );
		return $balance;
	}
}
