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

		if ( preg_match( '/[{}\\\\@]|\/\*|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding|<\/?\s*style/i', $css ) ) {
			throw new \InvalidArgumentException( 'That CSS was rejected. Use declarations only, such as color:#fff;padding:24px. Braces, @-rules, and backslashes are not allowed.' );
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
	 * Viewport ids Kirki will emit, plus the pseudos it appends.
	 *
	 * @return array{viewports:string[],pseudos:string[]}
	 */
	public static function variant_vocabulary() {
		$viewports = array( 'md', 'tablet', 'mobile' );
		if ( class_exists( '\Kirki\App\Supports\Facades\GlobalData' ) ) {
			$list = \Kirki\App\Supports\Facades\GlobalData::get_view_port_list();
			if ( is_array( $list ) && $list ) {
				$viewports = array();
				foreach ( $list as $id => $viewport ) {
					$name = is_string( $id ) ? $id : '';
					if ( is_array( $viewport ) && ! empty( $viewport['id'] ) ) {
						$name = (string) $viewport['id'];
					}
					if ( preg_match( '/^[a-z][a-z0-9]*$/', $name ) ) {
						$viewports[] = $name;
					}
				}
			}
		}
		if ( ! in_array( 'md', $viewports, true ) ) {
			$viewports[] = 'md';
		}
		return array(
			'viewports' => array_values( array_unique( $viewports ) ),
			'pseudos'   => array( 'hover', 'active', 'focus', 'before', 'after', 'placeholder' ),
		);
	}

	/**
	 * Normalize a style-block variant key such as md or tablet_hover.
	 *
	 * @param string $key Variant key.
	 * @return string
	 */
	public static function variant_key( $key ) {
		$key        = strtolower( trim( (string) $key ) );
		$vocabulary = self::variant_vocabulary();
		$parts      = explode( '_', $key );
		$viewport   = array_shift( $parts );
		if ( ! in_array( $viewport, $vocabulary['viewports'], true ) ) {
			throw new \InvalidArgumentException( 'Unsupported viewport "' . $viewport . '". Use ' . implode( ', ', $vocabulary['viewports'] ) . '.' );
		}
		if ( count( $parts ) > 2 ) {
			throw new \InvalidArgumentException( 'A style variant can have at most two pseudo suffixes.' );
		}
		foreach ( $parts as $pseudo ) {
			if ( ! in_array( $pseudo, $vocabulary['pseudos'], true ) ) {
				throw new \InvalidArgumentException( 'Unsupported pseudo "' . $pseudo . '". Use ' . implode( ', ', $vocabulary['pseudos'] ) . '.' );
			}
		}
		return $key;
	}

	/**
	 * A CSS font-family list. Quoted families pass through Kirki unchanged.
	 *
	 * @param string $value Font stack.
	 * @return string
	 */
	public static function font_stack( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || strlen( $value ) > 180 || preg_match( '/[{}<>\\\\@;]/', $value ) ) {
			throw new \InvalidArgumentException( 'Font stacks may list family names separated by commas.' );
		}
		$families = self::split_font_list( $value );
		if ( ! $families ) {
			throw new \InvalidArgumentException( 'A font stack needs at least one family.' );
		}
		$generic = array( 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-sans-serif', 'ui-serif', 'ui-monospace', 'emoji', 'math', 'fangsong' );
		$out     = array();
		foreach ( $families as $family ) {
			$family = trim( $family, " \t\"'" );
			if ( '' === $family || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/', $family ) ) {
				throw new \InvalidArgumentException( 'A font family name contains unsupported characters.' );
			}
			if ( in_array( strtolower( $family ), $generic, true ) ) {
				$out[] = strtolower( $family );
			} else {
				$out[] = '"' . $family . '"';
			}
		}
		return implode( ', ', $out );
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

		if ( preg_match( '/^(mailto:|tel:)/i', $url ) ) {
			if ( preg_match( '/[\s<>"\']/', $url ) ) {
				throw new \InvalidArgumentException( 'That URL was rejected.' );
			}
			return $url;
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
	 * Sanitize a font-family name or stack.
	 *
	 * @param string $value Font family.
	 * @return string
	 */
	public static function font_family( $value ) {
		return self::font_stack( $value );
	}

	/**
	 * Split a font list on commas that are not inside quotes.
	 *
	 * @param string $value Font list.
	 * @return string[]
	 */
	private static function split_font_list( $value ) {
		$parts  = array();
		$buffer = '';
		$quote  = '';
		$length = strlen( $value );
		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];
			if ( '' === $quote && ( '"' === $char || "'" === $char ) ) {
				$quote = $char;
				continue;
			}
			if ( '' !== $quote && $char === $quote ) {
				$quote = '';
				continue;
			}
			if ( '' === $quote && ',' === $char ) {
				$parts[] = $buffer;
				$buffer  = '';
				continue;
			}
			$buffer .= $char;
		}
		if ( '' !== $quote ) {
			throw new \InvalidArgumentException( 'A font stack has an unterminated quote.' );
		}
		$parts[] = $buffer;
		return $parts;
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
