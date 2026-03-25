<?php

declare(strict_types=1);

namespace AIValve\REST;

use AIValve\Settings\Settings;
use AIValve\Tracking\LogRepository;
use AIValve\Tracking\UsageTracker;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API endpoints for the AI Valve dashboard.
 *
 *   GET  /ai-valve/v1/usage    — usage summary
 *   GET  /ai-valve/v1/logs     — paginated log entries
 *   POST /ai-valve/v1/settings — update settings
 */
final class UsageController extends WP_REST_Controller {

	protected $namespace = 'ai-valve/v1';

	public function __construct(
		private readonly Settings $settings,
		private readonly UsageTracker $usage_tracker,
	) {}

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/usage', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_usage' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );

		register_rest_route( $this->namespace, '/logs', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_logs' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => [
					'page'          => [
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					],
					'per_page'      => [
						'type'              => 'integer',
						'default'           => 20,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					],
					'plugin_slug'   => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'provider_id'   => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'model_id'      => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'context'       => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'status'        => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'date_from'     => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'date_to'       => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'purge_logs' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );

		register_rest_route( $this->namespace, '/logs/filters', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_log_filters' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );

		register_rest_route( $this->namespace, '/settings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => [
					'settings' => [
						'type'     => 'object',
						'required' => true,
					],
				],
			],
		] );
	}

	/* ------------------------------------------------------------------
	 * Permission check
	 * ----------------------------------------------------------------*/

	public function check_permissions(): bool|WP_Error {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to access AI Valve data.', 'ai-valve' ),
			[ 'status' => 403 ]
		);
	}

	/* ------------------------------------------------------------------
	 * GET /usage
	 * ----------------------------------------------------------------*/

	public function get_usage( WP_REST_Request $request ): WP_REST_Response {
		$repo  = new LogRepository();
		$today = gmdate( 'Y-m-d' );
		$month = gmdate( 'Y-m' );

		$month_from = $month . '-01 00:00:00';
		$today_end  = $today . ' 23:59:59';

		// Known plugin slugs from log table.
		global $wpdb;
		$table       = LogRepository::table_name();
		$known_slugs = $wpdb->get_col( "SELECT DISTINCT plugin_slug FROM {$table} ORDER BY plugin_slug" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return rest_ensure_response( [
			'daily'             => $repo->totals( $today . ' 00:00:00', $today_end ),
			'monthly'           => $repo->totals( $month_from, $today_end ),
			'by_plugin'         => $repo->totals_by_plugin( $month_from, $today_end ),
			'by_provider'       => $repo->totals_by_provider( $month_from, $today_end ),
			'by_provider_model' => $repo->totals_by_provider_model( $month_from, $today_end ),
			'by_context'        => $repo->totals_by_context( $month_from, $today_end ),
			'recent'            => $repo->query( [ 'per_page' => 10 ] )['items'],
			'known_slugs'       => $known_slugs ?: [],
			'budgets'           => [
				'global_daily_limit'   => (int) $this->settings->get( 'global_daily_limit', 0 ),
				'global_monthly_limit' => (int) $this->settings->get( 'global_monthly_limit', 0 ),
				'global_daily_used'    => $this->usage_tracker->global_tokens_today(),
				'global_monthly_used'  => $this->usage_tracker->global_tokens_this_month(),
			],
		] );
	}

	/* ------------------------------------------------------------------
	 * GET /logs
	 * ----------------------------------------------------------------*/

	public function get_logs( WP_REST_Request $request ): WP_REST_Response {
		$repo   = new LogRepository();
		$result = $repo->query( $request->get_params() );

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) ceil( $result['total'] / max( 1, (int) $request->get_param( 'per_page' ) ) ) );

		return $response;
	}

	/* ------------------------------------------------------------------
	 * GET /logs/filters — distinct filter values
	 * ----------------------------------------------------------------*/

	public function get_log_filters( WP_REST_Request $request ): WP_REST_Response {
		$repo = new LogRepository();
		return rest_ensure_response( $repo->distinct_filter_values() );
	}

	/* ------------------------------------------------------------------
	 * GET /settings
	 * ----------------------------------------------------------------*/

	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->settings->all() );
	}

	/* ------------------------------------------------------------------
	 * POST /settings
	 * ----------------------------------------------------------------*/

	public function update_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$input     = $request->get_param( 'settings' );
		$sanitized = Settings::sanitize( $input );
		$this->settings->update( $sanitized );

		return rest_ensure_response( [
			'updated'  => true,
			'settings' => $sanitized,
		] );
	}

	/* ------------------------------------------------------------------
	 * DELETE /logs
	 * ----------------------------------------------------------------*/

	public function purge_logs( WP_REST_Request $request ): WP_REST_Response {
		$repo = new LogRepository();
		$repo->purge();

		return rest_ensure_response( [ 'purged' => true ] );
	}
}
