<?php

declare(strict_types=1);

namespace AIValve\Tests\Unit\Alert;

use AIValve\Alert\AlertManager;
use AIValve\Settings\Settings;
use AIValve\Tracking\UsageTracker;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class AlertManagerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/* ------------------------------------------------------------------
	 * register()
	 * ----------------------------------------------------------------*/

	public function test_register_hooks_admin_notices_and_widget(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$manager  = new AlertManager( $settings, $tracker );
		$manager->register();

		$this->assertTrue(
			\Brain\Monkey\Actions\has( 'admin_notices', [ $manager, 'maybe_show_notice' ] ) !== false
		);
		$this->assertTrue(
			\Brain\Monkey\Actions\has( 'wp_dashboard_setup', [ $manager, 'register_dashboard_widget' ] ) !== false
		);
	}

	/* ------------------------------------------------------------------
	 * maybe_show_notice() — no notice when under threshold
	 * ----------------------------------------------------------------*/

	public function test_no_notice_when_usage_under_threshold(): void {
		$today      = gmdate( 'Y-m-d' );
		$month      = gmdate( 'Y-m' );
		$daily_key  = 'ai_valve_tokens_daily_' . $today . '_*';
		$monthly_key = 'ai_valve_tokens_monthly_' . $month . '_*';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $daily_key, $monthly_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [
					'global_daily_limit'   => 10000,
					'global_monthly_limit' => 100000,
					'alert_threshold_pct'  => 80,
				];
			}
			if ( $key === $daily_key ) {
				return 5000; // 50% — under threshold.
			}
			if ( $key === $monthly_key ) {
				return 30000; // 30% — under threshold.
			}
			return $default;
		} );

		Functions\when( 'current_user_can' )->justReturn( true );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$manager  = new AlertManager( $settings, $tracker );

		// Capture output — should be empty.
		ob_start();
		$manager->maybe_show_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/* ------------------------------------------------------------------
	 * maybe_show_notice() — warning when at threshold
	 * ----------------------------------------------------------------*/

	public function test_warning_notice_at_threshold(): void {
		$today     = gmdate( 'Y-m-d' );
		$month     = gmdate( 'Y-m' );
		$daily_key = 'ai_valve_tokens_daily_' . $today . '_*';
		$monthly_key = 'ai_valve_tokens_monthly_' . $month . '_*';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $daily_key, $monthly_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [
					'global_daily_limit'   => 10000,
					'global_monthly_limit' => 0, // No monthly limit.
					'alert_threshold_pct'  => 80,
				];
			}
			if ( $key === $daily_key ) {
				return 8500; // 85% — at threshold.
			}
			if ( $key === $monthly_key ) {
				return 0;
			}
			return $default;
		} );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'number_format_i18n' )->alias( fn( $n ) => number_format( (float) $n ) );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_kses' )->returnArg();

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$manager  = new AlertManager( $settings, $tracker );

		ob_start();
		$manager->maybe_show_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( '85', $output );
	}

	/* ------------------------------------------------------------------
	 * maybe_show_notice() — error when exceeded
	 * ----------------------------------------------------------------*/

	public function test_error_notice_when_exceeded(): void {
		$today      = gmdate( 'Y-m-d' );
		$month      = gmdate( 'Y-m' );
		$daily_key  = 'ai_valve_tokens_daily_' . $today . '_*';
		$monthly_key = 'ai_valve_tokens_monthly_' . $month . '_*';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $daily_key, $monthly_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [
					'global_daily_limit'   => 10000,
					'global_monthly_limit' => 0,
					'alert_threshold_pct'  => 80,
					'alert_email'          => '',
				];
			}
			if ( $key === $daily_key ) {
				return 12000; // 120%.
			}
			if ( $key === $monthly_key ) {
				return 0;
			}
			return $default;
		} );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'number_format_i18n' )->alias( fn( $n ) => number_format( (float) $n ) );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_kses' )->returnArg();
		Functions\when( 'is_email' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( false );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$manager  = new AlertManager( $settings, $tracker );

		ob_start();
		$manager->maybe_show_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'exceeded', $output );
	}

	/* ------------------------------------------------------------------
	 * maybe_show_notice() — skips non-admin users
	 * ----------------------------------------------------------------*/

	public function test_no_notice_for_non_admin(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'current_user_can' )->justReturn( false );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$manager  = new AlertManager( $settings, $tracker );

		ob_start();
		$manager->maybe_show_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/* ------------------------------------------------------------------
	 * maybe_show_notice() — skips when plugin disabled
	 * ----------------------------------------------------------------*/

	public function test_no_notice_when_plugin_disabled(): void {
		Functions\when( 'get_option' )->justReturn( [ 'enabled' => false ] );
		Functions\when( 'current_user_can' )->justReturn( true );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$manager  = new AlertManager( $settings, $tracker );

		ob_start();
		$manager->maybe_show_notice();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/* ------------------------------------------------------------------
	 * Email — sends once per day
	 * ----------------------------------------------------------------*/

	public function test_email_sent_when_budget_exceeded(): void {
		$today      = gmdate( 'Y-m-d' );
		$month      = gmdate( 'Y-m' );
		$daily_key  = 'ai_valve_tokens_daily_' . $today . '_*';
		$monthly_key = 'ai_valve_tokens_monthly_' . $month . '_*';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $daily_key, $monthly_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [
					'global_daily_limit'  => 10000,
					'global_monthly_limit' => 0,
					'alert_threshold_pct' => 80,
					'alert_email'         => 'admin@example.com',
				];
			}
			if ( $key === $daily_key ) {
				return 15000;
			}
			if ( $key === $monthly_key ) {
				return 0;
			}
			return $default;
		} );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'number_format_i18n' )->alias( fn( $n ) => number_format( (float) $n ) );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_kses' )->returnArg();
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'get_bloginfo' )->justReturn( 'Test Site' );
		Functions\when( 'admin_url' )->justReturn( 'http://example.com/wp-admin/' );

		$mail_sent = false;
		Functions\when( 'wp_mail' )->alias( function ( $to ) use ( &$mail_sent ) {
			$mail_sent = true;
			return true;
		} );
		Functions\when( 'set_transient' )->justReturn( true );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$manager  = new AlertManager( $settings, $tracker );

		ob_start();
		$manager->maybe_show_notice();
		ob_get_clean();

		$this->assertTrue( $mail_sent, 'wp_mail should have been called' );
	}

	public function test_email_not_sent_twice_same_day(): void {
		$today      = gmdate( 'Y-m-d' );
		$month      = gmdate( 'Y-m' );
		$daily_key  = 'ai_valve_tokens_daily_' . $today . '_*';
		$monthly_key = 'ai_valve_tokens_monthly_' . $month . '_*';

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) use ( $daily_key, $monthly_key ) {
			if ( $key === 'ai_valve_settings' ) {
				return [
					'global_daily_limit'  => 10000,
					'global_monthly_limit' => 0,
					'alert_threshold_pct' => 80,
					'alert_email'         => 'admin@example.com',
				];
			}
			if ( $key === $daily_key ) {
				return 15000;
			}
			if ( $key === $monthly_key ) {
				return 0;
			}
			return $default;
		} );

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'number_format_i18n' )->alias( fn( $n ) => number_format( (float) $n ) );
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_kses' )->returnArg();
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( 1 ); // Already sent.

		$mail_sent = false;
		Functions\when( 'wp_mail' )->alias( function () use ( &$mail_sent ) {
			$mail_sent = true;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$manager  = new AlertManager( $settings, $tracker );

		ob_start();
		$manager->maybe_show_notice();
		ob_get_clean();

		$this->assertFalse( $mail_sent, 'wp_mail should NOT be called when transient exists' );
	}
}
