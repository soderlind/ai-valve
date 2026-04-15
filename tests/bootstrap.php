<?php
/**
 * PHPUnit bootstrap — loads Composer autoloader and stubs WP constants
 * so Brain Monkey can mock WordPress functions without a real WP install.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Stub WordPress constants that the plugin references at file-load time.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wp/' );
}
if ( ! defined( 'AI_VALVE_VERSION' ) ) {
	define( 'AI_VALVE_VERSION', '0.1.0-test' );
}
if ( ! defined( 'AI_VALVE_FILE' ) ) {
	define( 'AI_VALVE_FILE', dirname( __DIR__ ) . '/ai-valve.php' );
}
if ( ! defined( 'AI_VALVE_DIR' ) ) {
	define( 'AI_VALVE_DIR', dirname( __DIR__ ) );
}
if ( ! defined( 'AI_VALVE_BASENAME' ) ) {
	define( 'AI_VALVE_BASENAME', 'ai-valve/ai-valve.php' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', '/tmp/wp/wp-content/plugins' );
}
if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
	define( 'WPMU_PLUGIN_DIR', '/tmp/wp/wp-content/mu-plugins' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// Stub WP core classes that the plugin type-hints but aren't available outside WP.
if ( ! class_exists( 'WP_AI_Client_Prompt_Builder' ) ) {
	// @phpcs:ignore
	class WP_AI_Client_Prompt_Builder {}
}

// Stub WordPress\AiClient SDK classes used by RequestInterceptor.
require_once __DIR__ . '/stubs/ai-client-sdk.php';

