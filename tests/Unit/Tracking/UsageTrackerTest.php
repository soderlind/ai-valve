<?php

declare(strict_types=1);

namespace AIValve\Tests\Unit\Tracking;

use AIValve\Settings\Settings;
use AIValve\Tracking\UsageTracker;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class UsageTrackerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/* ------------------------------------------------------------------
	 * record()
	 * ----------------------------------------------------------------*/

	public function test_record_increments_four_counters(): void {
		$today = gmdate( 'Y-m-d' );
		$month = gmdate( 'Y-m' );

		$updated = [];

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			if ( $key === 'ai_valve_settings' ) {
				return [];
			}
			return 100; // Existing counter value.
		} );

		Functions\when( 'update_option' )->alias( function ( string $key, $value ) use ( &$updated ) {
			$updated[ $key ] = $value;
			return true;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$tracker->record( 'test-plugin', 500 );

		// Should increment 4 keys: per-plugin daily, per-plugin monthly, global daily, global monthly.
		$this->assertSame( 600, $updated[ 'ai_valve_tokens_daily_' . $today . '_test-plugin' ] );
		$this->assertSame( 600, $updated[ 'ai_valve_tokens_monthly_' . $month . '_test-plugin' ] );
		$this->assertSame( 600, $updated[ 'ai_valve_tokens_daily_' . $today . '_*' ] );
		$this->assertSame( 600, $updated[ 'ai_valve_tokens_monthly_' . $month . '_*' ] );
	}

	public function test_record_ignores_zero_tokens(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$tracker->record( 'test-plugin', 0 );

		// If update_option were called, it would fail as unmocked.
		$this->assertTrue( true );
	}

	public function test_record_ignores_negative_tokens(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$tracker->record( 'test-plugin', -10 );

		$this->assertTrue( true );
	}

	public function test_record_increments_provider_counters_when_given(): void {
		$today = gmdate( 'Y-m-d' );
		$month = gmdate( 'Y-m' );

		$updated = [];

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			if ( $key === 'ai_valve_settings' ) {
				return [];
			}
			return 100;
		} );

		Functions\when( 'update_option' )->alias( function ( string $key, $value ) use ( &$updated ) {
			$updated[ $key ] = $value;
			return true;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$tracker->record( 'test-plugin', 500, 'openai' );

		// Should increment 6 keys: 4 standard + 2 provider.
		$this->assertSame( 600, $updated[ 'ai_valve_tokens_daily_' . $today . '_test-plugin' ] );
		$this->assertSame( 600, $updated[ 'ai_valve_tokens_monthly_' . $month . '_test-plugin' ] );
		$this->assertSame( 600, $updated[ 'ai_valve_tokens_daily_' . $today . '_*' ] );
		$this->assertSame( 600, $updated[ 'ai_valve_tokens_monthly_' . $month . '_*' ] );
		$this->assertSame( 600, $updated[ 'ai_valve_tokens_daily_' . $today . '_provider:openai' ] );
		$this->assertSame( 600, $updated[ 'ai_valve_tokens_monthly_' . $month . '_provider:openai' ] );
	}

	public function test_record_skips_provider_counters_when_empty(): void {
		$today = gmdate( 'Y-m-d' );
		$month = gmdate( 'Y-m' );

		$updated = [];

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			if ( $key === 'ai_valve_settings' ) {
				return [];
			}
			return 0;
		} );

		Functions\when( 'update_option' )->alias( function ( string $key, $value ) use ( &$updated ) {
			$updated[ $key ] = $value;
			return true;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$tracker->record( 'test-plugin', 200 );

		// Only 4 standard keys — no provider keys.
		$this->assertCount( 4, $updated );
		$this->assertArrayNotHasKey( 'ai_valve_tokens_daily_' . $today . '_provider:', $updated );
	}

	/* ------------------------------------------------------------------
	 * Read counters
	 * ----------------------------------------------------------------*/

	public function test_plugin_tokens_today_reads_correct_key(): void {
		$today = gmdate( 'Y-m-d' );
		$expected_key = 'ai_valve_tokens_daily_' . $today . '_my-plugin';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $expected_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [];
			}
			if ( $key === $expected_key ) {
				return 2500;
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$this->assertSame( 2500, $tracker->plugin_tokens_today( 'my-plugin' ) );
	}

	public function test_plugin_tokens_this_month_reads_correct_key(): void {
		$month = gmdate( 'Y-m' );
		$expected_key = 'ai_valve_tokens_monthly_' . $month . '_my-plugin';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $expected_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [];
			}
			if ( $key === $expected_key ) {
				return 45000;
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$this->assertSame( 45000, $tracker->plugin_tokens_this_month( 'my-plugin' ) );
	}

	public function test_global_tokens_today(): void {
		$today = gmdate( 'Y-m-d' );
		$expected_key = 'ai_valve_tokens_daily_' . $today . '_*';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $expected_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [];
			}
			if ( $key === $expected_key ) {
				return 7777;
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$this->assertSame( 7777, $tracker->global_tokens_today() );
	}

	public function test_global_tokens_this_month(): void {
		$month = gmdate( 'Y-m' );
		$expected_key = 'ai_valve_tokens_monthly_' . $month . '_*';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $expected_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [];
			}
			if ( $key === $expected_key ) {
				return 99000;
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$this->assertSame( 99000, $tracker->global_tokens_this_month() );
	}

	public function test_provider_tokens_today_reads_correct_key(): void {
		$today = gmdate( 'Y-m-d' );
		$expected_key = 'ai_valve_tokens_daily_' . $today . '_provider:openai';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $expected_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [];
			}
			if ( $key === $expected_key ) {
				return 3000;
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$this->assertSame( 3000, $tracker->provider_tokens_today( 'openai' ) );
	}

	public function test_provider_tokens_this_month_reads_correct_key(): void {
		$month = gmdate( 'Y-m' );
		$expected_key = 'ai_valve_tokens_monthly_' . $month . '_provider:anthropic';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $expected_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [];
			}
			if ( $key === $expected_key ) {
				return 12000;
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$this->assertSame( 12000, $tracker->provider_tokens_this_month( 'anthropic' ) );
	}

	/* ------------------------------------------------------------------
	 * Budget percentage helpers
	 * ----------------------------------------------------------------*/

	public function test_global_daily_pct_with_limit(): void {
		$today = gmdate( 'Y-m-d' );
		$global_key = 'ai_valve_tokens_daily_' . $today . '_*';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $global_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [ 'global_daily_limit' => 10000 ];
			}
			if ( $key === $global_key ) {
				return 8000;
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$this->assertEqualsWithDelta( 80.0, $tracker->global_daily_pct(), 0.01 );
	}

	public function test_global_daily_pct_returns_zero_when_no_limit(): void {
		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			if ( $key === 'ai_valve_settings' ) {
				return [ 'global_daily_limit' => 0 ];
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$this->assertSame( 0.0, $tracker->global_daily_pct() );
	}

	public function test_global_monthly_pct_with_limit(): void {
		$month = gmdate( 'Y-m' );
		$monthly_key = 'ai_valve_tokens_monthly_' . $month . '_*';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $monthly_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [ 'global_monthly_limit' => 100000 ];
			}
			if ( $key === $monthly_key ) {
				return 50000;
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$this->assertEqualsWithDelta( 50.0, $tracker->global_monthly_pct(), 0.01 );
	}
}
