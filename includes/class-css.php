<?php
/**
 * CSS and URL sanitizers for agent-supplied design values.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rejects CSS that could break out of a style rule.
 */
class Css {

	const MAX_LENGTH = 8000;

	/**
	 * Sanitize one CSS declaration list.
	 *
	 * @param string $css Raw declarations.
	 * @return string
	 * @throws \InvalidArgumentException When the CSS is empty after trim is allowed; throws when unsafe.
	 */
	public static function sanitize( $css ) {
		$css = trim( (string) $css );
		if ( '' === $css ) {
			return '';
		}

		if ( strlen( $css ) > self::MAX_LENGTH ) {
			throw new \InvalidArgumentException( 'A CSS variant is longer than ' . self::MAX_LENGTH . ' characters.' );
		}

		if ( preg_match( '/[<>]|expression\s*\(|javascript\s*:|@import|@charset|behavior\s*:|-moz-binding|<\/?\s*style/i', $css ) ) {
			throw new \InvalidArgumentException( 'That CSS was rejected because it contains an unsafe construct.' );
		}

		if ( preg_match_all( '/url\(\s*([\'"]?)(.*?)\1\s*\)/i', $css, $matches ) ) {
			foreach ( $matches[2] as $url ) {
				if ( ! self::is_safe_css_url( $url ) ) {
					throw new \InvalidArgumentException( 'CSS url() values must be http(s) or site-relative paths.' );
				}
			}
		}

		$css = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $css );
		if ( ';' !== substr( $css, -1 ) ) {
			$css .= ';';
		}

		return $css;
	}

	/**
	 * Normalize a style-block variant key such as md or tablet_hover.
	 *
	 * @param string $key Variant key.
	 * @return string
	 */
	public static function variant_key( $key ) {
		$key = strtolower( trim( (string) $key ) );
		if ( ! preg_match( '/^[a-z][a-z0-9]{0,20}(_[a-z][a-z0-9]{0,20}){0,2}$/', $key ) ) {
			throw new \InvalidArgumentException( 'Unsupported style variant "' . $key . '". Use md, tablet, mobile, and optional _hover or _active suffixes.' );
		}
		return $key;
	}

	/**
	 * Sanitize a link or image URL. Fragments and site-relative paths are kept.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public static function url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		if ( '#' === $url[0] ) {
			if ( ! preg_match( '/^#[A-Za-z0-9_-]+$/', $url ) ) {
				throw new \InvalidArgumentException( 'Fragment links must look like #section-name.' );
			}
			return $url;
		}

		if ( function_exists( 'esc_url_raw' ) ) {
			$clean = esc_url_raw( $url );
			if ( ! is_string( $clean ) || '' === $clean ) {
				throw new \InvalidArgumentException( 'That URL was rejected.' );
			}
			return $clean;
		}

		if ( preg_match( '#^(https?:)?//#i', $url ) || preg_match( '#^/#', $url ) ) {
			if ( preg_match( '/[\s<>"\']|javascript\s*:/i', $url ) ) {
				throw new \InvalidArgumentException( 'That URL was rejected.' );
			}
			return $url;
		}

		throw new \InvalidArgumentException( 'URLs must be absolute http(s) addresses or site-relative paths.' );
	}

	/**
	 * Sanitize a color for a global variable.
	 *
	 * @param string $value Color.
	 * @return string
	 */
	public static function color( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
			return strtolower( $value );
		}
		if ( preg_match( '/^rgba?\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value ) ) {
			return $value;
		}
		throw new \InvalidArgumentException( 'Colors must be hex or rgb()/rgba().' );
	}

	/**
	 * Sanitize a font-family name.
	 *
	 * @param string $value Font family.
	 * @return string
	 */
	public static function font_family( $value ) {
		$value = trim( preg_replace( '/\s+/', ' ', (string) $value ) );
		if ( '' === $value || strlen( $value ) > 80 || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9 ,._-]*$/', $value ) ) {
			throw new \InvalidArgumentException( 'Font families may contain letters, numbers, spaces, commas, and hyphens.' );
		}
		return $value;
	}

	/**
	 * Whether a CSS url() target is allowed.
	 *
	 * @param string $url URL inside url().
	 * @return bool
	 */
	private static function is_safe_css_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || preg_match( '/javascript\s*:|data\s*:/i', $url ) ) {
			return false;
		}
		return (bool) preg_match( '#^(https?:)?//[^\s\'"()]+$#i', $url ) || (bool) preg_match( '#^/[^\s\'"()]*$#', $url );
	}
}
