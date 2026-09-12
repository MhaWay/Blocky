<?php
/**
 * API-key codec: generation, parsing and hashing (pure, no WP calls).
 *
 * Format: bky_live_<public-12>_<secret-40> (lowercase base32 alphabet,
 * RFC4648 padding stripped). Storage keeps only the sha256 of the full
 * key string; lookups hash the presented key and compare digests.
 *
 * @package Blocky\Core\Support
 */

declare( strict_types=1 );

namespace Blocky\Core\Support;

/**
 * Generates and validates opaque API-key strings.
 */
final class ApiKeyCodec {

	/**
	 * Key prefix identifying live Blocky keys.
	 *
	 * @var string
	 */
	public const PREFIX = 'bky_live_';

	/**
	 * Crockford-ish lowercase alphabet (no i, l, o, u to avoid ambiguity).
	 *
	 * @var string
	 */
	private const ALPHABET = '0123456789abcdefghjkmnpqrstvwxyz';

	/**
	 * Generate a fresh key triple.
	 *
	 * @return array{public_id: string, secret: string, hash: string} Key data for storage.
	 */
	public static function generate(): array {
		$public_id = self::random_string( 12 );
		$secret    = self::random_string( 40 );
		$full      = self::PREFIX . $public_id . '_' . $secret;

		return array(
			'public_id' => $public_id,
			'secret'    => $full,
			'hash'      => hash( 'sha256', $full ),
		);
	}

	/**
	 * Parse a presented bearer token into its normalized form.
	 *
	 * @param string $token Raw Authorization bearer value.
	 * @return string|null Normalized full key or null when malformed.
	 */
	public static function parse( string $token ): ?string {
		$token = trim( $token );
		$shape = self::PREFIX . '[0-9a-hjkmnp-tv-z]{12}_[0-9a-hjkmnp-tv-z]{40}';

		if ( 1 !== preg_match( '/^' . $shape . '$/', $token ) ) {
			return null;
		}

		return $token;
	}

	/**
	 * Extract the public id from a full key string.
	 *
	 * @param string $key Full key.
	 * @return string|null Public id or null when malformed.
	 */
	public static function public_id_from_key( string $key ): ?string {
		if ( 0 !== strpos( $key, self::PREFIX ) ) {
			return null;
		}

		$rest = substr( $key, strlen( self::PREFIX ) );
		$idx  = strpos( $rest, '_' );
		if ( false === $idx ) {
			return null;
		}

		$public_id = substr( $rest, 0, $idx );
		if ( 12 !== strlen( $public_id ) ) {
			return null;
		}

		return $public_id;
	}

	/**
	 * Hash a full key for storage or lookup.
	 *
	 * @param string $key Full key.
	 * @return string Hex sha256 digest.
	 */
	public static function hash( string $key ): string {
		return hash( 'sha256', $key );
	}

	/**
	 * Constant-time comparison of a presented key against the stored hash.
	 *
	 * @param string $key         Full presented key.
	 * @param string $stored_hash Stored sha256 hex digest.
	 * @return bool True when identical.
	 */
	public static function verify( string $key, string $stored_hash ): bool {
		return hash_equals( $stored_hash, self::hash( $key ) );
	}

	/**
	 * Mask a full key for display (logs, UI).
	 *
	 * @param string $key Full key.
	 * @return string Masked display form.
	 */
	public static function mask( string $key ): string {
		$public_id = self::public_id_from_key( $key ) ?? '############';
		return self::PREFIX . $public_id . '_****';
	}

	/**
	 * Uniformly sample a key segment from the alphabet.
	 *
	 * @param int $length Number of characters.
	 * @return string Random segment.
	 */
	private static function random_string( int $length ): string {
		$alphabet = self::ALPHABET;
		$size     = strlen( $alphabet );
		$out      = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$index = random_int( 0, $size - 1 );
			$out  .= $alphabet[ $index ];
		}

		return $out;
	}
}
