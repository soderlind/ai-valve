<?php

declare(strict_types=1);

namespace AIValve;

use AIValve\Admin\AdminPage;
use AIValve\Alert\AlertManager;
use AIValve\Interceptor\RequestInterceptor;
use AIValve\REST\UsageController;
use AIValve\Settings\Settings;
use AIValve\Tracking\UsageTracker;

/**
 * Central orchestrator — registers all hooks for the plugin.
 */
final class Plugin {

	public function register(): void {
		// Bail early if AI support is absent (pre-WP 7).
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return;
		}

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
	}
}
