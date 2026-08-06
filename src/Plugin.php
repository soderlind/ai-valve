<?php

declare(strict_types=1);

namespace Soderlind\AiValve;

use Soderlind\AiValve\Admin\AdminPage;
use Soderlind\AiValve\Alert\AlertManager;
use Soderlind\AiValve\Interceptor\AbilityInterceptor;
use Soderlind\AiValve\Interceptor\RequestInterceptor;
use Soderlind\AiValve\REST\UsageController;
use Soderlind\AiValve\Settings\Settings;
use Soderlind\AiValve\Tracking\LogRepository;
use Soderlind\AiValve\Tracking\UsageTracker;

defined( 'ABSPATH' ) || exit;

/**
 * Central orchestrator — registers all hooks for the plugin.
 */
final class Plugin {

	public function register(): void {
		// Run lightweight migrations before feature bails so in-place updates
		// still move legacy storage keys forward.
		LogRepository::maybe_upgrade();

		$settings = new Settings();
		$settings->all();

		$usage_tracker = new UsageTracker( $settings );

		// Abilities API guard (WP 7.1+) — opt-in, independent of the AI connector.
		if ( $settings->get( 'abilities_guard_enabled', false ) ) {
			if ( function_exists( 'wp_get_ability' ) ) {
				( new AbilityInterceptor( $settings, $usage_tracker ) )->register();
			} elseif ( is_admin() ) {
				add_action( 'admin_notices', array( self::class, 'render_abilities_unsupported_notice' ) );
			}
		}

		// Bail early if AI support is absent (pre-WP 7) or disabled.
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return;
		}
		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return;
		}

		$interceptor   = new RequestInterceptor( $settings, $usage_tracker );
		$alert_manager = new AlertManager( $settings, $usage_tracker );

		// Core interception hooks.
		$interceptor->register();

		// Admin UI (only when in admin context).
		if ( is_admin() ) {
			( new AdminPage( $settings, $usage_tracker ) )->register();
			$alert_manager->register();
		}

		// REST API endpoints.
		add_action(
			'rest_api_init',
			static function () use ( $settings, $usage_tracker ): void {
				( new UsageController( $settings, $usage_tracker ) )->register_routes();
			}
		);

		// Log retention cron.
		add_action(
			'soderlind_aivalve_log_retention',
			static function () use ( $settings ): void {
				$days = (int) $settings->get( 'log_retention_days', 0 );
				if ( $days > 0 ) {
					( new LogRepository() )->delete_older_than( $days );
				}
			}
		);

		if ( ! wp_next_scheduled( 'soderlind_aivalve_log_retention' ) ) {
			wp_schedule_event( time(), 'daily', 'soderlind_aivalve_log_retention' );
		}

		wp_clear_scheduled_hook( 'aivalve_log_retention' );
		wp_clear_scheduled_hook( 'ai_valve_log_retention' );
	}

	/**
	 * Warn when the Abilities API guard is enabled but the site predates WordPress 7.1.
	 *
	 * @internal Hooked to admin_notices.
	 */
	public static function render_abilities_unsupported_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__(
				'AI Valve: the Abilities API guard is enabled, but this WordPress version does not provide the Abilities API (requires WordPress 7.1+). The guard is inactive.',
				'ai-valve'
			)
		);
	}
}
