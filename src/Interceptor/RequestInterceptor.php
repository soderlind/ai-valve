<?php

declare(strict_types=1);

namespace AIValve\Interceptor;

use AIValve\Settings\Settings;
use AIValve\Tracking\LogRepository;
use AIValve\Tracking\UsageTracker;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Events\AfterGenerateResultEvent;
use WordPress\AiClient\Events\BeforeGenerateResultEvent;
use WP_AI_Client_Prompt_Builder;

/**
 * Wires the three WP 7 AI connector hooks:
 *
 * 1. `wp_ai_client_prevent_prompt`        — gate / ACL + inject event dispatcher
 * 2. `wp_ai_client_before_generate_result` — start tracking
 * 3. `wp_ai_client_after_generate_result`  — log token usage
 *
 * NOTE: WP 7.0 has a core bug where `wp_ai_client_prompt()` creates the
 * PromptBuilder without passing the event dispatcher. We fix this by injecting
 * the dispatcher via reflection in our `prevent_prompt` filter. The clone
 * shares the same inner SDK PromptBuilder, so the fix propagates to the original.
 */
final class RequestInterceptor {

	/** Correlation state for the current request. */
	private static string $current_plugin_slug = '';
	private static string $current_context     = '';

	public function __construct(
		private readonly Settings $settings,
		private readonly UsageTracker $usage_tracker,
	) {}

	public function register(): void {
		// Priority 5: inject event dispatcher before any other prevent_prompt filter.
		add_filter( 'wp_ai_client_prevent_prompt', [ $this, 'ensure_event_dispatcher' ], 5, 2 );
		// Priority 10: evaluate policy.
		add_filter( 'wp_ai_client_prevent_prompt', [ $this, 'maybe_prevent' ], 10, 2 );
		add_action( 'wp_ai_client_before_generate_result', [ $this, 'on_before_generate' ], 10, 1 );
		add_action( 'wp_ai_client_after_generate_result', [ $this, 'on_after_generate' ], 10, 1 );
	}

	/* ------------------------------------------------------------------
	 * 0. Fix WP 7 core bug — inject event dispatcher into PromptBuilder
	 *
	 * wp_ai_client_prompt() creates WP_AI_Client_Prompt_Builder which
	 * constructs the SDK PromptBuilder WITHOUT passing the event dispatcher.
	 * The prevent_prompt filter receives a clone that shares the same inner
	 * PromptBuilder, so we inject the dispatcher via reflection here.
	 * ----------------------------------------------------------------*/

	public function ensure_event_dispatcher( bool $prevent, WP_AI_Client_Prompt_Builder $builder ): bool {
		try {
			$wp_ref      = new \ReflectionClass( $builder );
			$builder_prop = $wp_ref->getProperty( 'builder' );
			$sdk_builder = $builder_prop->getValue( $builder );

			$sdk_ref     = new \ReflectionClass( $sdk_builder );
			$disp_prop   = $sdk_ref->getProperty( 'eventDispatcher' );
			$current     = $disp_prop->getValue( $sdk_builder );

			if ( null === $current ) {
				$dispatcher = AiClient::getEventDispatcher();
				if ( null !== $dispatcher ) {
					$disp_prop->setValue( $sdk_builder, $dispatcher );
				}
			}
		} catch ( \Throwable ) {
			// Reflection failed — don't block the request.
		}

		return $prevent;
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
	 * 2. Before generate — capture attribution
	 * ----------------------------------------------------------------*/

	public function on_before_generate( BeforeGenerateResultEvent $event ): void {
		// Ensure we have attribution even if prevent_prompt wasn't called
		// (e.g. is_supported checks bypass prevent_prompt but generate still fires).
		if ( '' === self::$current_plugin_slug ) {
			CallerDetector::reset();
			self::$current_plugin_slug = CallerDetector::detect();
			self::$current_context     = CallerDetector::context();
		}
	}

	/* ------------------------------------------------------------------
	 * 3. After generate — log token usage
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
		$context     = self::$current_context ?: CallerDetector::context();

		// Write to the log table.
		$repo = new LogRepository();
		$repo->insert( [
			'plugin_slug'       => $plugin_slug,
			'provider_id'       => $provider_id,
			'model_id'          => $model_id,
			'capability'        => $capability,
			'context'           => $context,
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'total_tokens'      => $total_tokens,
			'status'            => 'allowed',
		] );

		// Update rolling counters.
		$this->usage_tracker->record( $plugin_slug, $total_tokens, $provider_id );

		// Clear correlation state for the next request.
		self::$current_plugin_slug = '';
		self::$current_context     = '';
		CallerDetector::reset();
	}
}
