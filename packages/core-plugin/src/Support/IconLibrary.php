<?php
/**
 * Bundled Lucide icon library (ISC license) + user-uploaded sanitized SVGs.
 *
 * @package Blocky\Core\Support
 */

declare( strict_types=1 );

namespace Blocky\Core\Support;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

/**
 * Resolves icon tokens to inline SVG markup.
 *
 * Tokens: "lucide-{name}" (bundled set), "upload-{attachment_id}" (sanitized
 * user SVG), anything else renders as a legacy text glyph.
 */
final class IconLibrary {

	public const UPLOAD_META_KEY = '_blocky_svg';

	/** @var array<string, string>|null */
	private static ?array $manifest = null;

	/**
	 * Names of every bundled icon.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		return array_keys( self::manifest() );
	}

	/**
	 * Full manifest: icon name to pre-escaped inner SVG markup.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return self::manifest();
	}

	/**
	 * Inline SVG markup for a token, or null when the token is a legacy glyph.
	 */
	public static function markup( string $token ): ?string {
		if ( 0 === strpos( $token, 'lucide-' ) ) {
			$name   = substr( $token, 7 );
			$manifest = self::manifest();
			if ( ! isset( $manifest[ $name ] ) ) {
				return null;
			}
			return sprintf(
				'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-%1$s" aria-hidden="true">%2$s</svg>',
				esc_attr( $name ),
				$manifest[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput -- trusted bundled manifest, pre-escaped at build time.
			);
		}

		if ( preg_match( '/^upload-(\d+)$/', $token, $m ) ) {
			$saved = get_post_meta( (int) $m[1], self::UPLOAD_META_KEY, true );
			if ( ! is_string( $saved ) || '' === $saved ) {
				return null;
			}
			$safe = self::sanitize_svg( $saved );
			return null === $safe ? null : $safe;
		}

		return null;
	}

	/**
	 * Sanitizes a raw SVG document for storage: only inline-usable SVG markup
	 * survives (element/attribute allowlists, scripts and event handlers
	 * rejected outright). Returns null when nothing safe is left.
	 */
	public static function sanitize_svg( string $raw ): ?string {
		$raw = trim( $raw );
		if ( '' === $raw || strlen( $raw ) > 65536 ) {
			return null;
		}
		$raw = preg_replace( '/^\s*<\?xml[^>]*\?>\s*/', '', $raw );
		if ( ! is_string( $raw ) || false === stripos( $raw, '<svg' ) ) {
			return null;
		}
		$upper = strtoupper( $raw );
		foreach ( array( '<!DOCTYPE', '<!ENTITY', '<SCRIPT', '<IFRAME', '<EMBED', '<OBJECT', '<?', ']]>' ) as $needle ) {
			if ( false !== strpos( $upper, $needle ) ) {
				return null;
			}
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			return null;
		}

		$doc = new \DOMDocument();
		$doc->preserveWhiteSpace = false;
		if ( false === @$doc->loadXML( $raw, LIBXML_NONET | LIBXML_NOENT | LIBXML_NOWARNING | LIBXML_NOERROR ) ) {
			return null;
		}

		$allowed_elements = array_flip(
			array( 'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'title', 'desc', 'defs', 'clippath', 'mask', 'lineargradient', 'radialgradient', 'stop', 'filter', 'fecolormatrix', 'fegaussianblur', 'feoffset', 'feblend', 'fecomposite', 'femerge', 'femergenode', 'feflood', 'marker', 'text', 'tspan' )
		);
		$allowed_attrs    = array_flip(
			array( 'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'width', 'height', 'viewbox', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'stroke-miterlimit', 'fill-opacity', 'fill-rule', 'opacity', 'transform', 'points', 'offset', 'stop-color', 'stop-opacity', 'dx', 'dy', 'font-size', 'font-family', 'text-anchor', 'dominant-baseline', 'xlink:href-never', 'preserveaspectratio', 'version', 'role', 'focusable', 'aria-label', 'aria-labelledby', 'markerwidth', 'markerheight', 'markerunits', 'refx', 'refy', 'orient', 'in', 'in2', 'result', 'operator', 'values', 'stddeviation', 'dx-2', 'clip-path', 'cliprule', 'clippathunits', 'maskunits', 'spreadmethod' )
		);
		unset( $allowed_attrs['xlink:href-never'], $allowed_attrs['dx-2'] );

		$xpath = new \DOMXPath( $doc );
		foreach ( iterator_to_array( $xpath->query( '//comment()' ) ?: array() ) as $comment ) {
			$comment->parentNode?->removeChild( $comment );
		}
		foreach ( iterator_to_array( $xpath->query( '//*' ) ?: array() ) as $el ) {
			if ( ! $el instanceof \DOMElement || ! isset( $allowed_elements[ strtolower( (string) $el->localName ) ] ) ) {
				$el->parentNode?->removeChild( $el );
				continue;
			}
			foreach ( iterator_to_array( $el->attributes ) as $attr ) {
				if ( ! $attr instanceof \DOMAttr ) {
					continue;
				}
				$name = strtolower( $attr->name );
				if ( 'xmlns' !== $name && ! isset( $allowed_attrs[ $name ] ) ) {
					$el->removeAttribute( $attr->name );
					continue;
				}
				$value = strtolower( (string) $attr->value );
				if ( str_contains( $value, 'javascript:' ) || str_contains( $value, 'vbscript:' ) || str_contains( $value, 'data:' ) || str_contains( $value, 'url(' ) ) {
					$el->removeAttribute( $attr->name );
				}
			}
		}

		$out = $doc->saveXML( $doc->documentElement );
		return ( false === $out || false === stripos( $out, '<svg' ) ) ? null : $out;
	}

	/**
	 * @return array<string, string>
	 */
	private static function manifest(): array {
		if ( null === self::$manifest ) {
			$path    = BLOCKY_CORE_DIR . 'assets/icons/manifest.json';
			$raw     = is_readable( $path ) ? (string) file_get_contents( $path ) : '';
			$decoded = '' !== $raw ? json_decode( $raw, true ) : null;

			self::$manifest = is_array( $decoded )
				? array_filter( $decoded, 'is_string' )
				: array();
		}
		return self::$manifest;
	}
}
