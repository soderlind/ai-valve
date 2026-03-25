<?php

declare(strict_types=1);

namespace AIValve\Tracking;

/**
 * Manages the custom `{prefix}ai_valve_log` database table.
 */
final class LogRepository {

	private const TABLE_SUFFIX  = 'ai_valve_log';
	private const SCHEMA_VERSION = 3;
	private const VERSION_KEY    = 'ai_valve_db_version';

	/* ------------------------------------------------------------------
	 * Table name helper
	 * ----------------------------------------------------------------*/

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/* ------------------------------------------------------------------
	 * Activation — create / upgrade table
	 * ----------------------------------------------------------------*/

	public static function activate(): void {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			plugin_slug     VARCHAR(191)    NOT NULL DEFAULT '',
			provider_id     VARCHAR(191)    NOT NULL DEFAULT '',
			model_id        VARCHAR(191)    NOT NULL DEFAULT '',
			capability      VARCHAR(64)     NOT NULL DEFAULT '',
			context         VARCHAR(32)     NOT NULL DEFAULT '',
			prompt_tokens   INT UNSIGNED    NOT NULL DEFAULT 0,
			completion_tokens INT UNSIGNED  NOT NULL DEFAULT 0,
			total_tokens    INT UNSIGNED    NOT NULL DEFAULT 0,
			duration_ms     INT UNSIGNED    NOT NULL DEFAULT 0,
			status          VARCHAR(64)     NOT NULL DEFAULT 'allowed',
			created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_plugin_slug (plugin_slug),
			KEY idx_provider_id (provider_id),
			KEY idx_created_at (created_at),
			KEY idx_status     (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		$current_version = (int) get_option( self::VERSION_KEY, 0 );

		// v1 → v2: widen status column.
		if ( $current_version < 2 ) {
			$wpdb->query( "ALTER TABLE {$table} MODIFY status VARCHAR(64) NOT NULL DEFAULT 'allowed'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// v2 → v3: add duration_ms column.
		if ( $current_version < 3 ) {
			$col = $wpdb->get_var( $wpdb->prepare(
				'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$table,
				'duration_ms'
			) );
			if ( null === $col ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN duration_ms INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_tokens" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		update_option( self::VERSION_KEY, self::SCHEMA_VERSION, true );
	}

	/* ------------------------------------------------------------------
	 * Insert
	 * ----------------------------------------------------------------*/

	/**
	 * @param array{
	 *     plugin_slug: string,
	 *     provider_id: string,
	 *     model_id: string,
	 *     capability: string,
	 *     context: string,
	 *     prompt_tokens: int,
	 *     completion_tokens: int,
	 *     total_tokens: int,
	 *     status: string,
	 * } $row
	 */
	public function insert( array $row ): int|false {
		global $wpdb;

		$defaults = [
			'plugin_slug'       => '',
			'provider_id'       => '',
			'model_id'          => '',
			'capability'        => '',
			'context'           => '',
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'total_tokens'      => 0,
			'duration_ms'       => 0,
			'status'            => 'allowed',
		];

		$data = array_merge( $defaults, array_intersect_key( $row, $defaults ) );

		$result = $wpdb->insert(
			self::table_name(),
			$data,
			[
				'%s', // plugin_slug
				'%s', // provider_id
				'%s', // model_id
				'%s', // capability
				'%s', // context
				'%d', // prompt_tokens
				'%d', // completion_tokens
				'%d', // total_tokens
				'%d', // duration_ms
				'%s', // status
			]
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/* ------------------------------------------------------------------
	 * Queries
	 * ----------------------------------------------------------------*/

	/**
	 * Paginated log entries.
	 *
	 * @param array<string, mixed> $filters  Associative filter params.
	 * @return array{ items: list<object>, total: int }
	 */
	public function query( array $filters = [] ): array {
		global $wpdb;

		$table    = self::table_name();
		$where    = [];
		$values   = [];
		$per_page = max( 1, min( 100, (int) ( $filters['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		if ( ! empty( $filters['plugin_slug'] ) ) {
			$where[]  = 'plugin_slug = %s';
			$values[] = $filters['plugin_slug'];
		}
		if ( ! empty( $filters['provider_id'] ) ) {
			$where[]  = 'provider_id = %s';
			$values[] = $filters['provider_id'];
		}
		if ( ! empty( $filters['model_id'] ) ) {
			$where[]  = 'model_id = %s';
			$values[] = $filters['model_id'];
		}
		if ( ! empty( $filters['context'] ) ) {
			$where[]  = 'context = %s';
			$values[] = $filters['context'];
		}
		if ( ! empty( $filters['status'] ) ) {
			if ( 'denied' === $filters['status'] ) {
				$where[]  = 'status LIKE %s';
				$values[] = 'denied%';
			} else {
				$where[]  = 'status = %s';
				$values[] = $filters['status'];
			}
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $filters['date_from'];
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $filters['date_to'];
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Build the count query.
		$count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
		if ( $values ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Build the data query.
		$data_sql = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$all_vals = array_merge( $values, [ $per_page, $offset ] );
		$data_sql = $wpdb->prepare( $data_sql, ...$all_vals ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items    = $wpdb->get_results( $data_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return [
			'items' => $items ?: [],
			'total' => $total,
		];
	}

	/* ------------------------------------------------------------------
	 * Aggregation helpers
	 * ----------------------------------------------------------------*/

	/**
	 * Token totals for a given date range.
	 *
	 * @return array{ prompt_tokens: int, completion_tokens: int, total_tokens: int, request_count: int }
	 */
	public function totals( string $from, string $to, string $plugin_slug = '' ): array {
		global $wpdb;

		$table = self::table_name();
		$where = 'WHERE created_at >= %s AND created_at <= %s AND status = %s';
		$vals  = [ $from, $to, 'allowed' ];

		if ( '' !== $plugin_slug ) {
			$where .= ' AND plugin_slug = %s';
			$vals[] = $plugin_slug;
		}

		$sql = $wpdb->prepare(
			"SELECT
				COALESCE( SUM(prompt_tokens), 0 )     AS prompt_tokens,
				COALESCE( SUM(completion_tokens), 0 )  AS completion_tokens,
				COALESCE( SUM(total_tokens), 0 )       AS total_tokens,
				COUNT(*)                               AS request_count
			FROM {$table} {$where}",
			...$vals
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return [
			'prompt_tokens'     => (int) ( $row['prompt_tokens'] ?? 0 ),
			'completion_tokens' => (int) ( $row['completion_tokens'] ?? 0 ),
			'total_tokens'      => (int) ( $row['total_tokens'] ?? 0 ),
			'request_count'     => (int) ( $row['request_count'] ?? 0 ),
		];
	}

	/**
	 * Token usage grouped by plugin slug for a date range.
	 *
	 * @return list<array{ plugin_slug: string, total_tokens: int, request_count: int }>
	 */
	public function totals_by_plugin( string $from, string $to ): array {
		global $wpdb;

		$table = self::table_name();
		$sql   = $wpdb->prepare(
			"SELECT plugin_slug,
				COALESCE( SUM(total_tokens), 0 ) AS total_tokens,
				COUNT(*)                         AS request_count
			FROM {$table}
			WHERE created_at >= %s AND created_at <= %s AND status = %s
			GROUP BY plugin_slug
			ORDER BY total_tokens DESC",
			$from,
			$to,
			'allowed'
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map(
			static fn( array $r ) => [
				'plugin_slug'   => $r['plugin_slug'],
				'total_tokens'  => (int) $r['total_tokens'],
				'request_count' => (int) $r['request_count'],
			],
			$rows ?: []
		);
	}

	/**
	 * Token usage grouped by context for a date range.
	 *
	 * @return list<array{ context: string, total_tokens: int, request_count: int }>
	 */
	public function totals_by_context( string $from, string $to ): array {
		global $wpdb;

		$table = self::table_name();
		$sql   = $wpdb->prepare(
			"SELECT context,
				COALESCE( SUM(total_tokens), 0 ) AS total_tokens,
				COUNT(*)                         AS request_count
			FROM {$table}
			WHERE created_at >= %s AND created_at <= %s AND status = %s
			GROUP BY context
			ORDER BY total_tokens DESC",
			$from,
			$to,
			'allowed'
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map(
			static fn( array $r ) => [
				'context'       => $r['context'],
				'total_tokens'  => (int) $r['total_tokens'],
				'request_count' => (int) $r['request_count'],
			],
			$rows ?: []
		);
	}

	/**
	 * Token usage grouped by provider for a date range.
	 *
	 * @return list<array{ provider_id: string, total_tokens: int, request_count: int }>
	 */
	public function totals_by_provider( string $from, string $to ): array {
		global $wpdb;

		$table = self::table_name();
		$sql   = $wpdb->prepare(
			"SELECT provider_id,
				COALESCE( SUM(total_tokens), 0 ) AS total_tokens,
				COUNT(*)                         AS request_count
			FROM {$table}
			WHERE created_at >= %s AND created_at <= %s AND status = %s
			GROUP BY provider_id
			ORDER BY total_tokens DESC",
			$from,
			$to,
			'allowed'
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map(
			static fn( array $r ) => [
				'provider_id'   => $r['provider_id'],
				'total_tokens'  => (int) $r['total_tokens'],
				'request_count' => (int) $r['request_count'],
			],
			$rows ?: []
		);
	}

	/**
	 * Token usage grouped by provider and model for a date range.
	 *
	 * @return list<array{ provider_id: string, model_id: string, total_tokens: int, request_count: int }>
	 */
	public function totals_by_provider_model( string $from, string $to ): array {
		global $wpdb;

		$table = self::table_name();
		$sql   = $wpdb->prepare(
			"SELECT provider_id, model_id,
				COALESCE( SUM(total_tokens), 0 ) AS total_tokens,
				COUNT(*)                         AS request_count
			FROM {$table}
			WHERE created_at >= %s AND created_at <= %s AND status = %s
			GROUP BY provider_id, model_id
			ORDER BY total_tokens DESC",
			$from,
			$to,
			'allowed'
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map(
			static fn( array $r ) => [
				'provider_id'   => $r['provider_id'],
				'model_id'      => $r['model_id'],
				'total_tokens'  => (int) $r['total_tokens'],
				'request_count' => (int) $r['request_count'],
			],
			$rows ?: []
		);
	}

	/* ------------------------------------------------------------------
	 * Distinct filter values
	 * ----------------------------------------------------------------*/

	/**
	 * Return distinct non-empty values for the filterable columns.
	 *
	 * @return array{ plugins: list<string>, providers: list<string>, models: list<string> }
	 */
	public function distinct_filter_values(): array {
		global $wpdb;
		$table = self::table_name();

		return [
			'plugins'   => $wpdb->get_col( "SELECT DISTINCT plugin_slug FROM {$table} WHERE plugin_slug != '' ORDER BY plugin_slug" ),   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'providers' => $wpdb->get_col( "SELECT DISTINCT provider_id FROM {$table} WHERE provider_id != '' ORDER BY provider_id" ),   // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'models'    => $wpdb->get_col( "SELECT DISTINCT model_id    FROM {$table} WHERE model_id    != '' ORDER BY model_id" ),      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		];
	}

	/* ------------------------------------------------------------------
	 * Cleanup
	 * ----------------------------------------------------------------*/

	/**
	 * Delete all rows from the log table.
	 */
	public function purge(): int {
		global $wpdb;
		$table = self::table_name();
		return (int) $wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Delete rows older than the given number of days.
	 */
	public function delete_older_than( int $days ): int {
		global $wpdb;
		$table    = self::table_name();
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		return (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$cutoff
		) );
	}

	/**
	 * Drop the table and remove the schema version option.
	 */
	public static function uninstall(): void {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		delete_option( self::VERSION_KEY );
	}
}
