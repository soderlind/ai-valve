<?php

declare(strict_types=1);

namespace AIValve;

use AIValve\Admin\AdminPage;
use AIValve\Alert\AlertManager;
use AIValve\Interceptor\RequestInterceptor;
use AIValve\REST\UsageController;
use AIValve\Settings\Settings;
use AIValve\Tracking\LogRepository;
use AIValve\Tracking\UsageTracker;

/**
 * Central orchestrator — registers all hooks for the plugin.
 */
final class Plugin {

	public function register(): void {
		// Bail early if AI support is absent (pre-WP 7) or disabled.
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return;
		}
		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return;
		}

		// Run schema migrations on version bump (activation hook only
		// fires on activate, not on in-place file updates).
		LogRepository::maybe_upgrade();

		$settings      = new Settings();
		$usage_tracker = new UsageTracker( $settings );
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
		add_action( 'rest_api_init', static function () use ( $settings, $usage_tracker ): void {
			( new UsageController( $settings, $usage_tracker ) )->register_routes();
		} );

		// Log retention cron.
		add_action( 'ai_valve_log_retention', static function () use ( $settings ): void {
			$days = (int) $settings->get( 'log_retention_days', 0 );
			if ( $days > 0 ) {
				( new LogRepository() )->delete_older_than( $days );
			}
		} );

		if ( ! wp_next_scheduled( 'ai_valve_log_retention' ) ) {
			wp_schedule_event( time(), 'daily', 'ai_valve_log_retention' );
		}
	}
}
