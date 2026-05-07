<?php

declare(strict_types=1);

namespace AIValve\Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * Provides date buckets that match the database timestamps used by log rows.
 */
final class UsageClock {

	public static function current_date(): string {
		$date = self::db_date( 'SELECT CURRENT_DATE()' );

		return '' !== $date ? $date : gmdate( 'Y-m-d' );
	}

	public static function current_month(): string {
		$date = self::current_date();

		return substr( $date, 0, 7 );
	}

	private static function db_date( string $sql ): string {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return '';
		}

		$value = (string) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
}
