<?php

declare(strict_types=1);

namespace AIValve\Settings;

/**
 * Reads / writes the plugin option array stored in `ai_valve_settings`.
 */
final class Settings {

	private const OPTION_KEY = 'ai_valve_settings';

	/**
	 * Cached settings for the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/* ------------------------------------------------------------------
	 * Defaults
	 * ----------------------------------------------------------------*/

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			// Master switch.
			'enabled'              => true,

			// Per-plugin policy: slug => 'allow' | 'deny'.
			'plugin_policies'      => [],

			// Default policy for plugins not explicitly listed.
			'default_policy'       => 'allow',

			// Context restrictions — which execution contexts are allowed.
			'allow_admin'          => true,
			'allow_frontend'       => true,
			'allow_cron'           => true,
			'allow_rest'           => true,
			'allow_ajax'           => true,
			'allow_cli'            => true,

			// Global token budgets (0 = unlimited).
			'global_daily_limit'   => 0,
			'global_monthly_limit' => 0,

			// Per-plugin token budgets: slug => ['daily' => int, 'monthly' => int].
			'plugin_budgets'       => [],

			// Alert threshold — percentage of budget that triggers a warning.
			'alert_threshold_pct'  => 80,

			// Email notification on budget exceeded.
			'alert_email'          => '',

			// Log retention in days (0 = keep forever).
			'log_retention_days'   => 0,
		];
	}

	/* ------------------------------------------------------------------
	 * Read
	 * ----------------------------------------------------------------*/

	/**
	 * Returns the full merged settings array.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION_KEY, [] );
			$this->cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : [] );
		}
		return $this->cache;
	}

	/**
	 * Get a single setting value.
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->all();
		return $all[ $key ] ?? $default;
	}

	public function is_enabled(): bool {
		return (bool) $this->get( 'enabled', true );
	}

	/* ------------------------------------------------------------------
	 * Plugin policy helpers
	 * ----------------------------------------------------------------*/

	/**
	 * Returns the policy for a given plugin slug: 'allow' | 'deny'.
	 */
	public function plugin_policy( string $slug ): string {
		$policies = $this->get( 'plugin_policies', [] );
		if ( is_array( $policies ) && isset( $policies[ $slug ] ) ) {
			return $policies[ $slug ];
		}
		return (string) $this->get( 'default_policy', 'allow' );
	}

	/**
	 * Returns per-plugin daily budget (0 = unlimited).
	 */
	public function plugin_daily_budget( string $slug ): int {
		$budgets = $this->get( 'plugin_budgets', [] );
		return (int) ( $budgets[ $slug ]['daily'] ?? 0 );
	}

	/**
	 * Returns per-plugin monthly budget (0 = unlimited).
	 */
	public function plugin_monthly_budget( string $slug ): int {
		$budgets = $this->get( 'plugin_budgets', [] );
		return (int) ( $budgets[ $slug ]['monthly'] ?? 0 );
	}

	/* ------------------------------------------------------------------
	 * Context helpers
	 * ----------------------------------------------------------------*/

	public function is_context_allowed( string $context ): bool {
		return match ( $context ) {
			'admin'    => (bool) $this->get( 'allow_admin', true ),
			'frontend' => (bool) $this->get( 'allow_frontend', true ),
			'cron'     => (bool) $this->get( 'allow_cron', true ),
			'rest'     => (bool) $this->get( 'allow_rest', true ),
			'ajax'     => (bool) $this->get( 'allow_ajax', true ),
			'cli'      => (bool) $this->get( 'allow_cli', true ),
			default    => true,
		};
	}

	/* ------------------------------------------------------------------
	 * Write
	 * ----------------------------------------------------------------*/

	/**
	 * Persist a partial settings update (merges with existing).
	 *
	 * @param array<string, mixed> $values
	 */
	public function update( array $values ): bool {
		$current = $this->all();
		$merged  = array_merge( $current, $values );
		$result  = update_option( self::OPTION_KEY, $merged, false );

		// Bust cache so the next read reflects the change.
		$this->cache = null;

		return $result;
	}

	/**
	 * Delete the option entirely (used during uninstall).
	 */
	public static function delete(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Returns the raw option key name (for register_setting, etc.).
	 */
	public static function option_key(): string {
		return self::OPTION_KEY;
	}

	/**
	 * Sanitize callback for register_setting().
	 *
	 * @param mixed $input Raw form input.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public static function sanitize( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return self::defaults();
		}

		$clean = self::defaults();

		$clean['enabled']              = ! empty( $input['enabled'] );
		$clean['default_policy']       = in_array( $input['default_policy'] ?? '', [ 'allow', 'deny' ], true )
			? $input['default_policy']
			: 'allow';

		// Context toggles.
		foreach ( [ 'allow_admin', 'allow_frontend', 'allow_cron', 'allow_rest', 'allow_ajax', 'allow_cli' ] as $ctx ) {
			$clean[ $ctx ] = ! empty( $input[ $ctx ] );
		}

		// Global budgets.
		$clean['global_daily_limit']   = absint( $input['global_daily_limit'] ?? 0 );
		$clean['global_monthly_limit'] = absint( $input['global_monthly_limit'] ?? 0 );

		// Alert threshold.
		$pct = (int) ( $input['alert_threshold_pct'] ?? 80 );
		$clean['alert_threshold_pct']  = max( 1, min( 100, $pct ) );

		// Alert email.
		$clean['alert_email'] = sanitize_email( $input['alert_email'] ?? '' );

		// Log retention.
		$clean['log_retention_days'] = absint( $input['log_retention_days'] ?? 0 );

		// Per-plugin policies.
		if ( isset( $input['plugin_policies'] ) && is_array( $input['plugin_policies'] ) ) {
			$policies = [];
			foreach ( $input['plugin_policies'] as $slug => $policy ) {
				$slug = sanitize_key( $slug );
				if ( in_array( $policy, [ 'allow', 'deny' ], true ) ) {
					$policies[ $slug ] = $policy;
				}
			}
			$clean['plugin_policies'] = $policies;
		}

		// Per-plugin budgets.
		if ( isset( $input['plugin_budgets'] ) && is_array( $input['plugin_budgets'] ) ) {
			$budgets = [];
			foreach ( $input['plugin_budgets'] as $slug => $limits ) {
				$slug = sanitize_key( $slug );
				$budgets[ $slug ] = [
					'daily'   => absint( $limits['daily'] ?? 0 ),
					'monthly' => absint( $limits['monthly'] ?? 0 ),
				];
			}
			$clean['plugin_budgets'] = $budgets;
		}

		return $clean;
	}
}
