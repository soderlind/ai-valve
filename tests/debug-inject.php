<?php
/**
 * Verify that clone of WP_AI_Client_Prompt_Builder shares the same inner PromptBuilder,
 * and that injecting the event dispatcher via reflection works.
 */

use WordPress\AiClient\AiClient;

$log = fn( $msg ) => file_put_contents(
	WP_CONTENT_DIR . '/ai-valve-debug.log',
	$msg . "\n",
	FILE_APPEND
);

$log( "=== Dispatcher injection test " . gmdate( 'H:i:s' ) . " ===" );

// Hook prevent_prompt at priority 1 (before our plugin at 10).
add_filter( 'wp_ai_client_prevent_prompt', function ( $prevent, $builder ) use ( $log ) {
	// Use reflection to access the private $builder property.
	$ref_wp    = new ReflectionClass( $builder );
	$prop      = $ref_wp->getProperty( 'builder' );
	$sdk_builder = $prop->getValue( $builder );

	// Check current dispatcher state.
	$ref_sdk   = new ReflectionClass( $sdk_builder );
	$disp_prop = $ref_sdk->getProperty( 'eventDispatcher' );
	$current   = $disp_prop->getValue( $sdk_builder );
	$log( "Before injection: eventDispatcher is " . ( $current === null ? 'NULL' : get_class( $current ) ) );

	// Inject the WP event dispatcher if not already set.
	if ( $current === null ) {
		$dispatcher = AiClient::getEventDispatcher();
		$log( "Injecting dispatcher: " . ( $dispatcher !== null ? get_class( $dispatcher ) : 'NULL (no dispatcher available!)' ) );
		if ( $dispatcher !== null ) {
			$disp_prop->setValue( $sdk_builder, $dispatcher );
		}
		$check = $disp_prop->getValue( $sdk_builder );
		$log( "After injection: eventDispatcher is " . ( $check === null ? 'NULL' : get_class( $check ) ) );
	}

	return $prevent;
}, 1, 2 );

// Hook the events.
add_action( 'wp_ai_client_before_generate_result', function ( $event ) use ( $log ) {
	$log( "[EVENT] before_generate_result FIRED!" );
}, 5 );

add_action( 'wp_ai_client_after_generate_result', function ( $event ) use ( $log ) {
	$usage = $event->getResult()->getTokenUsage();
	$tokens = $usage ? $usage->totalTokens : 'null';
	$log( "[EVENT] after_generate_result FIRED! tokens={$tokens}" );
}, 5 );

$log( "Making test AI call..." );
$result = wp_ai_client_prompt( 'Say the word "hello" and nothing else.' )
	->using_max_tokens( 10 )
	->generate_text();

if ( is_wp_error( $result ) ) {
	$log( "WP_Error: " . $result->get_error_code() . " - " . $result->get_error_message() );
	echo "WP_Error: " . $result->get_error_message() . "\n";
} else {
	$log( "Result: " . $result );
	echo "Result: " . $result . "\n";
}

// Show log.
global $wpdb;
$table = $wpdb->prefix . 'ai_valve_log';
$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
echo "Log rows: {$count}\n\n";

echo file_get_contents( WP_CONTENT_DIR . '/ai-valve-debug.log' );
