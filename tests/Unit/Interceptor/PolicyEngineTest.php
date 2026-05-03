<?php

declare(strict_types=1);

namespace AIValve\Tests\Unit\Interceptor;

use AIValve\Interceptor\PolicyEngine;
use AIValve\Settings\Settings;
use AIValve\Tracking\UsageTracker;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Filters;
use PHPUnit\Framework\TestCase;

final class PolicyEngineTest extends TestCase {

	private Settings $settings;
	private UsageTracker $usage_tracker;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Default: get_option returns empty so Settings uses defaults.
		Functions\when( 'get_option' )->justReturn( [] );

		$this->settings      = new Settings();
		$this->usage_tracker = new UsageTracker( $this->settings );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/* ------------------------------------------------------------------
	 * Master switch
	 * ----------------------------------------------------------------*/

	public function test_denies_when_master_switch_disabled(): void {
		Functions\when( 'get_option' )->justReturn( [ 'enabled' => false ] );
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$engine = new PolicyEngine( $settings, $tracker );

		$this->assertFalse( $engine->evaluate( 'any-plugin', 'admin' ) );
		$this->assertSame( 'ai_valve_disabled', $engine->denial_reason() );
	}

	/* ------------------------------------------------------------------
	 * Plugin policy
	 * ----------------------------------------------------------------*/

	public function test_denies_when_plugin_explicitly_denied(): void {
		Functions\when( 'get_option' )->justReturn( [
			'plugin_policies' => [ 'bad-plugin' => 'deny' ],
		] );
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$engine = new PolicyEngine( $settings, $tracker );

		$this->assertFalse( $engine->evaluate( 'bad-plugin', 'admin' ) );
		$this->assertSame( 'plugin_denied', $engine->denial_reason() );
	}

	public function test_allows_when_plugin_explicitly_allowed(): void {
		Functions\when( 'get_option' )->justReturn( [
			'default_policy'  => 'deny',
			'plugin_policies' => [ 'good-plugin' => 'allow' ],
		] );
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$engine = new PolicyEngine( $settings, $tracker );

		$this->assertTrue( $engine->evaluate( 'good-plugin', 'admin' ) );
		$this->assertSame( '', $engine->denial_reason() );
	}

	/* ------------------------------------------------------------------
	 * Context restriction
	 * ----------------------------------------------------------------*/

	public function test_denies_when_context_not_allowed(): void {
		Functions\when( 'get_option' )->justReturn( [
			'allow_cron' => false,
		] );
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		$engine = new PolicyEngine( $settings, $tracker );

		$this->assertFalse( $engine->evaluate( 'any-plugin', 'cron' ) );
		$this->assertSame( 'context_denied', $engine->denial_reason() );
	}

	public function test_allows_when_context_is_permitted(): void {
		$engine = new PolicyEngine( $this->settings, $this->usage_tracker );

		$this->assertTrue( $engine->evaluate( 'any-plugin', 'rest' ) );
	}

	/* ------------------------------------------------------------------
	 * Per-plugin daily budget
	 * ----------------------------------------------------------------*/

	public function test_denies_when_plugin_daily_budget_exceeded(): void {
		$today = gmdate( 'Y-m-d' );
		$key   = 'ai_valve_tokens_daily_' . $today . '_my-plugin';

		Functions\when( 'get_option' )->alias( function ( $name, $default = false ) use ( $key ) {
			if ( $name === 'ai_valve_settings' ) {
				return [
					'plugin_budgets' => [
						'my-plugin' => [ 'daily' => 1000, 'monthly' => 0 ],
					],
				];
			}
			if ( $name === $key ) {
				return 1500; // Already over budget.
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$engine   = new PolicyEngine( $settings, $tracker );

		$this->assertFalse( $engine->evaluate( 'my-plugin', 'admin' ) );
		$this->assertSame( 'plugin_daily_budget_exceeded', $engine->denial_reason() );
	}

	/* ------------------------------------------------------------------
	 * Per-plugin monthly budget
	 * ----------------------------------------------------------------*/

	public function test_denies_when_plugin_monthly_budget_exceeded(): void {
		$today = gmdate( 'Y-m-d' );
		$month = gmdate( 'Y-m' );
		$daily_key   = 'ai_valve_tokens_daily_' . $today . '_my-plugin';
		$monthly_key = 'ai_valve_tokens_monthly_' . $month . '_my-plugin';

		Functions\when( 'get_option' )->alias( function ( $name, $default = false ) use ( $daily_key, $monthly_key ) {
			if ( $name === 'ai_valve_settings' ) {
				return [
					'plugin_budgets' => [
						'my-plugin' => [ 'daily' => 0, 'monthly' => 5000 ],
					],
				];
			}
			if ( $name === $daily_key ) {
				return 100;
			}
			if ( $name === $monthly_key ) {
				return 6000; // Over monthly budget.
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$engine   = new PolicyEngine( $settings, $tracker );

		$this->assertFalse( $engine->evaluate( 'my-plugin', 'admin' ) );
		$this->assertSame( 'plugin_monthly_budget_exceeded', $engine->denial_reason() );
	}

	/* ------------------------------------------------------------------
	 * Global daily budget
	 * ----------------------------------------------------------------*/

	public function test_denies_when_global_daily_budget_exceeded(): void {
		$today = gmdate( 'Y-m-d' );
		$global_key = 'ai_valve_tokens_daily_' . $today . '_*';

		Functions\when( 'get_option' )->alias( function ( $name, $default = false ) use ( $global_key ) {
			if ( $name === 'ai_valve_settings' ) {
				return [ 'global_daily_limit' => 10000 ];
			}
			if ( $name === $global_key ) {
				return 10000; // Exactly at limit.
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$engine   = new PolicyEngine( $settings, $tracker );

		$this->assertFalse( $engine->evaluate( 'any-plugin', 'admin' ) );
		$this->assertSame( 'global_daily_budget_exceeded', $engine->denial_reason() );
	}

	/* ------------------------------------------------------------------
	 * Global monthly budget
	 * ----------------------------------------------------------------*/

	public function test_denies_when_global_monthly_budget_exceeded(): void {
		$today      = gmdate( 'Y-m-d' );
		$month      = gmdate( 'Y-m' );
		$daily_key  = 'ai_valve_tokens_daily_' . $today . '_*';
		$monthly_key = 'ai_valve_tokens_monthly_' . $month . '_*';

		Functions\when( 'get_option' )->alias( function ( $name, $default = false ) use ( $daily_key, $monthly_key ) {
			if ( $name === 'ai_valve_settings' ) {
				return [ 'global_monthly_limit' => 50000 ];
			}
			if ( $name === $daily_key ) {
				return 0;
			}
			if ( $name === $monthly_key ) {
				return 55000;
			}
			return $default;
		} );

		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$engine   = new PolicyEngine( $settings, $tracker );

		$this->assertFalse( $engine->evaluate( 'any-plugin', 'admin' ) );
		$this->assertSame( 'global_monthly_budget_exceeded', $engine->denial_reason() );
	}

	/* ------------------------------------------------------------------
	 * Happy path
	 * ----------------------------------------------------------------*/

	public function test_allows_when_everything_is_within_limits(): void {
		$engine = new PolicyEngine( $this->settings, $this->usage_tracker );

		$this->assertTrue( $engine->evaluate( 'okay-plugin', 'admin' ) );
		$this->assertSame( '', $engine->denial_reason() );
	}

	/* ------------------------------------------------------------------
	 * Evaluation order — earlier checks take precedence
	 * ----------------------------------------------------------------*/

	public function test_master_switch_takes_precedence_over_plugin_policy(): void {
		Functions\when( 'get_option' )->justReturn( [
			'enabled'         => false,
			'plugin_policies' => [ 'my-plugin' => 'allow' ],
		] );
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$engine   = new PolicyEngine( $settings, $tracker );

		$this->assertFalse( $engine->evaluate( 'my-plugin', 'admin' ) );
		$this->assertSame( 'ai_valve_disabled', $engine->denial_reason() );
	}

	/* ------------------------------------------------------------------
	 * ai_valve_plugin_policy filter
	 * ----------------------------------------------------------------*/

	public function test_filter_can_deny_an_allowed_plugin(): void {
		// Database says allow, but filter overrides to deny.
		Functions\when( 'get_option' )->justReturn( [
			'plugin_policies' => [ 'trusted-plugin' => 'allow' ],
		] );
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$engine   = new PolicyEngine( $settings, $tracker );

		Filters\expectApplied( 'ai_valve_plugin_policy' )
			->once()
			->with( 'allow', 'trusted-plugin', 'admin' )
			->andReturn( 'deny' );

		$this->assertFalse( $engine->evaluate( 'trusted-plugin', 'admin' ) );
		$this->assertSame( 'plugin_denied', $engine->denial_reason() );
	}

	public function test_filter_can_allow_a_denied_plugin(): void {
		// Database says deny, but filter overrides to allow.
		Functions\when( 'get_option' )->justReturn( [
			'plugin_policies' => [ 'bad-plugin' => 'deny' ],
		] );
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$engine   = new PolicyEngine( $settings, $tracker );

		Filters\expectApplied( 'ai_valve_plugin_policy' )
			->once()
			->with( 'deny', 'bad-plugin', 'admin' )
			->andReturn( 'allow' );

		$this->assertTrue( $engine->evaluate( 'bad-plugin', 'admin' ) );
		$this->assertSame( '', $engine->denial_reason() );
	}

	public function test_filter_receives_plugin_slug_and_context(): void {
		$engine = new PolicyEngine( $this->settings, $this->usage_tracker );

		Filters\expectApplied( 'ai_valve_plugin_policy' )
			->once()
			->with( 'allow', 'my-plugin', 'cron' )
			->andReturnFirstArg();

		$result = $engine->evaluate( 'my-plugin', 'cron' );

		$this->assertTrue( $result );
	}
}
