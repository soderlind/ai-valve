<?php
/**
 * Plugin Name: AI Valve
 * Plugin URI:  https://github.com/soderlind/ai-valve
 * Description: Control, meter, and permission-gate AI usage from plugins that connect through the WordPress 7 AI connector.
 * Version:     1.0.2
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author:      Per Søderlind
 * Author URI:  https://soderlind.no
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-valve
 */

declare(strict_types=1);

namespace AIValve;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

define( 'AI_VALVE_VERSION', '1.0.1' );
define( 'AI_VALVE_FILE', __FILE__ );
define( 'AI_VALVE_DIR', __DIR__ );
define( 'AI_VALVE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader — Composer PSR-4.
 */
if ( file_exists( AI_VALVE_DIR . '/vendor/autoload.php' ) ) {
	require_once AI_VALVE_DIR . '/vendor/autoload.php';
}

/**
 * Activation hook — create DB table and store schema version.
 */
register_activation_hook( __FILE__, [ Tracking\LogRepository::class, 'activate' ] );

/**
 * Deactivation hook — clear scheduled events.
 */
register_deactivation_hook( __FILE__, static function (): void {
	wp_clear_scheduled_hook( 'ai_valve_log_retention' );
} );

/**
 * Boot the plugin on `plugins_loaded` so all dependencies are available.
 */
add_action( 'plugins_loaded', static function (): void {
	// Update checker via GitHub releases.
	if ( ! class_exists( \Soderlind\WordPress\GitHubUpdater::class) ) {
		require_once AI_VALVE_DIR . '/class-github-updater.php';
	}
	\Soderlind\WordPress\GitHubUpdater::init(
		github_url: 'https://github.com/soderlind/ai-valve',
		plugin_file: AI_VALVE_FILE,
		plugin_slug: 'ai-valve',
		name_regex: '/ai-valve\.zip/',
		branch: 'main',
	);

	( new Plugin() )->register();
} );
