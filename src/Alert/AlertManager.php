<?php

declare(strict_types=1);

namespace AIValve\Alert;

use AIValve\Settings\Settings;
use AIValve\Tracking\UsageTracker;

/**
 * Shows admin notices when token usage approaches / exceeds budget thresholds.
 * Optionally sends a one-time email alert per day when the budget is exceeded.
 */
final class AlertManager {

	private const EMAIL_TRANSIENT_PREFIX = 'ai_valve_alert_sent_';

	public function __construct(
		private readonly Settings $settings,
		private readonly UsageTracker $usage_tracker,
	) {}

	public function register(): void {
		add_action( 'admin_notices', [ $this, 'maybe_show_notice' ] );
		add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
	}

	/* ------------------------------------------------------------------
	 * Admin notices
	 * ----------------------------------------------------------------*/

	public function maybe_show_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		$threshold = (int) $this->settings->get( 'alert_threshold_pct', 80 );
		$messages  = [];

		// Check global daily.
		$daily_pct = $this->usage_tracker->global_daily_pct();
		if ( $daily_pct >= 100 ) {
			$messages[] = sprintf(
				/* translators: %s: percentage */
				__( 'AI Valve: Global daily token budget exceeded (%s%% used). New AI requests are being blocked.', 'ai-valve' ),
				number_format_i18n( (int) $daily_pct )
			);
			$this->maybe_send_email( 'daily', $daily_pct );
		} elseif ( $daily_pct >= $threshold ) {
			$messages[] = sprintf(
				/* translators: %s: percentage */
				__( 'AI Valve: Global daily token usage at %s%% of budget.', 'ai-valve' ),
				number_format_i18n( (int) $daily_pct )
			);
		}

		// Check global monthly.
		$monthly_pct = $this->usage_tracker->global_monthly_pct();
		if ( $monthly_pct >= 100 ) {
			$messages[] = sprintf(
				/* translators: %s: percentage */
				__( 'AI Valve: Global monthly token budget exceeded (%s%% used). New AI requests are being blocked.', 'ai-valve' ),
				number_format_i18n( (int) $monthly_pct )
			);
			$this->maybe_send_email( 'monthly', $monthly_pct );
		} elseif ( $monthly_pct >= $threshold ) {
			$messages[] = sprintf(
				/* translators: %s: percentage */
				__( 'AI Valve: Global monthly token usage at %s%% of budget.', 'ai-valve' ),
				number_format_i18n( (int) $monthly_pct )
			);
		}

		// Check per-plugin budgets.
		$plugin_budgets = (array) $this->settings->get( 'plugin_budgets', [] );
		foreach ( $plugin_budgets as $slug => $limits ) {
			$slug = (string) $slug;
			$p_daily_pct = $this->usage_tracker->plugin_daily_pct( $slug );
			if ( $p_daily_pct >= 100 ) {
				$messages[] = sprintf(
					/* translators: 1: plugin slug 2: percentage */
					__( 'AI Valve: %1$s daily token budget exceeded (%2$s%% used).', 'ai-valve' ),
					'<strong>' . esc_html( $slug ) . '</strong>',
					number_format_i18n( (int) $p_daily_pct )
				);
			} elseif ( $p_daily_pct >= $threshold ) {
				$messages[] = sprintf(
					/* translators: 1: plugin slug 2: percentage */
					__( 'AI Valve: %1$s daily token usage at %2$s%% of budget.', 'ai-valve' ),
					'<strong>' . esc_html( $slug ) . '</strong>',
					number_format_i18n( (int) $p_daily_pct )
				);
			}

			$p_monthly_pct = $this->usage_tracker->plugin_monthly_pct( $slug );
			if ( $p_monthly_pct >= 100 ) {
				$messages[] = sprintf(
					/* translators: 1: plugin slug 2: percentage */
					__( 'AI Valve: %1$s monthly token budget exceeded (%2$s%% used).', 'ai-valve' ),
					'<strong>' . esc_html( $slug ) . '</strong>',
					number_format_i18n( (int) $p_monthly_pct )
				);
			} elseif ( $p_monthly_pct >= $threshold ) {
				$messages[] = sprintf(
					/* translators: 1: plugin slug 2: percentage */
					__( 'AI Valve: %1$s monthly token usage at %2$s%% of budget.', 'ai-valve' ),
					'<strong>' . esc_html( $slug ) . '</strong>',
					number_format_i18n( (int) $p_monthly_pct )
				);
			}
		}

		foreach ( $messages as $msg ) {
			$type = str_contains( $msg, 'exceeded' ) ? 'error' : 'warning';
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $type ),
				wp_kses( $msg, [ 'strong' => [] ] )
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Dashboard widget
	 * ----------------------------------------------------------------*/

	public function register_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'ai_valve_usage_widget',
			__( 'AI Valve — AI Token Usage', 'ai-valve' ),
			[ $this, 'render_dashboard_widget' ],
		);
	}

	public function render_dashboard_widget(): void {
		$daily   = $this->usage_tracker->global_tokens_today();
		$monthly = $this->usage_tracker->global_tokens_this_month();

		$daily_limit   = (int) $this->settings->get( 'global_daily_limit', 0 );
		$monthly_limit = (int) $this->settings->get( 'global_monthly_limit', 0 );

		echo '<table class="widefat" style="border:0;">';

		// Daily row.
		echo '<tr><th>' . esc_html__( 'Today', 'ai-valve' ) . '</th><td>';
		echo esc_html( number_format_i18n( $daily ) );
		if ( $daily_limit > 0 ) {
			echo ' / ' . esc_html( number_format_i18n( $daily_limit ) );
			$this->render_progress_bar( $daily, $daily_limit );
		}
		echo '</td></tr>';

		// Monthly row.
		echo '<tr><th>' . esc_html__( 'This month', 'ai-valve' ) . '</th><td>';
		echo esc_html( number_format_i18n( $monthly ) );
		if ( $monthly_limit > 0 ) {
			echo ' / ' . esc_html( number_format_i18n( $monthly_limit ) );
			$this->render_progress_bar( $monthly, $monthly_limit );
		}
		echo '</td></tr>';

		echo '</table>';

		printf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'options-general.php?page=ai-valve' ) ),
			esc_html__( 'View full dashboard →', 'ai-valve' )
		);
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------*/

	private function render_progress_bar( int $used, int $limit ): void {
		$pct   = min( 100, (int) ( ( $used / max( 1, $limit ) ) * 100 ) );
		$color = match ( true ) {
			$pct >= 100 => '#d63638',
			$pct >= 80  => '#dba617',
			default     => '#00a32a',
		};

		printf(
			'<div style="background:#f0f0f1;border-radius:3px;height:12px;margin-top:4px;overflow:hidden;">'
			. '<div style="background:%s;height:100%%;width:%d%%;transition:width 0.3s;"></div>'
			. '</div>',
			esc_attr( $color ),
			$pct
		);
	}

	/**
	 * Sends a one-time email per calendar day per period type.
	 */
	private function maybe_send_email( string $period, float $pct ): void {
		$email = $this->settings->get( 'alert_email', '' );
		if ( empty( $email ) || ! is_email( $email ) ) {
			return;
		}

		$transient_key = self::EMAIL_TRANSIENT_PREFIX . $period . '_' . gmdate( 'Y-m-d' );
		if ( get_transient( $transient_key ) ) {
			return; // Already sent today.
		}

		$subject = sprintf(
			/* translators: 1: period (daily/monthly), 2: percentage */
			__( '[AI Valve] %1$s token budget exceeded (%2$s%%)', 'ai-valve' ),
			ucfirst( $period ),
			number_format_i18n( (int) $pct )
		);

		$body = sprintf(
			/* translators: 1: site name, 2: period, 3: percentage, 4: dashboard URL */
			__( "Site: %1\$s\nPeriod: %2\$s\nUsage: %3\$s%% of budget\n\nView dashboard: %4\$s", 'ai-valve' ),
			get_bloginfo( 'name' ),
			$period,
			number_format_i18n( (int) $pct ),
			admin_url( 'options-general.php?page=ai-valve' )
		);

		wp_mail( $email, $subject, $body );
		set_transient( $transient_key, 1, DAY_IN_SECONDS );
	}
}
