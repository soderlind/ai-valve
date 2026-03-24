<?php

declare(strict_types=1);

namespace AIValve\Tracking;

use AIValve\Settings\Settings;

/**
 * Maintains rolling daily/monthly token counters per-plugin and globally.
 *
 * Counters are stored as options with date-keyed names so they auto-expire
 * without requiring cron cleanup. Reads go through the object cache for speed.
 *
 * Option keys:
 *   ai_valve_tokens_daily_{Y-m-d}_{slug}   — per-plugin daily
 *   ai_valve_tokens_monthly_{Y-m}_{slug}    — per-plugin monthly
 *   ai_valve_tokens_daily_{Y-m-d}_*         — global daily
 *   ai_valve_tokens_monthly_{Y-m}_*         — global monthly
 */
final class UsageTracker {

	private const PREFIX = 'ai_valve_tokens_';

	public function __construct(
		private readonly Settings $settings,
	) {}

	/* ------------------------------------------------------------------
	 * Record usage
	 * ----------------------------------------------------------------*/

	/**
	 * Increment both per-plugin and global counters.
	 */
	public function record( string $plugin_slug, int $tokens, string $provider_id = '' ): void {
		if ( $tokens <= 0 ) {
			return;
		}

		$today = gmdate( 'Y-m-d' );
		$month = gmdate( 'Y-m' );

		// Per-plugin daily.
		$this->increment( self::daily_key( $today, $plugin_slug ), $tokens );
		// Per-plugin monthly.
		$this->increment( self::monthly_key( $month, $plugin_slug ), $tokens );
		// Global daily.
		$this->increment( self::daily_key( $today, '*' ), $tokens );
		// Global monthly.
		$this->increment( self::monthly_key( $month, '*' ), $tokens );

		// Per-provider counters.
		if ( '' !== $provider_id ) {
			$this->increment( self::daily_key( $today, 'provider:' . $provider_id ), $tokens );
			$this->increment( self::monthly_key( $month, 'provider:' . $provider_id ), $tokens );
		}
	}

	/* ------------------------------------------------------------------
	 * Read counters
	 * ----------------------------------------------------------------*/

	public function plugin_tokens_today( string $slug ): int {
		return $this->read( self::daily_key( gmdate( 'Y-m-d' ), $slug ) );
	}

	public function plugin_tokens_this_month( string $slug ): int {
		return $this->read( self::monthly_key( gmdate( 'Y-m' ), $slug ) );
	}

	public function global_tokens_today(): int {
		return $this->read( self::daily_key( gmdate( 'Y-m-d' ), '*' ) );
	}

	public function global_tokens_this_month(): int {
		return $this->read( self::monthly_key( gmdate( 'Y-m' ), '*' ) );
	}

	public function provider_tokens_today( string $provider_id ): int {
		return $this->read( self::daily_key( gmdate( 'Y-m-d' ), 'provider:' . $provider_id ) );
	}

	public function provider_tokens_this_month( string $provider_id ): int {
		return $this->read( self::monthly_key( gmdate( 'Y-m' ), 'provider:' . $provider_id ) );
	}

	/* ------------------------------------------------------------------
	 * Budget helpers (for AlertManager)
	 * ----------------------------------------------------------------*/

	/**
	 * Returns the percentage of the global daily budget consumed (0-100+).
	 * Returns 0 if no limit is set.
	 */
	public function global_daily_pct(): float {
		$limit = (int) $this->settings->get( 'global_daily_limit', 0 );
		if ( $limit <= 0 ) {
			return 0.0;
		}
		return ( $this->global_tokens_today() / $limit ) * 100;
	}

	public function global_monthly_pct(): float {
		$limit = (int) $this->settings->get( 'global_monthly_limit', 0 );
		if ( $limit <= 0 ) {
			return 0.0;
		}
		return ( $this->global_tokens_this_month() / $limit ) * 100;
	}

	public function plugin_daily_pct( string $slug ): float {
		$limit = $this->settings->plugin_daily_budget( $slug );
		if ( $limit <= 0 ) {
			return 0.0;
		}
		return ( $this->plugin_tokens_today( $slug ) / $limit ) * 100;
	}

	public function plugin_monthly_pct( string $slug ): float {
		$limit = $this->settings->plugin_monthly_budget( $slug );
		if ( $limit <= 0 ) {
			return 0.0;
		}
		return ( $this->plugin_tokens_this_month( $slug ) / $limit ) * 100;
	}

	/* ------------------------------------------------------------------
	 * Internal helpers
	 * ----------------------------------------------------------------*/

	private static function daily_key( string $date, string $slug ): string {
		return self::PREFIX . 'daily_' . $date . '_' . $slug;
	}

	private static function monthly_key( string $month, string $slug ): string {
		return self::PREFIX . 'monthly_' . $month . '_' . $slug;
	}

	private function increment( string $key, int $amount ): void {
		$current = (int) get_option( $key, 0 );
		update_option( $key, $current + $amount, false );
	}

	private function read( string $key ): int {
		return (int) get_option( $key, 0 );
	}

	/* ------------------------------------------------------------------
	 * Cleanup
	 * ----------------------------------------------------------------*/

	/**
	 * Delete ALL ai_valve_tokens_* options. Used during uninstall.
	 */
	public static function delete_all(): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::PREFIX ) . '%'
			)
		);
	}
}
