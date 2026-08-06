<?php

declare(strict_types=1);

namespace Soderlind\AiValve\Tests\Unit\Interceptor;

use Soderlind\AiValve\Interceptor\AbilityInterceptor;
use Soderlind\AiValve\Interceptor\CallerDetector;
use Soderlind\AiValve\Settings\Settings;
use Soderlind\AiValve\Tracking\UsageTracker;
use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class AbilityInterceptorTest extends TestCase {

	private Settings $settings;
	private UsageTracker $usage_tracker;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( '__' )->returnArg();

		// Default context resolves to 'admin'.
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( true );

		$this->settings      = new Settings();
		$this->usage_tracker = new UsageTracker( $this->settings );
	}

	protected function tearDown(): void {
		CallerDetector::reset();
		unset( $GLOBALS['wpdb'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Mock $wpdb so LogRepository::insert() can run in the deny path.
	 */
	private function mock_wpdb(): void {
		$wpdb = \Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 7;
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );
		$GLOBALS['wpdb'] = $wpdb;
	}

	/* ------------------------------------------------------------------
	 * register()
	 * ----------------------------------------------------------------*/

	public function test_register_hooks_pre_execute_and_invoked(): void {
		$interceptor = new AbilityInterceptor( $this->settings, $this->usage_tracker );
		$interceptor->register();

		$this->assertNotFalse(
			Filters\has( 'wp_pre_execute_ability', array( $interceptor, 'maybe_prevent_ability' ) )
		);
		$this->assertNotFalse(
			Actions\has( 'wp_ability_invoked', array( $interceptor, 'on_ability_invoked' ) )
		);
	}

	/* ------------------------------------------------------------------
	 * maybe_prevent_ability() — allow passes $pre through unchanged
	 * ----------------------------------------------------------------*/

	public function test_allow_returns_pre_unchanged(): void {
		$interceptor = new AbilityInterceptor( $this->settings, $this->usage_tracker );

		$sentinel = new \stdClass();
		$result   = $interceptor->maybe_prevent_ability( $sentinel, 'my-plugin/do-thing' );

		$this->assertSame( $sentinel, $result );
	}

	/* ------------------------------------------------------------------
	 * maybe_prevent_ability() — deny when master switch off
	 * ----------------------------------------------------------------*/

	public function test_denies_with_wp_error_when_disabled(): void {
		Functions\when( 'get_option' )->justReturn( array( 'enabled' => false ) );
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$this->mock_wpdb();

		$interceptor = new AbilityInterceptor( $settings, $tracker );
		$result      = $interceptor->maybe_prevent_ability( new \stdClass(), 'my-plugin/do-thing' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ability_prevented', $result->get_error_code() );
	}

	/* ------------------------------------------------------------------
	 * maybe_prevent_ability() — deny by namespace policy + fires action
	 * ----------------------------------------------------------------*/

	public function test_denies_when_namespace_policy_is_deny(): void {
		Functions\when( 'get_option' )->justReturn(
			array( 'plugin_policies' => array( 'bad-namespace' => 'deny' ) )
		);
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$this->mock_wpdb();

		$interceptor = new AbilityInterceptor( $settings, $tracker );
		$result      = $interceptor->maybe_prevent_ability( new \stdClass(), 'bad-namespace/run' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 403, $result->error_data['ability_prevented']['status'] );
	}

	/* ------------------------------------------------------------------
	 * on_ability_invoked() — fires telemetry action, no raw input
	 * ----------------------------------------------------------------*/

	public function test_on_ability_invoked_fires_action(): void {
		$interceptor = new AbilityInterceptor( $this->settings, $this->usage_tracker );

		$interceptor->on_ability_invoked( 'my-plugin/do-thing', array( 'secret' => 'x' ) );

		$this->assertSame(
			1,
			Actions\did( 'aivalve_ability_invoked' )
		);
	}

	/* ------------------------------------------------------------------
	 * namespace parsing — malformed name maps to 'unknown'
	 * ----------------------------------------------------------------*/

	public function test_malformed_ability_name_uses_unknown_namespace(): void {
		Functions\when( 'get_option' )->justReturn(
			array( 'default_policy' => 'deny' )
		);
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );
		$this->mock_wpdb();

		$captured = null;
		Actions\expectDone( 'aivalve_ability_denied' )
			->once()
			->with( 'no-namespace', 'unknown', 'admin', 'plugin_denied' );

		$interceptor = new AbilityInterceptor( $settings, $tracker );
		$result      = $interceptor->maybe_prevent_ability( new \stdClass(), 'no-namespace' );

		unset( $captured );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}
}
