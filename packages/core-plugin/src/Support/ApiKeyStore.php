<?php
/**
 * Hashed API-key storage with scopes and coarse rate limiting.
 *
 * Secrets are never stored: rows keep the sha256 digest of the full key
 * plus the public id used for display.
 *
 * @package Blocky\Core\Support
 */

declare( strict_types=1 );

namespace Blocky\Core\Support;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

/**
 * Custom-table CRUD for Blocky API keys.
 */
final class ApiKeyStore {

	/**
	 * Requests allowed per minute per key (transient-backed window).
	 *
	 * @var int
	 */
	public const RATE_LIMIT_PER_MINUTE = 60;

	/**
	 * Table name without prefix.
	 *
	 * @var string
	 */
	public const TABLE = 'blocky_api_keys';

	/**
	 * Valid scope identifiers accepted at creation.
	 *
	 * @var array<int, string>
	 */
	public const SCOPES = array( 'catalog:read', 'documents:read', 'documents:write', 'mcp:access' );

	/**
	 * Fully qualified table name.
	 *
	 * @return string Table name with WP prefix.
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Install or upgrade the keys table.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

			$sql = sprintf(
				'CREATE TABLE %1$s (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		public_id VARCHAR(12) NOT NULL,
		secret_hash CHAR(64) NOT NULL,
		name VARCHAR(191) NOT NULL,
		scopes VARCHAR(191) NOT NULL,
		created_by BIGINT UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL,
		last_used_at DATETIME NULL,
		revoked_at DATETIME NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY public_id (public_id),
		UNIQUE KEY secret_hash (secret_hash)
		) %2$s;',
				$table,
				$charset
			);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create a key row and return the one-time secret.
	 *
	 * @param string             $name       Human label.
	 * @param array<int, string> $scopes     Requested scopes (subset of SCOPES).
	 * @param int                $created_by Owning user id.
	 * @return array{secret: string, public_id: string}|null Null on invalid input.
	 */
	public static function create( string $name, array $scopes, int $created_by ): ?array {
		if ( $name === '' || $created_by <= 0 ) {
			return null;
		}

		$normalized = self::normalize_scopes( $scopes );
		if ( $normalized === array() ) {
			return null;
		}

		$data  = ApiKeyCodec::generate();
		$table = self::table_name();
		global $wpdb;

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; object cache intentionally bypassed.
			$table,
			array(
				'public_id'   => $data['public_id'],
				'secret_hash' => $data['hash'],
				'name'        => $name,
				'scopes'      => implode( ',', $normalized ),
				'created_by'  => $created_by,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( $inserted === false || (int) $wpdb->insert_id === 0 ) {
			return null;
		}

		return array(
			'secret'    => $data['secret'],
			'public_id' => $data['public_id'],
		);
	}

	/**
	 * Verify a presented key: hash lookup, revocation, scope.
	 *
	 * Rate limiting is enforced by the caller (ApiAuth) so a throttled
	 * request can answer 429 instead of masquerading as an invalid key.
	 *
	 * @param string $token Full bearer key.
	 * @param string $scope Required scope.
	 * @return array<string, mixed>|null Key row when usable.
	 */
	public static function authorize( string $token, string $scope ): ?array {
		$parsed = ApiKeyCodec::parse( $token );
		if ( $parsed === null ) {
			return null;
		}

		$hash  = ApiKeyCodec::hash( $parsed );
		$table = self::table_name();
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; object cache intentionally bypassed.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; object cache intentionally bypassed.
				/* phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is prefix plus class constant. */ "SELECT * FROM {$table} WHERE secret_hash = %s AND revoked_at IS NULL LIMIT 1",
				$hash
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$scopes = explode( ',', (string) ( $row['scopes'] ?? '' ) );
		if ( ! in_array( $scope, $scopes, true ) ) {
			return null;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; object cache intentionally bypassed.
			$table,
			array( 'last_used_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $row['id'] ),
			array( '%s' ),
			array( '%d' )
		);

		return $row;
	}

	/**
	 * Revoke a key by public id.
	 *
	 * @param string $public_id Public id (12 chars).
	 * @return bool True when a row was updated.
	 */
	public static function revoke( string $public_id ): bool {
		global $wpdb;
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; object cache intentionally bypassed.
			self::table_name(),
			array( 'revoked_at' => current_time( 'mysql' ) ),
			array( 'public_id' => $public_id )
		);
		return (int) $updated > 0;
	}

	/**
	 * List keys for display (never exposes hashes).
	 *
	 * @return array<int, array<string, mixed>> Display rows.
	 */
	public static function list_keys(): array {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only list from custom table.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is prefix plus class constant.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; object cache intentionally bypassed.
			$wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables; object cache intentionally bypassed.
				/* phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is prefix plus class constant. */ 'SELECT id, public_id, name, scopes, created_by, created_at, last_used_at, revoked_at FROM ' . $table . ' ORDER BY id DESC LIMIT 200'
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Filter requested scopes down to the allowed vocabulary.
	 *
	 * @param array<int, string> $scopes Requested scopes.
	 * @return array<int, string> Unique valid scopes.
	 */
	public static function normalize_scopes( array $scopes ): array {
		$valid = array_values(
			array_filter(
				array_unique( $scopes ),
				static function ( $scope ): bool {
					return is_string( $scope ) && in_array( $scope, self::SCOPES, true );
				}
			)
		);
		sort( $valid );
		return $valid;
	}

	/**
	 * Fixed-window rate limit per key.
	 *
	 * @param string $public_id Key public id.
	 * @return bool True when the request fits in the current window.
	 */
	public static function rate_limit_ok( string $public_id ): bool {
		$key   = 'ggapb_rate_' . $public_id . '_' . (string) (int) floor( microtime( true ) / 60 );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT_PER_MINUTE ) {
			return false;
		}
		set_transient( $key, $count + 1, 120 );
		return true;
	}
}
