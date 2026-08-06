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
if ( ! defined( 'SODERLIND_AIVALVE_VERSION' ) ) {
	define( 'SODERLIND_AIVALVE_VERSION', '0.1.0-test' );
}
if ( ! defined( 'SODERLIND_AIVALVE_PLUGIN_FILE' ) ) {
	define( 'SODERLIND_AIVALVE_PLUGIN_FILE', dirname( __DIR__ ) . '/soderlind-aivalve.php' );
}
if ( ! defined( 'SODERLIND_AIVALVE_PLUGIN_DIR' ) ) {
	define( 'SODERLIND_AIVALVE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'SODERLIND_AIVALVE_PLUGIN_BASENAME' ) ) {
	define( 'SODERLIND_AIVALVE_PLUGIN_BASENAME', 'soderlind-aivalve/soderlind-aivalve.php' );
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

if ( ! class_exists( 'WP_Error' ) ) {
	// @phpcs:ignore
	class WP_Error {
		/** @var array<string, list<string>> */
		public array $errors = array();
		/** @var array<string, mixed> */
		public array $error_data = array();

		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			if ( '' !== $code ) {
				$this->errors[ $code ][] = $message;
				if ( '' !== $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code(): string {
			return (string) ( array_key_first( $this->errors ) ?? '' );
		}

		public function get_error_message(): string {
			$code = $this->get_error_code();
			return $this->errors[ $code ][0] ?? '';
		}
	}
}

// Stub WordPress\AiClient SDK classes used by RequestInterceptor.
require_once __DIR__ . '/stubs/ai-client-sdk.php';

