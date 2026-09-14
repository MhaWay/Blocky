<?php
/**
 * Append-only audit trail for API-key activity.
 *
 * Client IPs are stored hashed with the site salt (GDPR-friendly).
 *
 * @package Blocky\Core\Support
 */

declare( strict_types=1 );

namespace Blocky\Core\Support;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

/**
 * Audit table CRUD for key usage and failures.
 */
final class ApiAudit {

	/**
	 * Table name without prefix.
	 *
	 * @var string
	 */
	public const TABLE = 'blocky_api_audit';

	/**
	 * Install or upgrade the audit table.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;
		$table   = $wpdb->prefix . self::TABLE;
		$charset = $wpdb->get_charset_collate();
		$sql     = sprintf(
			'CREATE TABLE %1$s (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		key_public_id VARCHAR(12) NOT NULL,
		action VARCHAR(64) NOT NULL,
		detail VARCHAR(191) NOT NULL,
		ip_hash CHAR(64) NOT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id),
		KEY key_action (key_public_id, action)
		) %2$s;',
			$table,
			$charset
		);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Append one audit entry.
	 *
	 * @param string $public_id Key public id (empty for anonymous failures).
	 * @param string $action    Machine-readable action id.
	 * @param string $detail    Short context (route, post id...).
	 * @return void
	 */
	public static function log( string $public_id, string $action, string $detail ): void {
		global $wpdb;
		$remote = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; object cache intentionally bypassed.
			$wpdb->prefix . self::TABLE, // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; object cache intentionally bypassed.
			array(
				'key_public_id' => substr( $public_id, 0, 12 ),
				'action'        => substr( $action, 0, 64 ),
				'detail'        => substr( $detail, 0, 191 ),
				'ip_hash'       => hash( 'sha256', $remote . wp_salt() ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Recent entries for display.
	 *
	 * @param int $limit Number of rows.
	 * @return array<int, array<string, mixed>> Rows.
	 */
	public static function recent( int $limit = 100 ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom audit table, reads are admin-only and cheap.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is prefix plus a class constant.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; object cache intentionally bypassed.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; object cache intentionally bypassed.
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table built from prefix and class constant.
				'SELECT id, key_public_id, action, detail, ip_hash, created_at FROM ' . $table . ' ORDER BY id DESC LIMIT %d',
				max( 1, min( 500, $limit ) )
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}
