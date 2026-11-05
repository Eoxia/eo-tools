<?php
/**
 * SVG sanitizer for images drawn by the AI.
 *
 * An SVG file can carry scripts. Only drawing elements and attributes are kept
 * (allowlist): scripts, event handlers, external references and XML entities
 * are removed or rejected.
 *
 * @package EoTools
 */

namespace EoTools\Includes\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eotools_Mcp_Svg {

	const MAX_SIZE = 1048576;

	const ELEMENTS = array(
		'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'textPath',
		'defs', 'linearGradient', 'radialGradient', 'stop', 'clipPath', 'mask', 'pattern', 'symbol', 'use', 'title',
		'desc', 'style', 'filter', 'feGaussianBlur', 'feOffset', 'feBlend', 'feColorMatrix', 'feMerge', 'feMergeNode',
		'feFlood', 'feComposite', 'feDropShadow', 'feTurbulence', 'feDisplacementMap', 'feMorphology',
		'feComponentTransfer', 'feFuncA', 'feFuncR', 'feFuncG', 'feFuncB',
	);

	/**
	 * Returns a safe version of an SVG document.
	 *
	 * @param string $svg SVG markup.
	 * @return array|\WP_Error { svg: string, width: int, height: int }
	 */
	public static function sanitize( $svg ) {
		$svg = trim( (string) $svg );

		if ( '' === $svg || strlen( $svg ) > self::MAX_SIZE ) {
			return new \WP_Error( 'eo_tools_svg_size', __( 'SVG vide ou trop volumineux (1 Mo maximum).', 'eo-tools' ) );
		}
		if ( false !== stripos( $svg, '<!DOCTYPE' ) || false !== stripos( $svg, '<!ENTITY' ) ) {
			return new \WP_Error( 'eo_tools_svg_doctype', __( 'Les SVG avec DOCTYPE ou entités ne sont pas acceptés.', 'eo-tools' ) );
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$loaded = $dom->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();

		if ( ! $loaded || ! $dom->documentElement || 'svg' !== $dom->documentElement->localName ) {
			return new \WP_Error( 'eo_tools_svg_invalid', __( 'Le code fourni n\'est pas un SVG valide.', 'eo-tools' ) );
		}

		self::clean_node( $dom->documentElement );

		$root = $dom->documentElement;
		if ( ! $root->hasAttribute( 'xmlns' ) ) {
			$root->setAttribute( 'xmlns', 'http://www.w3.org/2000/svg' );
		}

		// Without explicit dimensions, an SVG in an <img> tag may render tiny.
		list( $width, $height ) = self::get_dimensions( $root );
		if ( ! $root->hasAttribute( 'width' ) && $width ) {
			$root->setAttribute( 'width', (string) $width );
		}
		if ( ! $root->hasAttribute( 'height' ) && $height ) {
			$root->setAttribute( 'height', (string) $height );
		}

		return array(
			'svg'    => '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $dom->saveXML( $root ),
			'width'  => $width,
			'height' => $height,
		);
	}

	/**
	 * Removes forbidden elements and attributes, recursively.
	 *
	 * @param \DOMElement $element Element.
	 */
	private static function clean_node( $element ) {
		foreach ( iterator_to_array( $element->childNodes ) as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				if ( ! in_array( $child->localName, self::ELEMENTS, true ) || ( 'style' === $child->localName && ! self::is_safe_css( $child->textContent ) ) ) {
					$element->removeChild( $child );
					continue;
				}
				self::clean_node( $child );
			} elseif ( XML_PI_NODE === $child->nodeType || XML_COMMENT_NODE === $child->nodeType ) {
				$element->removeChild( $child );
			}
		}

		foreach ( iterator_to_array( $element->attributes ) as $attribute ) {
			$name  = strtolower( $attribute->nodeName );
			$value = trim( $attribute->nodeValue );

			$unsafe = 0 === strpos( $name, 'on' )
				|| ( ( 'href' === $attribute->localName ) && 0 !== strpos( $value, '#' ) )
				|| preg_match( '/javascript:|vbscript:|expression\s*\(/i', $value )
				|| ( 'style' === $name && ! self::is_safe_css( $value ) )
				|| ( preg_match( '/url\s*\(/i', $value ) && ! preg_match( '/^url\s*\(\s*[\'"]?#/i', $value ) );

			if ( $unsafe ) {
				$element->removeAttributeNode( $attribute );
			}
		}
	}

	/**
	 * CSS without imports, scripts or external resources.
	 */
	private static function is_safe_css( $css ) {
		if ( preg_match( '/@import|javascript:|expression\s*\(|<\//i', $css ) ) {
			return false;
		}

		// url() is only allowed for internal references (gradients, filters, clip paths).
		preg_match_all( '/url\s*\(\s*[\'"]?([^\'")]*)/i', $css, $urls );
		foreach ( $urls[1] as $url ) {
			if ( 0 !== strpos( trim( $url ), '#' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Width and height from the attributes or the viewBox.
	 *
	 * @param \DOMElement $root SVG element.
	 * @return int[]
	 */
	private static function get_dimensions( $root ) {
		$width  = (int) $root->getAttribute( 'width' );
		$height = (int) $root->getAttribute( 'height' );

		if ( ( ! $width || ! $height ) && preg_match( '/^\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*$/', $root->getAttribute( 'viewBox' ), $box ) ) {
			$width  = $width ? $width : (int) round( (float) $box[1] );
			$height = $height ? $height : (int) round( (float) $box[2] );
		}

		return array( $width, $height );
	}
}
