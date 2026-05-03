<?php

declare(strict_types=1);

namespace AIValve\Interceptor;

use AIValve\Settings\Settings;
use AIValve\Tracking\UsageTracker;

/**
 * Evaluates whether a given AI request should be allowed or denied.
 *
 * Check order:
 * 1. Master switch
 * 2. Plugin-level allow/deny
 * 3. Execution context restriction
 * 4. Budget (daily then monthly, per-plugin then global)
 */
final class PolicyEngine {

	/** @var string Denial reason, empty if allowed. */
	private string $denial_reason = '';

	public function __construct(
		private readonly Settings $settings,
		private readonly UsageTracker $usage_tracker,
	) {}

	/**
	 * Returns true if the request should be ALLOWED.
	 */
	public function evaluate( string $plugin_slug, string $context ): bool {
		$this->denial_reason = '';

		// 1. Master switch.
		if ( ! $this->settings->is_enabled() ) {
			$this->denial_reason = 'ai_valve_disabled';
			return false;
		}

		// 2. Per-plugin policy.
		// Filterable so external code can override the stored policy for a slug.
		$policy = (string) apply_filters(
			'ai_valve_plugin_policy',
			$this->settings->plugin_policy( $plugin_slug ),
			$plugin_slug,
			$context
		);
		if ( 'deny' === $policy ) {
			$this->denial_reason = 'plugin_denied';
			return false;
		}

		// 3. Context restriction.
		if ( ! $this->settings->is_context_allowed( $context ) ) {
			$this->denial_reason = 'context_denied';
			return false;
		}

		// 4a. Per-plugin daily budget.
		$plugin_daily_limit = $this->settings->plugin_daily_budget( $plugin_slug );
		if ( $plugin_daily_limit > 0 ) {
			$used = $this->usage_tracker->plugin_tokens_today( $plugin_slug );
			if ( $used >= $plugin_daily_limit ) {
				$this->denial_reason = 'plugin_daily_budget_exceeded';
				return false;
			}
		}

		// 4b. Per-plugin monthly budget.
		$plugin_monthly_limit = $this->settings->plugin_monthly_budget( $plugin_slug );
		if ( $plugin_monthly_limit > 0 ) {
			$used = $this->usage_tracker->plugin_tokens_this_month( $plugin_slug );
			if ( $used >= $plugin_monthly_limit ) {
				$this->denial_reason = 'plugin_monthly_budget_exceeded';
				return false;
			}
		}

		// 4c. Global daily budget.
		$global_daily = (int) $this->settings->get( 'global_daily_limit', 0 );
		if ( $global_daily > 0 ) {
			$used = $this->usage_tracker->global_tokens_today();
			if ( $used >= $global_daily ) {
				$this->denial_reason = 'global_daily_budget_exceeded';
				return false;
			}
		}

		// 4d. Global monthly budget.
		$global_monthly = (int) $this->settings->get( 'global_monthly_limit', 0 );
		if ( $global_monthly > 0 ) {
			$used = $this->usage_tracker->global_tokens_this_month();
			if ( $used >= $global_monthly ) {
				$this->denial_reason = 'global_monthly_budget_exceeded';
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns the reason the last `evaluate()` call denied the request,
	 * or an empty string if it was allowed.
	 */
	public function denial_reason(): string {
		return $this->denial_reason;
	}
}
