<?php
/**
 * Conservative sanitizer for client-uploaded page CSS (defense in depth).
 *
 * @package Blocky\Core\Support
 */

declare( strict_types=1 );

namespace Blocky\Core\Support;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

/**
 * Strips dangerous constructs from CSS accepted over REST.
 */
final class CssSanitizer {

	/**
	 * Hard size cap for accepted payloads.
	 *
	 * @var int
	 */
	public const MAX_BYTES = 2 * 1024 * 1024;

	/**
	 * Disallowed constructs, matched case-insensitively.
	 *
	 * @var array<int,string>
	 */
	private const FORBIDDEN = array(
		'/@import\b/i',
		'/@charset\b/i',
		'/expression\s*\(/i',
		'/behaviou?r\s*:/i',
		'/-moz-binding/i',
		'/javascript\s*:/i',
		'/vbscript\s*:/i',
		'/url\s*\(\s*["\']?\s*(?:https?:)?\/\//i',
		'/<\/@?style/i',
	);

	/**
	 * Sanitize a CSS payload.
	 *
	 * @param string $css Raw CSS submitted by the editor runtime.
	 * @return string Clean CSS, or an empty string when unusable.
	 */
	public static function sanitize( string $css ): string {
		$css = trim( $css );

		if ( '' === $css || strlen( $css ) > self::MAX_BYTES ) {
			return '';
		}

		// Strip comments so obfuscation via comment splitting cannot slip through.
		$css = (string) preg_replace( '/\/\*.*?\*\//s', '', $css );

		foreach ( self::FORBIDDEN as $pattern ) {
			if ( 1 === preg_match( $pattern, $css ) ) {
				return '';
			}
		}

		return trim( $css );
	}
}
