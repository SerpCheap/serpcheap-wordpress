<?php
/**
 * Credit cost model — mirrors the serp.cheap /v1/rank pricing so the UI can
 * show and project what tracking will spend.
 *
 * @package Serpcheap\RankTracking
 */

namespace Serpcheap\RankTracking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Credits {

	const PER_PAGE_FRESH  = 6;
	const PER_PAGE_CACHED = 3;
	const DEFAULT_PAGES   = 1;
	const MAX_PAGES       = 10;

	const MINUTES_PER_MONTH = 43200; // 30 days.

	/** Site-wide default search depth (pages), clamped 1..10. */
	public static function default_pages(): int {
		$p = (int) get_option( 'serpcheap_default_pages', self::DEFAULT_PAGES );
		return self::clamp_pages( $p );
	}

	public static function clamp_pages( int $pages ): int {
		return max( 1, min( self::MAX_PAGES, $pages ) );
	}

	public static function pages(): int {
		return (int) apply_filters( 'serpcheap_rank_pages', self::default_pages(), null );
	}

	/** Worst-case (all fresh) credits for one check. */
	public static function per_check( ?int $pages = null ): int {
		$pages = null === $pages ? self::pages() : $pages;
		return $pages * self::PER_PAGE_FRESH;
	}

	/** Best-case (fully cached) credits for one check. */
	public static function per_check_cached( ?int $pages = null ): int {
		$pages = null === $pages ? self::pages() : $pages;
		return $pages * self::PER_PAGE_CACHED;
	}

	/** Checks per month for an interval (0 = manual → 0). */
	public static function checks_per_month( int $interval_minutes ): float {
		return $interval_minutes > 0 ? self::MINUTES_PER_MONTH / $interval_minutes : 0.0;
	}

	/**
	 * Projected monthly credits. Uses a measured per-check cost when known,
	 * else the worst-case estimate.
	 */
	public static function monthly( int $interval_minutes, ?int $cost_per_check = null, ?int $pages = null ): int {
		$cost = null !== $cost_per_check && $cost_per_check > 0 ? $cost_per_check : self::per_check( $pages );
		return (int) round( self::checks_per_month( $interval_minutes ) * $cost );
	}

	/** @return array<string,int> client-side simulation inputs. */
	public static function model(): array {
		return array(
			'perPageFresh'  => self::PER_PAGE_FRESH,
			'perPageCached' => self::PER_PAGE_CACHED,
			'pages'         => self::pages(),
			'defaultPages'  => self::default_pages(),
			'maxPages'      => self::MAX_PAGES,
			'minutesMonth'  => self::MINUTES_PER_MONTH,
		);
	}
}
