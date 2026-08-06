<?php

declare(strict_types=1);

namespace Soderlind\AiValve\Interceptor;

use Soderlind\AiValve\Settings\Settings;
use Soderlind\AiValve\Tracking\LogRepository;
use Soderlind\AiValve\Tracking\UsageTracker;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Governs the WordPress 7.1 Abilities API execution lifecycle, reusing the same
 * PolicyEngine that gates AI connector prompts.
 *
 * Wires two hooks (opt-in via the abilities_guard_enabled setting):
 *
 * 1. `wp_pre_execute_ability` — deny before validation/permission/callback run.
 * 2. `wp_ability_invoked`      — fire a telemetry action for every invocation.
 *
 * The ability namespace (the part before the slash in `namespace/ability-name`)
 * is treated as the policy subject, so existing per-plugin allow/deny rules and
 * the `aivalve_plugin_policy` filter apply to ability namespaces too.
 */
final class AbilityInterceptor {

	public function __construct(
		private readonly Settings $settings,
		private readonly UsageTracker $usage_tracker,
	) {}

	public function register(): void {
		add_filter( 'wp_pre_execute_ability', array( $this, 'maybe_prevent_ability' ), 10, 4 );
		add_action( 'wp_ability_invoked', array( $this, 'on_ability_invoked' ), 10, 3 );
	}

	/*
	------------------------------------------------------------------
	 * 1. Gate — wp_pre_execute_ability
	 * ----------------------------------------------------------------*/

	/**
	 * Short-circuit ability execution when policy denies the namespace.
	 *
	 * @param mixed  $pre          Sentinel/precomputed result. Returned unchanged to continue execution.
	 * @param string $ability_name Ability name in `namespace/ability-name` form.
	 * @param mixed  $input        Raw input passed to execute() (never logged here).
	 * @param mixed  $ability      WP_Ability instance.
	 * @return mixed WP_Error to deny, or $pre unchanged to continue.
	 */
	public function maybe_prevent_ability( mixed $pre, string $ability_name, mixed $input = null, mixed $ability = null ): mixed {
		unset( $input, $ability );

		$namespace = self::namespace_of( $ability_name );
		$context   = CallerDetector::context();

		$engine = new PolicyEngine( $this->settings, $this->usage_tracker );
		if ( $engine->evaluate( $namespace, $context ) ) {
			return $pre;
		}

		$reason = $engine->denial_reason();

		( new LogRepository() )->insert(
			array(
				'plugin_slug' => $namespace,
				'provider_id' => 'wp-abilities',
				'model_id'    => '',
				'capability'  => $ability_name,
				'context'     => $context,
				'status'      => 'denied:' . $reason,
			)
		);

		/**
		 * Fires when AI Valve blocks an ability invocation.
		 *
		 * @param string $ability_name Ability name in `namespace/ability-name` form.
		 * @param string $namespace    Ability namespace used as the policy subject.
		 * @param string $context      Execution context: admin|frontend|cron|rest|ajax|cli.
		 * @param string $reason       Denial reason code.
		 */
		do_action( 'aivalve_ability_denied', $ability_name, $namespace, $context, $reason );
		// Deprecated alias (since 1.3.0).
		do_action_deprecated( 'soderlind_aivalve_ability_denied', array( $ability_name, $namespace, $context, $reason ), '1.3.0', 'aivalve_ability_denied' );

		return new WP_Error(
			'ability_prevented',
			sprintf(
				/* translators: 1: ability name, 2: denial reason code. */
				__( 'AI Valve blocked ability "%1$s": %2$s', 'ai-valve' ),
				$ability_name,
				$reason
			),
			array( 'status' => 403 )
		);
	}

	/*
	------------------------------------------------------------------
	 * 2. Telemetry — wp_ability_invoked
	 * ----------------------------------------------------------------*/

	/**
	 * Fires a telemetry action for every ability invocation. Raw input is not
	 * forwarded because it may contain credentials or personal data.
	 *
	 * @param string $ability_name Ability name in `namespace/ability-name` form.
	 * @param mixed  $input        Raw, unnormalized input (intentionally ignored).
	 * @param mixed  $ability      WP_Ability instance.
	 */
	public function on_ability_invoked( string $ability_name, mixed $input = null, mixed $ability = null ): void {
		unset( $input, $ability );

		$namespace = self::namespace_of( $ability_name );
		$context   = CallerDetector::context();

		/**
		 * Fires for every ability invocation, before validation or permission checks.
		 *
		 * @param string $ability_name Ability name in `namespace/ability-name` form.
		 * @param string $namespace    Ability namespace.
		 * @param string $context      Execution context: admin|frontend|cron|rest|ajax|cli.
		 */
		do_action( 'aivalve_ability_invoked', $ability_name, $namespace, $context );
		// Deprecated alias (since 1.3.0).
		do_action_deprecated( 'soderlind_aivalve_ability_invoked', array( $ability_name, $namespace, $context ), '1.3.0', 'aivalve_ability_invoked' );
	}

	/**
	 * Extract the namespace portion of an ability name.
	 */
	private static function namespace_of( string $ability_name ): string {
		$pos = strpos( $ability_name, '/' );
		if ( false === $pos || 0 === $pos ) {
			return 'unknown';
		}
		return sanitize_key( substr( $ability_name, 0, $pos ) );
	}
}
