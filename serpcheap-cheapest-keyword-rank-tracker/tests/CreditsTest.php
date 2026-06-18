<?php

use PHPUnit\Framework\TestCase;
use Serpcheap\RankTracking\Credits;

final class CreditsTest extends TestCase {

	public function test_per_check_scales_with_pages(): void {
		$this->assertSame( 6, Credits::per_check( 1 ) );
		$this->assertSame( 60, Credits::per_check( 10 ) );
		$this->assertSame( 30, Credits::per_check( 5 ) );
	}

	public function test_per_check_cached_is_cheaper(): void {
		$this->assertSame( 3, Credits::per_check_cached( 1 ) );
		$this->assertSame( 30, Credits::per_check_cached( 10 ) );
	}

	public function test_checks_per_month(): void {
		$this->assertSame( 720.0, Credits::checks_per_month( 60 ) );   // hourly
		$this->assertSame( 30.0, Credits::checks_per_month( 1440 ) );  // daily
		$this->assertSame( 0.0, Credits::checks_per_month( 0 ) );      // manual
	}

	public function test_monthly_projection(): void {
		$this->assertSame( 4320, Credits::monthly( 60, null, 1 ) );    // hourly, Top 10
		$this->assertSame( 1800, Credits::monthly( 1440, null, 10 ) ); // daily, Top 100
		$this->assertSame( 0, Credits::monthly( 0, null, 10 ) );       // manual
	}

	public function test_monthly_prefers_measured_cost_when_given(): void {
		// 30 checks/month * a measured 12 credits/check.
		$this->assertSame( 360, Credits::monthly( 1440, 12, 1 ) );
	}

	public function test_clamp_pages_bounds(): void {
		$this->assertSame( 1, Credits::clamp_pages( 0 ) );
		$this->assertSame( 1, Credits::clamp_pages( -5 ) );
		$this->assertSame( 10, Credits::clamp_pages( 99 ) );
		$this->assertSame( 7, Credits::clamp_pages( 7 ) );
	}

	public function test_model_exposes_client_inputs(): void {
		$m = Credits::model();
		$this->assertSame( 6, $m['perPageFresh'] );
		$this->assertSame( 3, $m['perPageCached'] );
		$this->assertSame( 10, $m['maxPages'] );
		$this->assertSame( 43200, $m['minutesMonth'] );
	}
}
