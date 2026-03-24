<?php

declare(strict_types=1);

namespace AIValve\Admin;

use AIValve\Settings\Settings;
use AIValve\Tracking\LogRepository;
use AIValve\Tracking\UsageTracker;

/**
 * Registers the Settings → AI Valve admin page with three tabs:
 *   1. Dashboard  — usage overview
 *   2. Settings   — permissions, contexts, budgets
 *   3. Logs       — filterable request log
 */
final class AdminPage {

	private const SLUG = 'ai-valve';

	public function __construct(
		private readonly Settings $settings,
		private readonly UsageTracker $usage_tracker,
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
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
	 * Settings API registration
	 * ----------------------------------------------------------------*/

	public function register_settings(): void {
		register_setting(
			'ai_valve_settings_group',
			Settings::option_key(),
			[
				'type'              => 'array',
				'sanitize_callback' => [ Settings::class, 'sanitize' ],
				'default'           => Settings::defaults(),
			]
		);
	}

	/* ------------------------------------------------------------------
	 * Page renderer — tabs
	 * ----------------------------------------------------------------*/

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab param, read only.
		$tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Valve — AI Usage Control', 'ai-valve' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::SLUG . '&tab=dashboard' ) ); ?>"
				   class="nav-tab <?php echo 'dashboard' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Dashboard', 'ai-valve' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::SLUG . '&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'ai-valve' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::SLUG . '&tab=logs' ) ); ?>"
				   class="nav-tab <?php echo 'logs' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Logs', 'ai-valve' ); ?>
				</a>
			</nav>

			<div class="ai-valve-tab-content" style="margin-top: 1em;">
				<?php
				match ( $tab ) {
					'settings' => $this->render_settings_tab(),
					'logs'     => $this->render_logs_tab(),
					default    => $this->render_dashboard_tab(),
				};
				?>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Dashboard tab
	 * ----------------------------------------------------------------*/

	private function render_dashboard_tab(): void {
		$repo  = new LogRepository();
		$today = gmdate( 'Y-m-d' );
		$month = gmdate( 'Y-m' );

		$daily_totals   = $repo->totals( $today . ' 00:00:00', $today . ' 23:59:59' );
		$monthly_totals = $repo->totals( $month . '-01 00:00:00', $today . ' 23:59:59' );
		$by_plugin      = $repo->totals_by_plugin( $month . '-01 00:00:00', $today . ' 23:59:59' );
		$by_provider    = $repo->totals_by_provider( $month . '-01 00:00:00', $today . ' 23:59:59' );

		$recent = $repo->query( [ 'per_page' => 10 ] );

		$global_daily_limit   = (int) $this->settings->get( 'global_daily_limit', 0 );
		$global_monthly_limit = (int) $this->settings->get( 'global_monthly_limit', 0 );

		$all      = $this->settings->all();
		$policies = (array) ( $all['plugin_policies'] ?? [] );
		$budgets  = (array) ( $all['plugin_budgets'] ?? [] );
		$opt      = Settings::option_key();

		// Merge known slugs from policies, budgets, and recent log entries.
		$known_slugs = array_unique( array_merge(
			array_keys( $policies ),
			array_keys( $budgets ),
			$this->get_known_plugin_slugs(),
		) );
		sort( $known_slugs );

		// Index by_plugin for fast lookup.
		$plugin_usage = [];
		foreach ( $by_plugin as $row ) {
			$plugin_usage[ $row['plugin_slug'] ] = $row;
		}
		?>

		<style>
			.ai-valve-cards { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
			.ai-valve-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; min-width: 220px; flex: 1; }
			.ai-valve-card h3 { margin: 0 0 12px; font-size: 14px; color: #1d2327; }
			.ai-valve-card .ai-valve-big { font-size: 28px; font-weight: 600; line-height: 1.2; color: #1d2327; }
			.ai-valve-card .ai-valve-sub { font-size: 13px; color: #646970; margin-top: 4px; }
			.ai-valve-bar-wrap { background: #f0f0f1; border-radius: 3px; height: 8px; margin-top: 8px; overflow: hidden; }
			.ai-valve-bar { height: 100%; border-radius: 3px; transition: width .3s; }
			.ai-valve-bar--ok { background: #00a32a; }
			.ai-valve-bar--warn { background: #dba617; }
			.ai-valve-bar--over { background: #d63638; }
			.ai-valve-status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
			.ai-valve-status-dot--allow { background: #00a32a; }
			.ai-valve-status-dot--deny { background: #d63638; }
		</style>

		<!-- ===== Summary cards ===== -->
		<div class="ai-valve-cards">
			<div class="ai-valve-card">
				<h3><?php esc_html_e( 'Today', 'ai-valve' ); ?></h3>
				<div class="ai-valve-big"><?php echo esc_html( number_format_i18n( $daily_totals['total_tokens'] ) ); ?></div>
				<div class="ai-valve-sub">
					<?php
					printf(
						/* translators: %s: number of tokens */
						esc_html__( 'tokens across %s requests', 'ai-valve' ),
						esc_html( number_format_i18n( $daily_totals['request_count'] ) )
					);
					?>
				</div>
				<div class="ai-valve-sub">
					<?php
					printf(
						/* translators: 1: prompt token count 2: completion token count */
						esc_html__( '%1$s prompt · %2$s completion', 'ai-valve' ),
						esc_html( number_format_i18n( $daily_totals['prompt_tokens'] ) ),
						esc_html( number_format_i18n( $daily_totals['completion_tokens'] ) )
					);
					?>
				</div>
				<?php $this->render_budget_bar( $daily_totals['total_tokens'], $global_daily_limit, __( 'daily budget', 'ai-valve' ) ); ?>
			</div>

			<div class="ai-valve-card">
				<h3><?php esc_html_e( 'This Month', 'ai-valve' ); ?></h3>
				<div class="ai-valve-big"><?php echo esc_html( number_format_i18n( $monthly_totals['total_tokens'] ) ); ?></div>
				<div class="ai-valve-sub">
					<?php
					printf(
						/* translators: %s: number of requests */
						esc_html__( 'tokens across %s requests', 'ai-valve' ),
						esc_html( number_format_i18n( $monthly_totals['request_count'] ) )
					);
					?>
				</div>
				<?php $this->render_budget_bar( $monthly_totals['total_tokens'], $global_monthly_limit, __( 'monthly budget', 'ai-valve' ) ); ?>
			</div>
		</div>

		<!-- ===== Per-plugin overview (usage + policy + budgets) ===== -->
		<h2><?php esc_html_e( 'Plugins', 'ai-valve' ); ?></h2>
		<p class="description" style="margin-bottom: 12px;">
			<?php esc_html_e( 'Control each plugin\'s access to the AI connector and set token budgets. Plugins appear automatically after their first AI request. Limits are in tokens — set to 0 for unlimited.', 'ai-valve' ); ?>
		</p>

		<?php if ( $known_slugs ) : ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'ai_valve_settings_group' ); ?>
			<?php $this->emit_hidden_settings_fields( $all ); ?>

			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 22%;"><?php esc_html_e( 'Plugin', 'ai-valve' ); ?></th>
						<th style="width: 10%;"><?php esc_html_e( 'Access', 'ai-valve' ); ?></th>
						<th style="width: 14%; text-align: right;"><?php esc_html_e( 'Requests', 'ai-valve' ); ?></th>
						<th style="width: 14%; text-align: right;"><?php esc_html_e( 'Tokens used', 'ai-valve' ); ?></th>
						<th style="width: 20%;">
							<?php esc_html_e( 'Daily token limit', 'ai-valve' ); ?>
						</th>
						<th style="width: 20%;">
							<?php esc_html_e( 'Monthly token limit', 'ai-valve' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $known_slugs as $slug ) :
						$usage      = $plugin_usage[ $slug ] ?? [ 'request_count' => 0, 'total_tokens' => 0 ];
						$policy     = $policies[ $slug ] ?? $all['default_policy'];
						$slug_daily = (int) ( $budgets[ $slug ]['daily'] ?? 0 );
						$slug_month = (int) ( $budgets[ $slug ]['monthly'] ?? 0 );
						?>
					<tr>
						<td>
							<code><?php echo esc_html( $slug ); ?></code>
						</td>
						<td>
							<select name="<?php echo esc_attr( $opt ); ?>[plugin_policies][<?php echo esc_attr( $slug ); ?>]"
									style="width: 100%;">
								<option value="allow" <?php selected( $policy, 'allow' ); ?>>
									<?php esc_html_e( 'Allow', 'ai-valve' ); ?>
								</option>
								<option value="deny" <?php selected( $policy, 'deny' ); ?>>
									<?php esc_html_e( 'Deny', 'ai-valve' ); ?>
								</option>
							</select>
						</td>
						<td style="text-align: right;">
							<?php echo esc_html( number_format_i18n( $usage['request_count'] ) ); ?>
						</td>
						<td style="text-align: right;">
							<?php echo esc_html( number_format_i18n( $usage['total_tokens'] ) ); ?>
						</td>
						<td>
							<input type="number" min="0" step="1"
								   name="<?php echo esc_attr( $opt ); ?>[plugin_budgets][<?php echo esc_attr( $slug ); ?>][daily]"
								   value="<?php echo esc_attr( (string) $slug_daily ); ?>"
								   class="small-text" style="width: 100%;"
								   placeholder="0"
								   title="<?php esc_attr_e( '0 = unlimited', 'ai-valve' ); ?>">
						</td>
						<td>
							<input type="number" min="0" step="1"
								   name="<?php echo esc_attr( $opt ); ?>[plugin_budgets][<?php echo esc_attr( $slug ); ?>][monthly]"
								   value="<?php echo esc_attr( (string) $slug_month ); ?>"
								   class="small-text" style="width: 100%;"
								   placeholder="0"
								   title="<?php esc_attr_e( '0 = unlimited', 'ai-valve' ); ?>">
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description" style="margin-top: 4px;">
				<?php esc_html_e( 'Token limits: 0 = no limit. When a limit is reached, further AI requests from that plugin are blocked until the next day or month.', 'ai-valve' ); ?>
			</p>
			<?php submit_button( __( 'Save Changes', 'ai-valve' ) ); ?>
		</form>
		<?php else : ?>
			<p><em><?php esc_html_e( 'No plugins have made AI requests yet. They will appear here automatically.', 'ai-valve' ); ?></em></p>
		<?php endif; ?>

		<?php if ( $by_provider ) : ?>
		<h2><?php esc_html_e( 'Providers (This Month)', 'ai-valve' ); ?></h2>
		<table class="widefat fixed striped" style="max-width: 600px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provider', 'ai-valve' ); ?></th>
					<th style="text-align: right;"><?php esc_html_e( 'Requests', 'ai-valve' ); ?></th>
					<th style="text-align: right;"><?php esc_html_e( 'Tokens', 'ai-valve' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $by_provider as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row['provider_id'] ); ?></td>
					<td style="text-align: right;"><?php echo esc_html( number_format_i18n( $row['request_count'] ) ); ?></td>
					<td style="text-align: right;"><?php echo esc_html( number_format_i18n( $row['total_tokens'] ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>

		<?php if ( $recent['items'] ) : ?>
		<h2><?php esc_html_e( 'Recent Requests', 'ai-valve' ); ?></h2>
		<?php $this->render_log_table( $recent['items'] ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders a token budget progress bar with label.
	 */
	private function render_budget_bar( int $used, int $limit, string $label ): void {
		if ( $limit <= 0 ) {
			return;
		}
		$pct       = min( 100, (int) round( ( $used / $limit ) * 100 ) );
		$bar_class = 'ai-valve-bar ai-valve-bar--ok';
		if ( $pct >= 100 ) {
			$bar_class = 'ai-valve-bar ai-valve-bar--over';
		} elseif ( $pct >= (int) $this->settings->get( 'alert_threshold_pct', 80 ) ) {
			$bar_class = 'ai-valve-bar ai-valve-bar--warn';
		}
		?>
		<div class="ai-valve-bar-wrap" title="<?php echo esc_attr( $pct . '% of ' . $label ); ?>">
			<div class="<?php echo esc_attr( $bar_class ); ?>" style="width: <?php echo esc_attr( (string) $pct ); ?>%;"></div>
		</div>
		<div class="ai-valve-sub">
			<?php
			printf(
				/* translators: 1: used tokens 2: limit tokens 3: percentage 4: budget label */
				esc_html__( '%1$s / %2$s (%3$s%%) of %4$s', 'ai-valve' ),
				esc_html( number_format_i18n( $used ) ),
				esc_html( number_format_i18n( $limit ) ),
				esc_html( (string) $pct ),
				esc_html( $label )
			);
			?>
		</div>
		<?php
	}

	/**
	 * Emits hidden fields for settings not present in the current form,
	 * so the Settings API sanitize callback preserves them.
	 *
	 * @param array<string, mixed> $all Current settings.
	 */
	private function emit_hidden_settings_fields( array $all ): void {
		$opt = Settings::option_key();
		// Scalar settings.
		$scalars = [
			'enabled'              => $all['enabled'] ? '1' : '',
			'default_policy'       => $all['default_policy'],
			'allow_admin'          => $all['allow_admin'] ? '1' : '',
			'allow_frontend'       => $all['allow_frontend'] ? '1' : '',
			'allow_cron'           => $all['allow_cron'] ? '1' : '',
			'allow_rest'           => $all['allow_rest'] ? '1' : '',
			'allow_ajax'           => $all['allow_ajax'] ? '1' : '',
			'allow_cli'            => $all['allow_cli'] ? '1' : '',
			'global_daily_limit'   => (string) $all['global_daily_limit'],
			'global_monthly_limit' => (string) $all['global_monthly_limit'],
			'alert_threshold_pct'  => (string) $all['alert_threshold_pct'],
			'alert_email'          => $all['alert_email'],
		];
		foreach ( $scalars as $key => $value ) {
			printf(
				'<input type="hidden" name="%s[%s]" value="%s">',
				esc_attr( $opt ),
				esc_attr( $key ),
				esc_attr( $value )
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Settings tab
	 * ----------------------------------------------------------------*/

	private function render_settings_tab(): void {
		$all = $this->settings->all();
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'ai_valve_settings_group' );
			$opt = Settings::option_key();
			?>

			<h2><?php esc_html_e( 'General', 'ai-valve' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable AI Valve', 'ai-valve' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="<?php echo esc_attr( $opt ); ?>[enabled]"
								   value="1"
								   <?php checked( $all['enabled'] ); ?>>
							<?php esc_html_e( 'Intercept and control AI requests', 'ai-valve' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When disabled, all AI requests pass through unmonitored.', 'ai-valve' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default policy', 'ai-valve' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( $opt ); ?>[default_policy]">
							<option value="allow" <?php selected( $all['default_policy'], 'allow' ); ?>>
								<?php esc_html_e( 'Allow', 'ai-valve' ); ?>
							</option>
							<option value="deny" <?php selected( $all['default_policy'], 'deny' ); ?>>
								<?php esc_html_e( 'Deny', 'ai-valve' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Applies to plugins not individually configured on the Dashboard tab. "Allow" lets any plugin use AI; "Deny" blocks plugins unless you explicitly allow them.', 'ai-valve' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Allowed Contexts', 'ai-valve' ); ?></h2>
			<p class="description" style="margin-bottom: 12px;">
				<?php esc_html_e( 'Choose which WordPress execution contexts may trigger AI requests. Unchecked contexts will block all AI calls, regardless of plugin policy.', 'ai-valve' ); ?>
			</p>
			<table class="form-table">
				<?php
				$contexts = [
					'allow_admin'    => [
						__( 'Admin (wp-admin)', 'ai-valve' ),
						__( 'Requests originating from the WordPress admin dashboard.', 'ai-valve' ),
					],
					'allow_frontend' => [
						__( 'Frontend', 'ai-valve' ),
						__( 'Requests from the public-facing site (themes, shortcodes, etc.).', 'ai-valve' ),
					],
					'allow_cron'     => [
						__( 'WP-Cron', 'ai-valve' ),
						__( 'Scheduled background tasks via wp-cron.php.', 'ai-valve' ),
					],
					'allow_rest'     => [
						__( 'REST API', 'ai-valve' ),
						__( 'Requests through the WordPress REST API (/wp-json/).', 'ai-valve' ),
					],
					'allow_ajax'     => [
						__( 'AJAX', 'ai-valve' ),
						__( 'Admin-ajax.php requests (legacy AJAX handler).', 'ai-valve' ),
					],
					'allow_cli'      => [
						__( 'WP-CLI', 'ai-valve' ),
						__( 'Command-line requests via the wp command.', 'ai-valve' ),
					],
				];
				foreach ( $contexts as $key => [ $label, $desc ] ) :
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									   name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $key ); ?>]"
									   value="1"
									   <?php checked( $all[ $key ] ); ?>>
								<?php esc_html_e( 'Allow AI requests', 'ai-valve' ); ?>
							</label>
							<p class="description"><?php echo esc_html( $desc ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'Global Token Budgets', 'ai-valve' ); ?></h2>
			<p class="description" style="margin-bottom: 12px;">
				<?php esc_html_e( 'Set site-wide token limits. Tokens are the units AI models use to measure input and output — roughly 1 token ≈ ¾ of a word. When a budget is reached, all AI requests are blocked until the next period resets.', 'ai-valve' ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Daily token limit', 'ai-valve' ); ?></th>
					<td>
						<input type="number" min="0" step="1"
							   name="<?php echo esc_attr( $opt ); ?>[global_daily_limit]"
							   value="<?php echo esc_attr( (string) $all['global_daily_limit'] ); ?>"
							   class="regular-text">
						<p class="description"><?php esc_html_e( 'Maximum tokens all plugins combined may use per day. Set to 0 for no limit.', 'ai-valve' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Monthly token limit', 'ai-valve' ); ?></th>
					<td>
						<input type="number" min="0" step="1"
							   name="<?php echo esc_attr( $opt ); ?>[global_monthly_limit]"
							   value="<?php echo esc_attr( (string) $all['global_monthly_limit'] ); ?>"
							   class="regular-text">
						<p class="description"><?php esc_html_e( 'Maximum tokens all plugins combined may use per calendar month. Set to 0 for no limit.', 'ai-valve' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Alerts', 'ai-valve' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Warning threshold', 'ai-valve' ); ?></th>
					<td>
						<input type="number" min="1" max="100" step="1"
							   name="<?php echo esc_attr( $opt ); ?>[alert_threshold_pct]"
							   value="<?php echo esc_attr( (string) $all['alert_threshold_pct'] ); ?>"
							   class="small-text"> %
						<p class="description">
							<?php esc_html_e( 'Show an admin notice when token usage reaches this percentage of any budget (daily or monthly). The progress bar on the Dashboard tab also turns yellow at this threshold.', 'ai-valve' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Notification email', 'ai-valve' ); ?></th>
					<td>
						<input type="email"
							   name="<?php echo esc_attr( $opt ); ?>[alert_email]"
							   value="<?php echo esc_attr( $all['alert_email'] ); ?>"
							   class="regular-text"
							   placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Receive an email when a budget is exceeded. Leave empty to disable email alerts.', 'ai-valve' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Logs tab
	 * ----------------------------------------------------------------*/

	private function render_logs_tab(): void {
		$repo = new LogRepository();

		// Read filter params (nonce not required — read-only display, admin-only page).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$filters = [
			'plugin_slug' => sanitize_key( $_GET['filter_plugin'] ?? '' ),
			'provider_id' => sanitize_key( $_GET['filter_provider'] ?? '' ),
			'context'     => sanitize_key( $_GET['filter_context'] ?? '' ),
			'status'      => sanitize_text_field( $_GET['filter_status'] ?? '' ),
			'per_page'    => 25,
			'page'        => max( 1, (int) ( $_GET['paged'] ?? 1 ) ),
		];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$result      = $repo->query( $filters );
		$total_pages = (int) ceil( $result['total'] / $filters['per_page'] );
		$base_url    = admin_url( 'options-general.php?page=' . self::SLUG . '&tab=logs' );
		?>

		<form method="get" action="<?php echo esc_url( $base_url ); ?>" style="margin-bottom: 1em;">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
			<input type="hidden" name="tab" value="logs">
			<label>
				<?php esc_html_e( 'Plugin:', 'ai-valve' ); ?>
				<input type="text" name="filter_plugin"
					   value="<?php echo esc_attr( $filters['plugin_slug'] ); ?>"
					   class="regular-text" placeholder="e.g. vmfa-ai-organizer">
			</label>
			<label style="margin-left: 1em;">
				<?php esc_html_e( 'Provider:', 'ai-valve' ); ?>
				<input type="text" name="filter_provider"
					   value="<?php echo esc_attr( $filters['provider_id'] ); ?>"
					   class="regular-text" placeholder="e.g. openai">
			</label>
			<label style="margin-left: 1em;">
				<?php esc_html_e( 'Context:', 'ai-valve' ); ?>
				<select name="filter_context">
					<option value=""><?php esc_html_e( 'All', 'ai-valve' ); ?></option>
					<?php foreach ( [ 'admin', 'frontend', 'cron', 'rest', 'ajax', 'cli' ] as $ctx ) : ?>
						<option value="<?php echo esc_attr( $ctx ); ?>"
							<?php selected( $filters['context'], $ctx ); ?>>
							<?php echo esc_html( $ctx ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<label style="margin-left: 1em;">
				<?php esc_html_e( 'Status:', 'ai-valve' ); ?>
				<select name="filter_status">
					<option value=""><?php esc_html_e( 'All', 'ai-valve' ); ?></option>
					<option value="allowed" <?php selected( $filters['status'], 'allowed' ); ?>>
						<?php esc_html_e( 'Allowed', 'ai-valve' ); ?>
					</option>
					<option value="denied" <?php selected( str_starts_with( $filters['status'], 'denied' ), true ); ?>>
						<?php esc_html_e( 'Denied', 'ai-valve' ); ?>
					</option>
				</select>
			</label>
			<?php submit_button( __( 'Filter', 'ai-valve' ), 'secondary', 'submit', false ); ?>
		</form>

		<p>
			<?php
			printf(
				/* translators: %s: total number of log entries */
				esc_html__( '%s entries found.', 'ai-valve' ),
				'<strong>' . esc_html( number_format_i18n( $result['total'] ) ) . '</strong>'
			);
			?>
		</p>

		<?php
		$this->render_log_table( $result['items'] );

		// Pagination.
		if ( $total_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post( paginate_links( [
				'base'    => add_query_arg( 'paged', '%#%', $base_url ),
				'format'  => '',
				'current' => $filters['page'],
				'total'   => $total_pages,
			] ) ?? '' );
			echo '</div></div>';
		}
	}

	/* ------------------------------------------------------------------
	 * Shared log table renderer
	 * ----------------------------------------------------------------*/

	/**
	 * @param list<object> $items
	 */
	private function render_log_table( array $items ): void {
		if ( ! $items ) {
			echo '<p><em>' . esc_html__( 'No log entries yet.', 'ai-valve' ) . '</em></p>';
			return;
		}
		?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'ai-valve' ); ?></th>
					<th><?php esc_html_e( 'Plugin', 'ai-valve' ); ?></th>
					<th><?php esc_html_e( 'Provider', 'ai-valve' ); ?></th>
					<th><?php esc_html_e( 'Model', 'ai-valve' ); ?></th>
					<th><?php esc_html_e( 'Capability', 'ai-valve' ); ?></th>
					<th><?php esc_html_e( 'Context', 'ai-valve' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Tokens', 'ai-valve' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ai-valve' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->created_at ?? '' ); ?></td>
					<td><code><?php echo esc_html( $row->plugin_slug ?? '' ); ?></code></td>
					<td><?php echo esc_html( $row->provider_id ?? '' ); ?></td>
					<td><?php echo esc_html( $row->model_id ?? '' ); ?></td>
					<td><?php echo esc_html( $row->capability ?? '' ); ?></td>
					<td><?php echo esc_html( $row->context ?? '' ); ?></td>
					<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) ( $row->total_tokens ?? 0 ) ) ); ?></td>
					<td>
						<?php
						$status = $row->status ?? '';
						$badge  = str_starts_with( $status, 'denied' )
							? '<span style="color:#d63638;font-weight:600;">' . esc_html( $status ) . '</span>'
							: '<span style="color:#00a32a;">' . esc_html( $status ) . '</span>';
						echo wp_kses( $badge, [ 'span' => [ 'style' => [] ] ] );
						?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ----------------------------------------------------------------*/

	/**
	 * Returns distinct plugin slugs that have appeared in the log.
	 *
	 * @return list<string>
	 */
	private function get_known_plugin_slugs(): array {
		global $wpdb;
		$table = LogRepository::table_name();
		$slugs = $wpdb->get_col( "SELECT DISTINCT plugin_slug FROM {$table} ORDER BY plugin_slug" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $slugs ?: [];
	}
}
