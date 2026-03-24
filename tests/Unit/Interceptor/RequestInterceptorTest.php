<?php

declare(strict_types=1);

namespace AIValve\Tests\Unit\Interceptor;

use AIValve\Interceptor\CallerDetector;
use AIValve\Interceptor\RequestInterceptor;
use AIValve\Settings\Settings;
use AIValve\Tracking\UsageTracker;
use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class RequestInterceptorTest extends TestCase {

	private Settings $settings;
	private UsageTracker $usage_tracker;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'get_option' )->justReturn( [] );

		$this->settings      = new Settings();
		$this->usage_tracker = new UsageTracker( $this->settings );
	}

	protected function tearDown(): void {
		CallerDetector::reset();
		Monkey\tearDown();
		parent::tearDown();
	}

	/* ------------------------------------------------------------------
	 * register()
	 * ----------------------------------------------------------------*/

	public function test_register_hooks_three_events(): void {
		$interceptor = new RequestInterceptor( $this->settings, $this->usage_tracker );
		$interceptor->register();

		// Brain Monkey tracks add_filter / add_action calls.
		$this->assertTrue(
			Filters\has( 'wp_ai_client_prevent_prompt', [ $interceptor, 'ensure_event_dispatcher' ] ) !== false
		);
		$this->assertTrue(
			Filters\has( 'wp_ai_client_prevent_prompt', [ $interceptor, 'maybe_prevent' ] ) !== false
		);
		$this->assertTrue(
			Actions\has( 'wp_ai_client_before_generate_result', [ $interceptor, 'on_before_generate' ] ) !== false
		);
		$this->assertTrue(
			Actions\has( 'wp_ai_client_after_generate_result', [ $interceptor, 'on_after_generate' ] ) !== false
		);
	}

	/* ------------------------------------------------------------------
	 * maybe_prevent() — respects upstream prevention
	 * ----------------------------------------------------------------*/

	public function test_maybe_prevent_returns_true_when_already_prevented(): void {
		$interceptor = new RequestInterceptor( $this->settings, $this->usage_tracker );

		$builder = $this->createMock( \WP_AI_Client_Prompt_Builder::class );

		// Already prevented by another filter.
		$result = $interceptor->maybe_prevent( true, $builder );
		$this->assertTrue( $result );
	}

	/* ------------------------------------------------------------------
	 * maybe_prevent() — master switch off
	 * ----------------------------------------------------------------*/

	public function test_maybe_prevent_blocks_when_disabled(): void {
		Functions\when( 'get_option' )->justReturn( [ 'enabled' => false ] );
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		// Stub CallerDetector deps.
		Functions\when( 'wp_normalize_path' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'get_theme_root' )->justReturn( '/tmp/themes' );

		// Stub $wpdb for LogRepository::insert (denial path writes a log row).
		$wpdb_mock = new \stdClass();
		$wpdb_mock->prefix    = 'wp_';
		$wpdb_mock->insert_id = 1;
		$wpdb_mock->insert    = fn() => 1;
		// Make insert() callable as a method.
		$GLOBALS['wpdb'] = \Mockery::mock( $wpdb_mock );
		$GLOBALS['wpdb']->prefix    = 'wp_';
		$GLOBALS['wpdb']->insert_id = 1;
		$GLOBALS['wpdb']->shouldReceive( 'insert' )->andReturn( 1 );

		$interceptor = new RequestInterceptor( $settings, $tracker );
		$builder     = $this->createMock( \WP_AI_Client_Prompt_Builder::class );

		$result = $interceptor->maybe_prevent( false, $builder );
		$this->assertTrue( $result ); // Should block because disabled.

		unset( $GLOBALS['wpdb'] );
	}

	/* ------------------------------------------------------------------
	 * maybe_prevent() — allows when policy passes
	 * ----------------------------------------------------------------*/

	public function test_maybe_prevent_allows_when_policy_passes(): void {
		// Default settings: enabled=true, default_policy=allow, all contexts true.
		Functions\when( 'get_option' )->justReturn( [] );
		$settings = new Settings();
		$tracker  = new UsageTracker( $settings );

		Functions\when( 'wp_normalize_path' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'get_theme_root' )->justReturn( '/tmp/themes' );

		$interceptor = new RequestInterceptor( $settings, $tracker );
		$builder     = $this->createMock( \WP_AI_Client_Prompt_Builder::class );

		$result = $interceptor->maybe_prevent( false, $builder );
		$this->assertFalse( $result ); // Should allow.
	}
}
