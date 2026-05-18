<?php
/**
 * Plugin Name: AI Valve
 * Plugin URI:  https://github.com/soderlind/soderlind-aivalve
 * Description: Control, meter, and permission-gate AI usage from plugins that connect through the WordPress 7 AI connector.
 * Version:     1.1.5
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author:      Per Søderlind
 * Author URI:  https://soderlind.no
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: soderlind-aivalve
 */

declare(strict_types=1);

namespace Soderlind\AiValve;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

define( 'SODERLIND_AIVALVE_VERSION', '1.1.5' );
define( 'SODERLIND_AIVALVE_PLUGIN_FILE', __FILE__ );
define( 'SODERLIND_AIVALVE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SODERLIND_AIVALVE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SODERLIND_AIVALVE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader — Composer PSR-4.
 */
if ( file_exists( SODERLIND_AIVALVE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once SODERLIND_AIVALVE_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Activation hook — create DB table and store schema version.
 */
register_activation_hook( __FILE__, [ Tracking\LogRepository::class, 'activate' ] );

/**
 * Deactivation hook — clear scheduled events.
 */
register_deactivation_hook( __FILE__, static function (): void {
	wp_clear_scheduled_hook( 'soderlind_aivalve_log_retention' );
	wp_clear_scheduled_hook( 'aiv' . 'alve_log_retention' );
	wp_clear_scheduled_hook( 'ai' . '_valve_log_retention' );
} );

/**
 * Boot the plugin on `plugins_loaded` so all dependencies are available.
 */
add_action( 'plugins_loaded', static function (): void {
	( new Plugin() )->register();
} );
