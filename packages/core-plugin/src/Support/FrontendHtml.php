<?php
/**
 * Frontend output hardening for rendered documents.
 *
 * Defense in depth: every renderer escapes dynamic values at build time
 * (esc_attr() centrally in HtmlString::buildAttrs(), esc_html() for text,
 * esc_url() for URLs, wp_kses_post() for rich-text props). This class adds
 * a final kses gate on the HTML returned by content callbacks
 * (the_content filter, block render callbacks) so event handlers,
 * inline scripting and dangerous URLs can never reach the page even if a
 * future renderer forgets to escape.
 *
 * @package Blocky\Core\Support
 */

declare( strict_types=1 );

namespace Blocky\Core\Support;

defined( 'ABSPATH' ) || exit; // Protect against direct file access.

/**
 * Sanitizes rendered frontend HTML with wp_kses() using the Blocky markup vocabulary.
 */
final class FrontendHtml {

	/**
	 * HTML tags the engine is allowed to output.
	 *
	 * @var string[]
	 */
	private const TAGS = array(
		'a', 'abbr', 'article', 'aside', 'b', 'blockquote', 'br', 'button',
		'caption', 'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'details',
		'dfn', 'div', 'dl', 'dt', 'em', 'fieldset', 'figcaption', 'figure',
		'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr',
		'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label', 'legend', 'li',
		'main', 'mark', 'nav', 'noscript', 'ol', 'optgroup', 'option', 'p',
		'picture', 'pre', 'q', 's', 'samp', 'section', 'select', 'small',
		'source', 'span', 'strong', 'sub', 'summary', 'sup', 'table',
		'tbody', 'td', 'textarea', 'tfoot', 'th', 'thead', 'time', 'tr',
		'ul', 'var', 'video',
	);

	/**
	 * SVG tags preserved (already DOM-sanitized by IconLibrary::sanitize_svg()).
	 *
	 * @var string[]
	 */
	private const SVG_TAGS = array(
		'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline',
		'polygon', 'title', 'desc', 'defs', 'clipPath', 'mask', 'linearGradient',
		'radialGradient', 'stop', 'filter', 'feColorMatrix', 'feGaussianBlur',
		'feOffset', 'feBlend', 'feComposite', 'feMerge', 'feMergeNode',
		'feFlood', 'marker', 'text', 'tspan',
	);

	/**
	 * Attributes allowed on every tag (data-* wildcard supported by kses).
	 *
	 * @var array<string, bool>
	 */
	private const GLOBAL_ATTRS = array(
		'class' => true,
		'id' => true,
		'style' => true,
		'data-*' => true,
		'role' => true,
		'tabindex' => true,
		'hidden' => true,
		'dir' => true,
		'lang' => true,
		'title' => true,
		'aria-label' => true,
		'aria-labelledby' => true,
		'aria-describedby' => true,
		'aria-haspopup' => true,
		'aria-hidden' => true,
		'aria-modal' => true,
		'aria-selected' => true,
		'aria-controls' => true,
		'aria-expanded' => true,
		'aria-current' => true,
		'aria-live' => true,
		'aria-atomic' => true,
		'aria-busy' => true,
		'aria-checked' => true,
		'aria-disabled' => true,
		'aria-pressed' => true,
		'aria-readonly' => true,
		'aria-required' => true,
		'aria-valuenow' => true,
		'aria-valuemin' => true,
		'aria-valuemax' => true,
		'aria-multiselectable' => true,
		'aria-orientation' => true,
		'aria-roledescription' => true,
	);

	/**
	 * Extra attributes for specific HTML tags (media, forms, tables).
	 *
	 * @var array<string, array<string, bool>>
	 */
	private const TAG_ATTRS = array(
		'a' => array( 'href' => true, 'target' => true, 'rel' => true ),
		'abbr' => array(),
		'blockquote' => array( 'cite' => true ),
		'button' => array( 'type' => true, 'name' => true, 'value' => true, 'disabled' => true ),
		'col' => array( 'span' => true ),
		'colgroup' => array( 'span' => true ),
		'del' => array( 'cite' => true, 'datetime' => true ),
		'details' => array( 'open' => true ),
		'fieldset' => array( 'disabled' => true, 'name' => true ),
		'form' => array( 'action' => true, 'method' => true, 'enctype' => true, 'novalidate' => true, 'autocomplete' => true, 'target' => true ),
		'iframe' => array( 'src' => true, 'title' => true, 'width' => true, 'height' => true, 'loading' => true, 'allow' => true, 'allowfullscreen' => true, 'referrerpolicy' => true ),
		'img' => array( 'src' => true, 'srcset' => true, 'sizes' => true, 'alt' => true, 'width' => true, 'height' => true, 'loading' => true, 'decoding' => true ),
		'input' => array( 'type' => true, 'name' => true, 'value' => true, 'checked' => true, 'required' => true, 'disabled' => true, 'readonly' => true, 'placeholder' => true, 'accept' => true, 'min' => true, 'max' => true, 'step' => true, 'pattern' => true, 'multiple' => true, 'autocomplete' => true, 'list' => true, 'inputmode' => true, 'maxlength' => true ),
		'ins' => array( 'cite' => true, 'datetime' => true ),
		'label' => array( 'for' => true ),
		'legend' => array(),
		'li' => array( 'value' => true ),
		'ol' => array( 'start' => true ),
		'optgroup' => array( 'label' => true, 'disabled' => true ),
		'option' => array( 'value' => true, 'selected' => true, 'label' => true, 'disabled' => true ),
		'q' => array( 'cite' => true ),
		'select' => array( 'name' => true, 'multiple' => true, 'size' => true, 'required' => true, 'disabled' => true ),
		'source' => array( 'srcset' => true, 'sizes' => true, 'media' => true, 'src' => true, 'type' => true ),
		'table' => array( 'border' => true ),
		'td' => array( 'colspan' => true, 'rowspan' => true, 'headers' => true, 'scope' => true ),
		'textarea' => array( 'name' => true, 'rows' => true, 'cols' => true, 'placeholder' => true, 'required' => true, 'disabled' => true, 'readonly' => true, 'maxlength' => true ),
		'th' => array( 'colspan' => true, 'rowspan' => true, 'headers' => true, 'scope' => true ),
		'time' => array( 'datetime' => true ),
		'video' => array( 'src' => true, 'poster' => true, 'controls' => true, 'autoplay' => true, 'loop' => true, 'muted' => true, 'playsinline' => true, 'preload' => true, 'width' => true, 'height' => true ),
	);

	/**
	 * Attributes preserved on SVG tags (mirrors IconLibrary whitelist).
	 *
	 * @var array<string, bool>
	 */
	private const SVG_ATTRS = array(
		'd' => true, 'cx' => true, 'cy' => true, 'r' => true, 'rx' => true,
		'ry' => true, 'x' => true, 'y' => true, 'x1' => true, 'y1' => true,
		'x2' => true, 'y2' => true, 'width' => true, 'height' => true,
		'viewbox' => true, 'fill' => true, 'stroke' => true,
		'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true,
		'stroke-dasharray' => true, 'stroke-dashoffset' => true,
		'stroke-opacity' => true, 'stroke-miterlimit' => true, 'fill-opacity' => true,
		'fill-rule' => true, 'opacity' => true, 'transform' => true,
		'points' => true, 'offset' => true, 'stop-color' => true,
		'stop-opacity' => true, 'dx' => true, 'dy' => true, 'font-size' => true,
		'font-family' => true, 'text-anchor' => true, 'dominant-baseline' => true,
		'preserveaspectratio' => true, 'version' => true, 'focusable' => true,
		'xmlns' => true, 'href' => true, 'markerwidth' => true,
		'markerheight' => true, 'markerunits' => true, 'refx' => true,
		'refy' => true, 'orient' => true, 'type' => true,
	);

	/**
	 * Cached allow-list built from the constants above.
	 *
	 * @var array<string, array<string, bool>>|null
	 */
	private static ?array $allowed = null;

	/**
	 * Final kses gate for fully rendered frontend HTML.
	 *
	 * @param string $html Rendered markup (values already escaped at build time).
	 * @return string Hardened markup safe for echo/return on the frontend.
	 */
	public static function sanitize( string $html ): string {
		if ( '' === $html ) {
			return '';
		}
		return \wp_kses( $html, self::allowed_html() );
	}

	/**
	 * kses allow-list for Blocky frontend markup.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_html(): array {
		if ( null !== self::$allowed ) {
			return self::$allowed;
		}
		$allowed = array();
		foreach ( self::TAGS as $tag ) {
			$attrs = self::GLOBAL_ATTRS;
			if ( isset( self::TAG_ATTRS[ $tag ] ) ) {
				$attrs = array_merge( $attrs, self::TAG_ATTRS[ $tag ] );
			}
			$allowed[ $tag ] = $attrs;
		}
		foreach ( self::SVG_TAGS as $tag ) {
			$allowed[ strtolower( $tag ) ] = array_merge( self::GLOBAL_ATTRS, self::SVG_ATTRS );
		}
		/**
		 * Extends or overrides the frontend kses allow-list.
		 *
		 * @param array<string, array<string, bool>> $allowed Map of tag => allowed attributes.
		 */
		self::$allowed = apply_filters( 'blocky_frontend_html_allowed_html', $allowed );
		return self::$allowed;
	}
}
