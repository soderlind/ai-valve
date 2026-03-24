<?php

declare(strict_types=1);

namespace AIValve\Tests\Unit\Settings;

use AIValve\Settings\Settings;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/* ------------------------------------------------------------------
	 * defaults()
	 * ----------------------------------------------------------------*/

	public function test_defaults_returns_expected_shape(): void {
		$defaults = Settings::defaults();

		$this->assertArrayHasKey( 'enabled', $defaults );
		$this->assertArrayHasKey( 'default_policy', $defaults );
		$this->assertArrayHasKey( 'plugin_policies', $defaults );
		$this->assertArrayHasKey( 'global_daily_limit', $defaults );
		$this->assertArrayHasKey( 'global_monthly_limit', $defaults );
		$this->assertArrayHasKey( 'alert_threshold_pct', $defaults );
		$this->assertTrue( $defaults['enabled'] );
		$this->assertSame( 'allow', $defaults['default_policy'] );
		$this->assertSame( 0, $defaults['global_daily_limit'] );
	}

	/* ------------------------------------------------------------------
	 * all() / get()
	 * ----------------------------------------------------------------*/

	public function test_all_merges_stored_with_defaults(): void {
		Functions\when( 'get_option' )->justReturn( [
			'enabled'          => false,
			'global_daily_limit' => 5000,
		] );

		$settings = new Settings();
		$all      = $settings->all();

		$this->assertFalse( $all['enabled'] );
		$this->assertSame( 5000, $all['global_daily_limit'] );
		// Defaults still present for unset keys.
		$this->assertSame( 'allow', $all['default_policy'] );
	}

	public function test_all_handles_non_array_stored_value(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		$settings = new Settings();
		$this->assertTrue( $settings->is_enabled() );
	}

	public function test_get_returns_value_or_default(): void {
		Functions\when( 'get_option' )->justReturn( [ 'alert_threshold_pct' => 90 ] );

		$settings = new Settings();
		$this->assertSame( 90, $settings->get( 'alert_threshold_pct' ) );
		$this->assertNull( $settings->get( 'nonexistent' ) );
		$this->assertSame( 'fallback', $settings->get( 'nonexistent', 'fallback' ) );
	}

	/* ------------------------------------------------------------------
	 * plugin_policy()
	 * ----------------------------------------------------------------*/

	public function test_plugin_policy_explicit_deny(): void {
		Functions\when( 'get_option' )->justReturn( [
			'plugin_policies' => [ 'bad-plugin' => 'deny' ],
		] );

		$settings = new Settings();
		$this->assertSame( 'deny', $settings->plugin_policy( 'bad-plugin' ) );
	}

	public function test_plugin_policy_falls_back_to_default(): void {
		Functions\when( 'get_option' )->justReturn( [
			'default_policy' => 'deny',
		] );

		$settings = new Settings();
		$this->assertSame( 'deny', $settings->plugin_policy( 'unlisted-plugin' ) );
	}

	/* ------------------------------------------------------------------
	 * plugin_daily_budget() / plugin_monthly_budget()
	 * ----------------------------------------------------------------*/

	public function test_plugin_daily_budget_returns_configured_value(): void {
		Functions\when( 'get_option' )->justReturn( [
			'plugin_budgets' => [
				'some-plugin' => [ 'daily' => 1000, 'monthly' => 30000 ],
			],
		] );

		$settings = new Settings();
		$this->assertSame( 1000, $settings->plugin_daily_budget( 'some-plugin' ) );
		$this->assertSame( 30000, $settings->plugin_monthly_budget( 'some-plugin' ) );
	}

	public function test_plugin_budget_returns_zero_when_unset(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$settings = new Settings();
		$this->assertSame( 0, $settings->plugin_daily_budget( 'no-budget' ) );
		$this->assertSame( 0, $settings->plugin_monthly_budget( 'no-budget' ) );
	}

	/* ------------------------------------------------------------------
	 * is_context_allowed()
	 * ----------------------------------------------------------------*/

	public function test_context_allowed_defaults_to_true(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$settings = new Settings();
		$this->assertTrue( $settings->is_context_allowed( 'admin' ) );
		$this->assertTrue( $settings->is_context_allowed( 'frontend' ) );
		$this->assertTrue( $settings->is_context_allowed( 'cron' ) );
		$this->assertTrue( $settings->is_context_allowed( 'rest' ) );
		$this->assertTrue( $settings->is_context_allowed( 'ajax' ) );
		$this->assertTrue( $settings->is_context_allowed( 'cli' ) );
	}

	public function test_context_can_be_disabled(): void {
		Functions\when( 'get_option' )->justReturn( [
			'allow_cron' => false,
			'allow_cli'  => false,
		] );

		$settings = new Settings();
		$this->assertFalse( $settings->is_context_allowed( 'cron' ) );
		$this->assertFalse( $settings->is_context_allowed( 'cli' ) );
		$this->assertTrue( $settings->is_context_allowed( 'admin' ) );
	}

	public function test_unknown_context_defaults_to_allowed(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$settings = new Settings();
		$this->assertTrue( $settings->is_context_allowed( 'totally-unknown' ) );
	}

	/* ------------------------------------------------------------------
	 * update()
	 * ----------------------------------------------------------------*/

	public function test_update_merges_and_persists(): void {
		Functions\when( 'get_option' )->justReturn( [ 'enabled' => true ] );
		Functions\expect( 'update_option' )
			->once()
			->with(
				'ai_valve_settings',
				\Mockery::on( fn( $v ) => $v['enabled'] === false && $v['default_policy'] === 'allow' ),
				false
			)
			->andReturn( true );

		$settings = new Settings();
		$result   = $settings->update( [ 'enabled' => false ] );

		$this->assertTrue( $result );
	}

	/* ------------------------------------------------------------------
	 * sanitize()
	 * ----------------------------------------------------------------*/

	public function test_sanitize_returns_defaults_for_non_array(): void {
		$result = Settings::sanitize( 'garbage' );
		$this->assertSame( Settings::defaults(), $result );
	}

	public function test_sanitize_coerces_values(): void {
		Functions\when( 'absint' )->alias( fn( $v ) => abs( (int) $v ) );
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'sanitize_key' )->alias( fn( $v ) => strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $v ) ) );

		$result = Settings::sanitize( [
			'enabled'              => '1',
			'default_policy'       => 'deny',
			'global_daily_limit'   => '5000',
			'global_monthly_limit' => '-100',
			'alert_threshold_pct'  => '150',  // Will be clamped to 100.
			'allow_cron'           => '',      // Falsy.
		] );

		$this->assertTrue( $result['enabled'] );
		$this->assertSame( 'deny', $result['default_policy'] );
		$this->assertSame( 5000, $result['global_daily_limit'] );
		$this->assertSame( 100, $result['global_monthly_limit'] );  // abs(-100) = 100.
		$this->assertFalse( $result['allow_cron'] );
	}

	public function test_sanitize_rejects_invalid_default_policy(): void {
		Functions\when( 'absint' )->alias( fn( $v ) => abs( (int) $v ) );
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();

		$result = Settings::sanitize( [ 'default_policy' => 'maybe' ] );
		$this->assertSame( 'allow', $result['default_policy'] );
	}

	/* ------------------------------------------------------------------
	 * delete() / option_key()
	 * ----------------------------------------------------------------*/

	public function test_option_key_returns_constant(): void {
		$this->assertSame( 'ai_valve_settings', Settings::option_key() );
	}

	public function test_delete_calls_delete_option(): void {
		$called = false;
		Functions\when( 'delete_option' )->alias( function ( $key ) use ( &$called ) {
			$called = true;
			$this->assertSame( 'ai_valve_settings', $key );
		} );

		Settings::delete();
		$this->assertTrue( $called );
	}
}
