<?php
/**
 * Diagnostic: direct hooks to confirm events fire during a live AI request.
 * Run with: wp eval-file wp-content/plugins/ai-valve/tests/debug-live.php
 */

$log_file = WP_CONTENT_DIR . '/ai-valve-debug.log';
file_put_contents( $log_file, "=== Debug session " . gmdate( 'Y-m-d H:i:s' ) . " ===\n", FILE_APPEND );

// Hook prevent_prompt directly (bypass our plugin's callback).
add_filter( 'wp_ai_client_prevent_prompt', function ( $prevent, $builder ) use ( $log_file ) {
	file_put_contents( $log_file, "[prevent_prompt] called, prevent=" . var_export( $prevent, true ) . ", builder=" . get_class( $builder ) . "\n", FILE_APPEND );
	return $prevent;
}, 5, 2 ); // priority 5 = before our plugin at 10

// Hook before_generate directly.
add_action( 'wp_ai_client_before_generate_result', function ( $event ) use ( $log_file ) {
	file_put_contents( $log_file, "[before_generate] called, event=" . get_class( $event ) . "\n", FILE_APPEND );
}, 5, 1 );

// Hook after_generate directly.
add_action( 'wp_ai_client_after_generate_result', function ( $event ) use ( $log_file ) {
	$usage = $event->getResult()->getTokenUsage();
	$tokens = $usage ? $usage->totalTokens : 'null';
	file_put_contents( $log_file, "[after_generate] called, event=" . get_class( $event ) . ", tokens=" . $tokens . "\n", FILE_APPEND );
}, 5, 1 );

file_put_contents( $log_file, "Debug hooks registered. Now making test AI call...\n", FILE_APPEND );

// Make a test AI call.
if ( function_exists( 'wp_ai_client_prompt' ) ) {
	file_put_contents( $log_file, "wp_ai_client_prompt exists, calling...\n", FILE_APPEND );

	$result = wp_ai_client_prompt( 'Say the word "hello" and nothing else.' )
		->using_max_tokens( 10 )
		->generate_text();

	if ( is_wp_error( $result ) ) {
		file_put_contents( $log_file, "WP_Error: " . $result->get_error_code() . " - " . $result->get_error_message() . "\n", FILE_APPEND );
		echo "WP_Error: " . $result->get_error_code() . " - " . $result->get_error_message() . "\n";
	} else {
		file_put_contents( $log_file, "Result: " . substr( (string) $result, 0, 200 ) . "\n", FILE_APPEND );
		echo "AI Result: " . $result . "\n";
	}
} else {
	file_put_contents( $log_file, "wp_ai_client_prompt does NOT exist\n", FILE_APPEND );
	echo "wp_ai_client_prompt does not exist\n";
}

file_put_contents( $log_file, "=== End debug session ===\n\n", FILE_APPEND );

// Also show if our plugin hooks fired.
global $wpdb;
$table = $wpdb->prefix . 'ai_valve_log';
$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
echo "Rows in {$table}: {$count}\n";

// Show debug log.
echo "\n--- Debug log contents ---\n";
echo file_get_contents( $log_file );
