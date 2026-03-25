<?php

declare(strict_types=1);

namespace AIValve\Admin;

use AIValve\Settings\Settings;
use AIValve\Tracking\LogRepository;
use AIValve\Tracking\UsageTracker;

/**
 * Registers the Settings → AI Valve admin page.
 *
 * The page is a React SPA rendered into #ai-valve-root.
 * Data flows via the REST API (UsageController).
 * CSV export remains a server-side admin-post action.
 */
final class AdminPage {

	private const SLUG = 'ai-valve';

	public function __construct(
		private readonly Settings $settings,
		private readonly UsageTracker $usage_tracker,
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_ai_valve_export_csv', [ $this, 'handle_csv_export' ] );
	}

	/* ------------------------------------------------------------------
	 * Menu
	 * ----------------------------------------------------------------*/

	public function add_menu_page(): void {
		add_options_page(
			__( 'AI Valve — AI Usage Control', 'ai-valve' ),
			__( 'AI Valve', 'ai-valve' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render_page' ],
		);
	}

	/* ------------------------------------------------------------------
	 * Asset enqueue (React app)
	 * ----------------------------------------------------------------*/

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = AI_VALVE_DIR . '/build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'ai-valve-admin',
			plugins_url( 'build/index.js', AI_VALVE_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'ai-valve-admin',
			plugins_url( 'build/index.css', AI_VALVE_FILE ),
			[ 'wp-components' ],
			$asset['version']
		);

		wp_localize_script( 'ai-valve-admin', 'aiValve', [
			'adminEmail'   => get_option( 'admin_email' ),
			'csvNonce'     => wp_create_nonce( 'ai_valve_export_csv' ),
			'adminPostUrl' => admin_url( 'admin-post.php' ),
		] );
	}

	/* ------------------------------------------------------------------
	 * Page renderer — React root container
	 * ----------------------------------------------------------------*/

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div id="ai-valve-root"></div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * CSV export (admin-post action, kept server-side)
	 * ----------------------------------------------------------------*/

	public function handle_csv_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'ai-valve' ), 403 );
		}

		check_admin_referer( 'ai_valve_export_csv' );

		$repo    = new LogRepository();
		$filters = [
			'plugin_slug' => sanitize_key( $_GET['filter_plugin'] ?? '' ),
			'provider_id' => sanitize_key( $_GET['filter_provider'] ?? '' ),
			'model_id'    => sanitize_text_field( $_GET['filter_model'] ?? '' ),
			'context'     => sanitize_key( $_GET['filter_context'] ?? '' ),
			'status'      => sanitize_text_field( $_GET['filter_status'] ?? '' ),
			'per_page'    => 10000,
			'page'        => 1,
		];

		$date_from = sanitize_text_field( $_GET['filter_date_from'] ?? '' );
		$date_to   = sanitize_text_field( $_GET['filter_date_to'] ?? '' );
		if ( $date_from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$filters['date_from'] = $date_from . ' 00:00:00';
		}
		if ( $date_to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$filters['date_to'] = $date_to . ' 23:59:59';
		}

		$result = $repo->query( $filters );

		$filename = 'ai-valve-logs-' . gmdate( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, [ 'Time', 'Plugin', 'Provider', 'Model', 'Capability', 'Context', 'Prompt Tokens', 'Completion Tokens', 'Total Tokens', 'Duration (ms)', 'Status' ] );

		foreach ( $result['items'] as $row ) {
			fputcsv( $output, [
				$row->created_at ?? '',
				$row->plugin_slug ?? '',
				$row->provider_id ?? '',
				$row->model_id ?? '',
				$row->capability ?? '',
				$row->context ?? '',
				$row->prompt_tokens ?? 0,
				$row->completion_tokens ?? 0,
				$row->total_tokens ?? 0,
				$row->duration_ms ?? 0,
				$row->status ?? '',
			] );
		}

		fclose( $output );
		exit;
	}
}
