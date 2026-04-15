<?php

declare(strict_types=1);

namespace AIValve\Interceptor;

use AIValve\Settings\Settings;
use AIValve\Tracking\LogRepository;
use AIValve\Tracking\UsageTracker;
use WordPress\AiClient\Events\AfterGenerateResultEvent;
use WordPress\AiClient\Events\BeforeGenerateResultEvent;
use WP_AI_Client_Prompt_Builder;

/**
 * Wires the three WP 7 AI connector hooks:
 *
 * 1. `wp_ai_client_prevent_prompt`        — gate / ACL
 * 2. `wp_ai_client_before_generate_result` — insert pending log row
 * 3. `wp_ai_client_after_generate_result`  — finalise with token usage
 *
 * If the SDK throws during generation (auth error, timeout, etc.),
 * the after-event never fires. A shutdown handler catches orphaned
 * pending rows and marks them as errors.
 */
final class RequestInterceptor {

	/** Correlation state for the current request. */
	private static string $current_plugin_slug = '';
	private static string $current_context     = '';
	private static float  $request_start_time  = 0.0;

	/** Pending log row ID written in on_before_generate. */
	private static int $pending_log_id = 0;

	/** Whether the shutdown handler has been registered. */
	private static bool $shutdown_registered = false;

	public function __construct(
		private readonly Settings $settings,
		private readonly UsageTracker $usage_tracker,
	) {}

	public function register(): void {
		add_filter( 'wp_ai_client_prevent_prompt', [ $this, 'maybe_prevent' ], 10, 2 );
		add_action( 'wp_ai_client_before_generate_result', [ $this, 'on_before_generate' ], 10, 1 );
		add_action( 'wp_ai_client_after_generate_result', [ $this, 'on_after_generate' ], 10, 1 );
	}

	/* ------------------------------------------------------------------
	 * 1. Gate — wp_ai_client_prevent_prompt
	 * ----------------------------------------------------------------*/

	/**
	 * @param bool                        $prevent Already prevented by another filter?
	 * @param WP_AI_Client_Prompt_Builder $builder Read-only clone of the builder.
	 */
	public function maybe_prevent( bool $prevent, WP_AI_Client_Prompt_Builder $builder ): bool {
		// Respect upstream prevention.
		if ( $prevent ) {
			return true;
		}

		// Reset CallerDetector cache for each new prompt evaluation.
		CallerDetector::reset();

		$plugin_slug = CallerDetector::detect();
		$context     = CallerDetector::context();

		// Stash for correlation in the before/after hooks.
		self::$current_plugin_slug = $plugin_slug;
		self::$current_context     = $context;

		$engine = new PolicyEngine( $this->settings, $this->usage_tracker );

		if ( ! $engine->evaluate( $plugin_slug, $context ) ) {
			// Log the denied request.
			$repo = new LogRepository();
			$repo->insert( [
				'plugin_slug' => $plugin_slug,
				'provider_id' => '',
				'model_id'    => '',
				'capability'  => '',
				'context'     => $context,
				'status'      => 'denied:' . $engine->denial_reason(),
			] );

			return true; // Prevent the prompt.
		}

		return false;
	}

	/* ------------------------------------------------------------------
	 * 2. Before generate — insert pending log row
	 * ----------------------------------------------------------------*/

	public function on_before_generate( BeforeGenerateResultEvent $event ): void {
		self::$request_start_time = hrtime( true );

		// Ensure we have attribution even if prevent_prompt wasn't called
		// (e.g. is_supported checks bypass prevent_prompt but generate still fires).
		if ( '' === self::$current_plugin_slug ) {
			CallerDetector::reset();
			self::$current_plugin_slug = CallerDetector::detect();
			self::$current_context     = CallerDetector::context();
		}

		// Extract provider / model / capability from the event.
		$model       = $event->getModel();
		$provider_id = '';
		$model_id    = '';
		$capability  = '';

		try {
			$provider_id = $model->providerMetadata()->getId();
		} catch ( \Throwable ) {
		}

		try {
			$model_id = $model->metadata()->getId();
		} catch ( \Throwable ) {
		}

		if ( null !== $event->getCapability() ) {
			$capability = (string) $event->getCapability();
		}

		// Write a pending row so the request is visible even if after-event never fires.
		$repo   = new LogRepository();
		$row_id = $repo->insert( [
			'plugin_slug' => self::$current_plugin_slug ?: 'unknown',
			'provider_id' => $provider_id,
			'model_id'    => $model_id,
			'capability'  => $capability,
			'context'     => self::$current_context ?: CallerDetector::context(),
			'status'      => 'pending',
		] );

		self::$pending_log_id = is_int( $row_id ) ? $row_id : 0;

		// Register a one-time shutdown handler to catch orphaned pending rows.
		if ( ! self::$shutdown_registered ) {
			self::$shutdown_registered = true;
			register_shutdown_function( [ self::class, 'finalise_on_shutdown' ] );
		}
	}

	/* ------------------------------------------------------------------
	 * 3. After generate — finalise pending row with token usage
	 * ----------------------------------------------------------------*/

	public function on_after_generate( AfterGenerateResultEvent $event ): void {
		$model  = $event->getModel();
		$result = $event->getResult();
		$usage  = $result->getTokenUsage();

		$provider_id = '';
		$model_id    = '';
		$capability  = '';

		try {
			$provider_id = $model->providerMetadata()->getId();
		} catch ( \Throwable ) {
			// Some models may not expose provider metadata gracefully.
		}

		try {
			$model_id = $model->metadata()->getId();
		} catch ( \Throwable ) {
			// Defensive.
		}

		if ( null !== $event->getCapability() ) {
			$capability = (string) $event->getCapability();
		}

		$prompt_tokens     = $usage->getPromptTokens();
		$completion_tokens = $usage->getCompletionTokens();
		$total_tokens      = $usage->getTotalTokens();

		$plugin_slug = self::$current_plugin_slug ?: 'unknown';

		// Compute request duration.
		$duration_ms = 0;
		if ( self::$request_start_time > 0 ) {
			$duration_ms = (int) round( ( hrtime( true ) - self::$request_start_time ) / 1000000 );
		}

		$repo = new LogRepository();

		if ( self::$pending_log_id > 0 ) {
			// Update the pending row inserted by on_before_generate.
			$repo->update( self::$pending_log_id, [
				'provider_id'       => $provider_id,
				'model_id'          => $model_id,
				'capability'        => $capability,
				'prompt_tokens'     => $prompt_tokens,
				'completion_tokens' => $completion_tokens,
				'total_tokens'      => $total_tokens,
				'duration_ms'       => $duration_ms,
				'status'            => 'allowed',
			] );
		} else {
			// Fallback: no pending row (unlikely but defensive).
			$repo->insert( [
				'plugin_slug'       => $plugin_slug,
				'provider_id'       => $provider_id,
				'model_id'          => $model_id,
				'capability'        => $capability,
				'context'           => self::$current_context ?: CallerDetector::context(),
				'prompt_tokens'     => $prompt_tokens,
				'completion_tokens' => $completion_tokens,
				'total_tokens'      => $total_tokens,
				'duration_ms'       => $duration_ms,
				'status'            => 'allowed',
			] );
		}

		// Update rolling counters.
		$this->usage_tracker->record( $plugin_slug, $total_tokens, $provider_id );

		// Clear correlation state for the next request.
		self::$current_plugin_slug = '';
		self::$current_context     = '';
		self::$request_start_time  = 0.0;
		self::$pending_log_id      = 0;
		CallerDetector::reset();
	}

	/* ------------------------------------------------------------------
	 * Shutdown handler — catch orphaned pending rows
	 * ----------------------------------------------------------------*/

	/**
	 * If on_after_generate never fired, mark the pending row as an error.
	 *
	 * @internal Called via register_shutdown_function.
	 */
	public static function finalise_on_shutdown(): void {
		if ( self::$pending_log_id <= 0 ) {
			return;
		}

		$duration_ms = 0;
		if ( self::$request_start_time > 0 ) {
			$duration_ms = (int) round( ( hrtime( true ) - self::$request_start_time ) / 1000000 );
		}

		$repo = new LogRepository();
		$repo->update( self::$pending_log_id, [
			'duration_ms' => $duration_ms,
			'status'      => 'error',
		] );

		self::$pending_log_id     = 0;
		self::$request_start_time = 0.0;
	}

	/**
	 * Reset all static state. Intended for tests only.
	 *
	 * @internal
	 */
	public static function reset_state(): void {
		self::$current_plugin_slug = '';
		self::$current_context     = '';
		self::$request_start_time  = 0.0;
		self::$pending_log_id      = 0;
		self::$shutdown_registered = false;
	}
}
