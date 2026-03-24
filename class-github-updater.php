<?php
/**
 * GitHub Plugin Updater
 *
 * Minimal wrapper around yahnis-elsts/plugin-update-checker for GitHub-hosted
 * WordPress plugins. Defers the actual update-checker setup to the `init` action
 * so it works regardless of when the calling plugin loads the file.
 *
 * @package Soderlind\WordPress
 * @version 1.0.0
 * @author  Per Soderlind
 * @license GPL-2.0-or-later
 * @link    https://github.com/soderlind/wordpress-plugin-github-updater
 */

namespace Soderlind\WordPress;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Sets up automatic plugin updates from a GitHub repository.
 */
final class GitHubUpdater {

	/**
	 * Initialize the GitHub update checker.
	 *
	 * @param string $github_url  Full GitHub repository URL (e.g. 'https://github.com/owner/repo').
	 * @param string $plugin_file Absolute path to the main plugin file (__FILE__).
	 * @param string $plugin_slug Plugin slug used by WordPress (e.g. 'my-plugin').
	 * @param string $name_regex  Optional regex to filter release assets (e.g. '/my-plugin\.zip/').
	 * @param string $branch      Branch to track (default 'main').
	 */
	public static function init(
		string $github_url,
		string $plugin_file,
		string $plugin_slug,
		string $name_regex = '',
		string $branch = 'main',
	): void {
		add_action( 'init', static function () use ( $github_url, $plugin_file, $plugin_slug, $name_regex, $branch ): void {
			try {
				$checker = PucFactory::buildUpdateChecker( $github_url, $plugin_file, $plugin_slug );
				$checker->setBranch( $branch );

				if ( '' !== $name_regex ) {
					$checker->getVcsApi()->enableReleaseAssets( $name_regex );
				}
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'GitHubUpdater (' . $plugin_slug . '): ' . $e->getMessage() );
				}
			}
		} );
	}
}
