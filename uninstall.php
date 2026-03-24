<?php
/**
 * AIValve — Uninstall handler.
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes: custom DB table, all ai_valve_* options, and alert transients.
 *
 * @package AIValve
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Autoloader may not be loaded during uninstall, so require manually.
require_once __DIR__ . '/vendor/autoload.php';

use AIValve\Settings\Settings;
use AIValve\Tracking\LogRepository;
use AIValve\Tracking\UsageTracker;

// Drop the log table.
LogRepository::uninstall();

// Delete the settings option.
Settings::delete();

// Delete all rolling counter options.
UsageTracker::delete_all();

// Clean up alert transients.
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_ai_valve_alert_sent_' ) . '%'
	)
);
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_timeout_ai_valve_alert_sent_' ) . '%'
	)
);
