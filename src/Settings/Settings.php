<?php
/**
 * Plugin settings storage and sanitization.
 *
 * @package Soderlind\AiValve
 */

declare(strict_types=1);

namespace Soderlind\AiValve\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin option array stored in soderlind_aivalve_settings.
 */
final class Settings {

	private const OPTION_KEY         = 'soderlind_aivalve_settings';
	private const LEGACY_OPTION_KEYS = array(
		'aivalve_settings',
		'ai_valve_settings',
	);

	/**
	 * Cached settings for the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Get legacy option keys for migration.
	 *
	 * @return list<string>
	 */
	private static function legacy_option_keys(): array {
		return self::LEGACY_OPTION_KEYS;
	}

	/**
	 * Get default plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'              => true,
			'abilities_guard_enabled' => false,
			'plugin_policies'      => array(),
			'default_policy'       => 'allow',
			'allow_admin'          => true,
			'allow_frontend'       => true,
			'allow_cron'           => true,
			'allow_rest'           => true,
			'allow_ajax'           => true,
			'allow_cli'            => true,
			'global_daily_limit'   => 0,
			'global_monthly_limit' => 0,
			'plugin_budgets'       => array(),
			'alert_threshold_pct'  => 80,
			'alert_email'          => '',
			'log_retention_days'   => 0,
		);
	}

	/**
	 * Returns the full merged settings array.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null === $this->cache ) {
			$stored = get_option( self::OPTION_KEY, null );
			if ( ! is_array( $stored ) ) {
				foreach ( self::legacy_option_keys() as $legacy_key ) {
					$legacy = get_option( $legacy_key, null );
					if ( is_array( $legacy ) ) {
						update_option( self::OPTION_KEY, $legacy, false );
						$stored = $legacy;
						break;
					}
				}
			}
			$this->cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return $this->cache;
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed Setting value.
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * Check whether AI Valve is enabled.
	 *
	 * @return bool Whether the plugin is enabled.
	 */
	public function is_enabled(): bool {
		return (bool) $this->get( 'enabled', true );
	}

	/**
	 * Returns the policy for a given plugin slug: allow or deny.
	 *
	 * @param string $slug Plugin slug.
	 * @return string Policy name.
	 */
	public function plugin_policy( string $slug ): string {
		$policies = $this->get( 'plugin_policies', array() );
		if ( is_array( $policies ) && isset( $policies[ $slug ] ) ) {
			return $policies[ $slug ];
		}
		return (string) $this->get( 'default_policy', 'allow' );
	}

	/**
	 * Returns per-plugin daily budget.
	 *
	 * @param string $slug Plugin slug.
	 * @return int Daily budget, or 0 for unlimited.
	 */
	public function plugin_daily_budget( string $slug ): int {
		$budgets = $this->get( 'plugin_budgets', array() );
		return (int) ( $budgets[ $slug ]['daily'] ?? 0 );
	}

	/**
	 * Returns per-plugin monthly budget.
	 *
	 * @param string $slug Plugin slug.
	 * @return int Monthly budget, or 0 for unlimited.
	 */
	public function plugin_monthly_budget( string $slug ): int {
		$budgets = $this->get( 'plugin_budgets', array() );
		return (int) ( $budgets[ $slug ]['monthly'] ?? 0 );
	}

	/**
	 * Check whether a request context is allowed.
	 *
	 * @param string $context Request context.
	 * @return bool Whether the context is allowed.
	 */
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

	/**
	 * Persist a partial settings update.
	 *
	 * @param array<string, mixed> $values Settings values.
	 * @return bool Whether the option was updated.
	 */
	public function update( array $values ): bool {
		$current = $this->all();
		$merged  = array_merge( $current, $values );
		$result  = update_option( self::OPTION_KEY, $merged, false );
		foreach ( self::legacy_option_keys() as $legacy_key ) {
			delete_option( $legacy_key );
		}

		$this->cache = null;

		return $result;
	}

	/**
	 * Delete the option entirely.
	 *
	 * @return void
	 */
	public static function delete(): void {
		delete_option( self::OPTION_KEY );
		foreach ( self::legacy_option_keys() as $legacy_key ) {
			delete_option( $legacy_key );
		}
	}

	/**
	 * Returns the raw option key name.
	 *
	 * @return string Option key.
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

		$clean['enabled']                 = ! empty( $input['enabled'] );
		$clean['abilities_guard_enabled'] = ! empty( $input['abilities_guard_enabled'] );
		$clean['default_policy'] = in_array( $input['default_policy'] ?? '', array( 'allow', 'deny' ), true )
			? $input['default_policy']
			: 'allow';

		foreach ( array( 'allow_admin', 'allow_frontend', 'allow_cron', 'allow_rest', 'allow_ajax', 'allow_cli' ) as $ctx ) {
			$clean[ $ctx ] = ! empty( $input[ $ctx ] );
		}

		$clean['global_daily_limit']   = absint( $input['global_daily_limit'] ?? 0 );
		$clean['global_monthly_limit'] = absint( $input['global_monthly_limit'] ?? 0 );

		$pct                          = (int) ( $input['alert_threshold_pct'] ?? 80 );
		$clean['alert_threshold_pct'] = max( 1, min( 100, $pct ) );
		$clean['alert_email']         = sanitize_email( $input['alert_email'] ?? '' );
		$clean['log_retention_days']  = absint( $input['log_retention_days'] ?? 0 );

		if ( isset( $input['plugin_policies'] ) && is_array( $input['plugin_policies'] ) ) {
			$policies = array();
			foreach ( $input['plugin_policies'] as $slug => $policy ) {
				$slug = sanitize_key( $slug );
				if ( in_array( $policy, array( 'allow', 'deny' ), true ) ) {
					$policies[ $slug ] = $policy;
				}
			}
			$clean['plugin_policies'] = $policies;
		}

		if ( isset( $input['plugin_budgets'] ) && is_array( $input['plugin_budgets'] ) ) {
			$budgets = array();
			foreach ( $input['plugin_budgets'] as $slug => $limits ) {
				$slug             = sanitize_key( $slug );
				$budgets[ $slug ] = array(
					'daily'   => absint( $limits['daily'] ?? 0 ),
					'monthly' => absint( $limits['monthly'] ?? 0 ),
				);
			}
			$clean['plugin_budgets'] = $budgets;
		}

		return $clean;
	}
}
