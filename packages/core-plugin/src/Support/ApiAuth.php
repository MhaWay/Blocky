<?php
/**
 * Bearer-token authentication for the Blocky REST namespace.
 *
 * Runs alongside cookie auth: a valid bky_live_* key authenticates the
 * request for scope checks (Access::allowed) without forging a user.
 *
 * @package Blocky\Core\Support
 */

declare( strict_types=1 );

namespace Blocky\Core\Support;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

/**
 * Request-scoped API-key authentication state.
 */
final class ApiAuth {

	/**
	 * Authenticated key row for this request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $key_row = null;

	/**
	 * Hook into the REST authentication pipeline.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'rest_authentication_errors', array( self::class, 'authenticate' ), 25 );
	}

	/**
	 * Authenticate a presented bearer key, if any.
	 *
	 * @param mixed $value Current authentication result.
	 * @return mixed Pass-through or WP_Error when the key is invalid.
	 */
	public static function authenticate( $value ) {
		$token = self::bearer_token();

		if ( null === $token || 0 !== strpos( $token, ApiKeyCodec::PREFIX ) ) {
			return $value;
		}

		$row = self::authorize_key( $token );
		if ( null === $row ) {
			ApiAudit::log( '', 'auth_failed', '' );
			return new \WP_Error(
				'blocky_invalid_key',
				__( 'Invalid or revoked API key.', 'blocky' ),
				array( 'status' => 401 )
			);
		}

		if ( ! ApiKeyStore::rate_limit_ok( (string) $row['public_id'] ) ) {
			ApiAudit::log( (string) $row['public_id'], 'rate_limited', '' );
			return new \WP_Error(
				'blocky_rate_limited',
				__( 'Rate limit exceeded: 60 requests per minute per key.', 'blocky' ),
				array( 'status' => 429 )
			);
		}

		self::$key_row = $row;
		// Core application-passwords (prio 20) already rejected
		// our bearer scheme; a verified key must assert auth itself.
		return true;
	}

	/**
	 * Verify a key directly (unit-testable seam over the store).
	 *
	 * @param string $token Full bearer token.
	 * @return array<string, mixed>|null Key row when valid.
	 */
	public static function authorize_key( string $token ): ?array {
		return ApiKeyStore::authorize( $token, self::scope_for_request() );
	}

	/**
	 * The key row authenticated for this request, if any.
	 *
	 * @return array<string, mixed>|null Row or null.
	 */
	public static function current_key_row(): ?array {
		return self::$key_row;
	}

	/**
	 * Reset request state (tests).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$key_row = null;
	}

	/**
	 * Extract the bearer token from the Authorization header.
	 *
	 * @return string|null Token or null.
	 */
	private static function bearer_token(): ?string {
		$header = '';
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$header = sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		if ( '' === $header && function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
			$header  = is_array( $headers ) && isset( $headers['Authorization'] )
				? (string) $headers['Authorization']
				: '';
		}

		if ( 1 !== preg_match( '/^Bearer\s+(\S+)$/i', $header, $matches ) ) {
			return null;
		}

		return (string) $matches[1];
	}

	/**
	 * Required scope for the current REST request (defaults to mcp).
	 *
	 * @return string Scope id.
	 */
	private static function scope_for_request(): string {
		$route = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		if ( false !== strpos( $route, '/blocky/v1/blocks' ) ) {
			return 'catalog:read';
		}

		if ( false !== strpos( $route, '/blocky/v1/documents' ) ) {
			$method = isset( $_SERVER['REQUEST_METHOD'] )
				? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
				: 'GET';
			return in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true )
				? 'documents:write' : 'documents:read';
		}

		return 'mcp:access';
	}
}
