<?php

declare(strict_types=1);

namespace AIValve\Interceptor;

/**
 * Walks the call stack to identify which plugin triggered the AI request.
 *
 * Uses `debug_backtrace()` to find the first file path under `WP_PLUGIN_DIR`
 * that does NOT belong to ai-valve itself, then resolves the plugin slug.
 */
final class CallerDetector {

	/**
	 * Per-request cache so we only walk the backtrace once.
	 */
	private static ?string $cached_slug = null;

	/**
	 * Reset cache between requests (useful in tests / long-running processes).
	 */
	public static function reset(): void {
		self::$cached_slug = null;
	}

	/**
	 * Detect the calling plugin slug from the current call stack.
	 *
	 * Returns the directory name of the plugin (e.g. `vmfa-ai-organizer`),
	 * or `'unknown'` if no plugin can be identified.
	 */
	public static function detect(): string {
		if ( null !== self::$cached_slug ) {
			return self::$cached_slug;
		}

		$plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );
		$own_dir    = wp_normalize_path( AI_VALVE_DIR );

		// Walk the backtrace looking for the first frame in a different plugin.
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 30 );

		foreach ( $trace as $frame ) {
			if ( ! isset( $frame['file'] ) ) {
				continue;
			}

			$file = wp_normalize_path( $frame['file'] );

			// Must be under wp-content/plugins/.
			if ( ! str_starts_with( $file, $plugin_dir . '/' ) ) {
				continue;
			}

			// Skip ai-valve's own files.
			if ( str_starts_with( $file, $own_dir . '/' ) ) {
				continue;
			}

			// Skip WP core AI client files (they relay, not originate).
			if ( str_contains( $file, '/wp-includes/' ) ) {
				continue;
			}

			// Extract the plugin directory slug.
			$relative = substr( $file, strlen( $plugin_dir ) + 1 );
			$parts    = explode( '/', $relative, 2 );

			if ( ! empty( $parts[0] ) ) {
				self::$cached_slug = sanitize_key( $parts[0] );
				return self::$cached_slug;
			}
		}

		// Also try mu-plugins or theme code.
		$mu_dir    = defined( 'WPMU_PLUGIN_DIR' ) ? wp_normalize_path( WPMU_PLUGIN_DIR ) : '';
		$theme_dir = wp_normalize_path( get_theme_root() );

		foreach ( $trace as $frame ) {
			if ( ! isset( $frame['file'] ) ) {
				continue;
			}

			$file = wp_normalize_path( $frame['file'] );

			if ( $mu_dir && str_starts_with( $file, $mu_dir . '/' ) ) {
				$relative = substr( $file, strlen( $mu_dir ) + 1 );
				$parts    = explode( '/', $relative, 2 );
				self::$cached_slug = 'mu:' . sanitize_key( $parts[0] );
				return self::$cached_slug;
			}

			if ( str_starts_with( $file, $theme_dir . '/' ) ) {
				$relative = substr( $file, strlen( $theme_dir ) + 1 );
				$parts    = explode( '/', $relative, 2 );
				self::$cached_slug = 'theme:' . sanitize_key( $parts[0] );
				return self::$cached_slug;
			}
		}

		self::$cached_slug = 'unknown';
		return self::$cached_slug;
	}

	/**
	 * Detect the execution context.
	 */
	public static function context(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}
		if ( wp_doing_cron() ) {
			return 'cron';
		}
		if ( wp_doing_ajax() ) {
			return 'ajax';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		if ( is_admin() ) {
			return 'admin';
		}
		return 'frontend';
	}
}
