<?php
/**
 * WP-CLI management for Blocky API keys.
 *
 * @package Blocky\Core\Cli
 */

declare(strict_types=1);

namespace Blocky\Core\Cli;

use Blocky\Core\Support\ApiKeyStore;

/**
 * Manage scoped, hashed API keys for MCP and REST automation.
 */
class KeyCommand extends \WP_CLI_Command {

	/**
	 * Create a key. The secret is printed exactly once.
	 *
	 * ## OPTIONS
	 *
	 * --name=<name>
	 * : Human label.
	 *
	 * [--scopes=<scopes>]
	 * : Comma separated scopes.
	 *
	 * ## EXAMPLES
	 *
	 *   wp blocky key create --name="Agent A" --scopes=catalog:read,documents:write
	 *
	 * @param array<int, string>   $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function create( array $args, array $assoc_args ): void {
		unset( $args );
		$name   = (string) ( $assoc_args['name'] ?? '' );
		$scopes = array_map( 'trim', explode( ',', (string) ( $assoc_args['scopes'] ?? 'catalog:read' ) ) );

		$admins  = get_users(
			array(
				'fields' => array( 'ID' ),
				'role'   => 'administrator',
				'number' => 1,
			)
		);
		$user_id = (int) ( get_current_user_id() ?: (int) ( $admins[0]->ID ?? 0 ) );

		$created = ApiKeyStore::create( $name, $scopes, $user_id );
		if ( $created === null ) {
			\WP_CLI::error( 'Cannot create key: check --name and --scopes (valid: ' . implode( ', ', ApiKeyStore::SCOPES ) . ')' );
		}

		\WP_CLI::log( 'Store this secret now; it is not recoverable:' );
		\WP_CLI::log( $created['secret'] );
		\WP_CLI::success( 'Key ' . $created['public_id'] . ' created.' );
	}

	/**
	 * List keys (never shows secrets).
	 *
	 * @param array<int, string>   $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function all( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );
		$rows = ApiKeyStore::list_keys();
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'public_id', 'name', 'scopes', 'created_at', 'last_used_at', 'revoked_at' ) );
	}

	/**
	 * Revoke a key by public id.
	 *
	 * ## OPTIONS
	 *
	 * <public_id>
	 * : Public id (12 chars).
	 *
	 * @param array<int, string>   $args       Positional args.
	 * @param array<string, mixed> $assoc_args Flags.
	 */
	public function revoke( array $args, array $assoc_args ): void {
		unset( $assoc_args );
		$public_id = (string) ( $args[0] ?? '' );
		if ( $public_id !== '' && ApiKeyStore::revoke( $public_id ) ) {
			\WP_CLI::success( 'Revoked ' . $public_id );
		} else {
			\WP_CLI::warning( 'No active key with id ' . $public_id );
		}
	}
}
