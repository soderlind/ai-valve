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
use WordPress\AiClient\Events\AfterGenerateResultEvent;
use WordPress\AiClient\Events\BeforeGenerateResultEvent;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;

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
		RequestInterceptor::reset_state();
		unset( $GLOBALS['wpdb'] );
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

	/* ------------------------------------------------------------------
	 * Helper: build mock Model, events, and $wpdb
	 * ----------------------------------------------------------------*/

	private function make_model( string $provider = 'azure-ai-foundry', string $model = 'gpt-4.1' ): ModelInterface {
		$mock = $this->createMock( ModelInterface::class );
		$mock->method( 'providerMetadata' )->willReturn( new ProviderMetadata( $provider, $provider ) );
		$mock->method( 'metadata' )->willReturn( new ModelMetadata( $model, $model ) );
		return $mock;
	}

	private function make_before_event( ?ModelInterface $model = null ): BeforeGenerateResultEvent {
		return new BeforeGenerateResultEvent(
			[],
			$model ?? $this->make_model(),
			CapabilityEnum::textGeneration(),
		);
	}

	private function make_after_event( ?ModelInterface $model = null, int $total = 100 ): AfterGenerateResultEvent {
		$usage  = new TokenUsage( 40, 60, $total );
		$result = new GenerativeAiResult( $usage );
		return new AfterGenerateResultEvent(
			[],
			$model ?? $this->make_model(),
			CapabilityEnum::textGeneration(),
			$result,
		);
	}

	/**
	 * Create a Mockery $wpdb that expects insert().
	 *
	 * @param int $insert_id ID returned by insert.
	 * @return \Mockery\MockInterface
	 */
	private function mock_wpdb( int $insert_id = 42 ): \Mockery\MockInterface {
		$wpdb = \Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = $insert_id;
		$wpdb->shouldReceive( 'insert' )->andReturnUsing( function () use ( $wpdb, $insert_id ) {
			$wpdb->insert_id = $insert_id;
			return 1;
		} );
		$GLOBALS['wpdb'] = $wpdb;
		return $wpdb;
	}

	private function stub_caller_detector_deps(): void {
		Functions\when( 'wp_normalize_path' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'get_theme_root' )->justReturn( '/tmp/themes' );
	}

	/* ------------------------------------------------------------------
	 * on_before_generate — inserts a pending row
	 * ----------------------------------------------------------------*/

	public function test_on_before_generate_inserts_pending_row(): void {
		$this->stub_caller_detector_deps();
		Functions\when( 'register_shutdown_function' )->justReturn( true );

		$wpdb = \Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 42;

		// Expect insert is called with status = 'pending'.
		$captured_data = null;
		$wpdb->shouldReceive( 'insert' )->once()->andReturnUsing(
			function ( $table, $data ) use ( $wpdb, &$captured_data ) {
				$captured_data = $data;
				$wpdb->insert_id = 42;
				return 1;
			}
		);

		$GLOBALS['wpdb'] = $wpdb;

		$interceptor = new RequestInterceptor( $this->settings, $this->usage_tracker );
		$interceptor->on_before_generate( $this->make_before_event() );

		$this->assertNotNull( $captured_data );
		$this->assertSame( 'pending', $captured_data['status'] );

		unset( $GLOBALS['wpdb'] );
	}

	/* ------------------------------------------------------------------
	 * on_after_generate — updates pending row to allowed
	 * ----------------------------------------------------------------*/

	public function test_on_after_generate_updates_pending_row_to_allowed(): void {
		$this->stub_caller_detector_deps();
		Functions\when( 'register_shutdown_function' )->justReturn( true );
		Functions\when( 'update_option' )->justReturn( true );

		$wpdb = $this->mock_wpdb( 42 );

		// Capture the update call to verify status.
		$updated_data = null;
		$wpdb->shouldReceive( 'update' )->once()->andReturnUsing(
			function ( $table, $data, $where ) use ( &$updated_data ) {
				$updated_data = $data;
				return 1;
			}
		);

		$interceptor = new RequestInterceptor( $this->settings, $this->usage_tracker );

		// Simulate the full lifecycle: before → after.
		$model = $this->make_model();
		$interceptor->on_before_generate( $this->make_before_event( $model ) );
		$interceptor->on_after_generate( $this->make_after_event( $model, 100 ) );

		$this->assertNotNull( $updated_data );
		$this->assertSame( 'allowed', $updated_data['status'] );
		$this->assertSame( 100, $updated_data['total_tokens'] );

		unset( $GLOBALS['wpdb'] );
	}

	/* ------------------------------------------------------------------
	 * finalise_on_shutdown — marks orphaned pending row as error
	 * ----------------------------------------------------------------*/

	public function test_finalise_on_shutdown_marks_pending_as_error(): void {
		$this->stub_caller_detector_deps();
		Functions\when( 'register_shutdown_function' )->justReturn( true );

		$wpdb = $this->mock_wpdb( 99 );

		// Capture the update call from the shutdown handler.
		$updated_data = null;
		$wpdb->shouldReceive( 'update' )->once()->andReturnUsing(
			function ( $table, $data, $where ) use ( &$updated_data ) {
				$updated_data = $data;
				return 1;
			}
		);

		$interceptor = new RequestInterceptor( $this->settings, $this->usage_tracker );

		// Only call before — simulates SDK exception preventing after-event.
		$interceptor->on_before_generate( $this->make_before_event() );

		// Invoke the shutdown handler manually.
		RequestInterceptor::finalise_on_shutdown();

		$this->assertNotNull( $updated_data );
		$this->assertSame( 'error', $updated_data['status'] );
		$this->assertArrayHasKey( 'duration_ms', $updated_data );

		unset( $GLOBALS['wpdb'] );
	}

	/* ------------------------------------------------------------------
	 * finalise_on_shutdown — no-op when after-event already ran
	 * ----------------------------------------------------------------*/

	public function test_finalise_on_shutdown_noop_when_after_event_ran(): void {
		$this->stub_caller_detector_deps();
		Functions\when( 'register_shutdown_function' )->justReturn( true );
		Functions\when( 'update_option' )->justReturn( true );

		$wpdb = $this->mock_wpdb( 42 );

		// Allow exactly one update call (from on_after_generate).
		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		$interceptor = new RequestInterceptor( $this->settings, $this->usage_tracker );

		$model = $this->make_model();
		$interceptor->on_before_generate( $this->make_before_event( $model ) );
		$interceptor->on_after_generate( $this->make_after_event( $model ) );

		// Shutdown should do nothing (pending_log_id already cleared).
		RequestInterceptor::finalise_on_shutdown();

		// If we reach here without extra update calls, test passes.
		$this->assertTrue( true );

		unset( $GLOBALS['wpdb'] );
	}
}
