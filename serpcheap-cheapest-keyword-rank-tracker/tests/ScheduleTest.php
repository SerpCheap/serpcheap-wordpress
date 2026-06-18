<?php

use PHPUnit\Framework\TestCase;
use Serpcheap\RankTracking\Plugin;

final class ScheduleTest extends TestCase {

	public function test_presets_map_to_minutes(): void {
		$this->assertSame( 60, Plugin::interval_minutes( 'hourly' ) );
		$this->assertSame( 360, Plugin::interval_minutes( '6h' ) );
		$this->assertSame( 720, Plugin::interval_minutes( '12h' ) );
		$this->assertSame( 1440, Plugin::interval_minutes( 'daily' ) );
		$this->assertSame( 10080, Plugin::interval_minutes( 'weekly' ) );
		$this->assertSame( 0, Plugin::interval_minutes( 'manual' ) );
	}

	public function test_custom_interval_is_clamped(): void {
		$this->assertSame( 180, Plugin::interval_minutes( 'custom', 180 ) );
		$this->assertSame( 15, Plugin::interval_minutes( 'custom', 5 ) );       // min 15
		$this->assertSame( 40320, Plugin::interval_minutes( 'custom', 999999 ) ); // max 28d
	}

	public function test_unknown_schedule_falls_back_to_daily(): void {
		$this->assertSame( 1440, Plugin::interval_minutes( 'bogus' ) );
		$this->assertSame( 1440, Plugin::interval_minutes( 'custom', 0 ) ); // custom needs a positive value
	}

	public function test_presets_list(): void {
		$presets = Plugin::schedule_presets();
		$this->assertSame( array( 'hourly', '6h', '12h', 'daily', 'weekly', 'manual' ), array_keys( $presets ) );
	}
}
