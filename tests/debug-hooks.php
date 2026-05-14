<?php
/**
 * Debug script — run with: wp eval-file wp-content/plugins/ai-valve/tests/debug-hooks.php
 */

echo "=== AI Valve Hook Debug ===\n\n";

echo "wp_ai_client_prompt exists: " . ( function_exists( 'wp_ai_client_prompt' ) ? 'YES' : 'NO' ) . "\n";
echo "wp_supports_ai exists: " . ( function_exists( 'wp_supports_ai' ) ? 'YES' : 'NO' ) . "\n";

if ( function_exists( 'wp_supports_ai' ) ) {
	echo "wp_supports_ai(): " . ( wp_supports_ai() ? 'YES' : 'NO' ) . "\n";
}

echo "\n--- Registered hooks ---\n";

global $wp_filter;

$hooks = [
	'wp_ai_client_prevent_prompt',
	'wp_ai_client_before_generate_result',
	'wp_ai_client_after_generate_result',
];

foreach ( $hooks as $hook ) {
	echo "\n{$hook}:\n";
	if ( isset( $wp_filter[ $hook ] ) ) {
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $cbs ) {
			foreach ( $cbs as $key => $cb ) {
				echo "  [{$priority}] {$key}\n";
			}
		}
	} else {
		echo "  (none)\n";
	}
}

echo "\n--- Log table row count ---\n";
global $wpdb;
$table = $wpdb->prefix . 'soderlind_aivalve_log';
$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
echo "Rows in {$table}: {$count}\n";

echo "\n--- AI Valve settings ---\n";
$settings = get_option( 'soderlind_aivalve_settings', [] );
echo print_r( $settings, true ) . "\n";

echo "\n--- Token counter options ---\n";
$counters = $wpdb->get_results(
	"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'soderlind_aivalve_tokens_%' ORDER BY option_name",
	ARRAY_A
);
if ( $counters ) {
	foreach ( $counters as $row ) {
		echo "  {$row['option_name']} = {$row['option_value']}\n";
	}
} else {
	echo "  (none)\n";
}
