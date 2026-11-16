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
	 * Head custom code: one or more style blocks, and nothing else.
	 *
	 * @param string $html Raw head code.
	 * @return string
	 */
	public static function head_style_blocks( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return '';
		}
		if ( strlen( $html ) > 20000 ) {
			throw new \InvalidArgumentException( 'Custom code is limited to 20,000 characters.' );
		}
		$pattern = '/<style((?:\s+(?:type|media)\s*=\s*(?:"[^"<>]*"|\'[^\'<>]*\'))*)\s*>(.*?)<\/style>/is';
		if ( ! preg_match_all( $pattern, $html, $matches ) ) {
			throw new \InvalidArgumentException( 'Head code must be one or more <style> blocks.' );
		}
		$remainder = preg_replace( $pattern, '', $html );
		if ( '' !== trim( (string) $remainder ) ) {
			throw new \InvalidArgumentException( 'Head code must be one or more <style> blocks. Other markup was found outside a style block.' );
		}
		$out = '';
		foreach ( $matches[2] as $index => $css ) {
			$out .= '<style' . self::style_attributes( $matches[1][ $index ] ) . '>' . self::sanitize_stylesheet( $css ) . '</style>';
		}
		return $out;
	}

	/**
	 * Keep only a safe media attribute on a style tag.
	 *
	 * @param string $raw Attribute string captured from the tag.
	 * @return string
	 */
	private static function style_attributes( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( ! preg_match( '/^(?:(?:type|media)\s*=\s*(?:"[^"<>]*"|\'[^\'<>]*\')\s*)+$/i', $raw ) ) {
			throw new \InvalidArgumentException( 'A style tag may only use a media attribute.' );
		}
		preg_match_all( '/(type|media)\s*=\s*(?:"([^"<>]*)"|\'([^\'<>]*)\')/i', $raw, $found, PREG_SET_ORDER );
		if ( ! $found ) {
			throw new \InvalidArgumentException( 'A style tag may only use a media attribute.' );
		}
		$media = '';
		foreach ( $found as $attr ) {
			$name  = strtolower( $attr[1] );
			$value = '' !== $attr[2] ? $attr[2] : $attr[3];
			if ( 'type' === $name ) {
				if ( 'text/css' !== strtolower( $value ) ) {
					throw new \InvalidArgumentException( 'A style tag type must be text/css.' );
				}
				continue;
			}
			if ( ! preg_match( '/^[A-Za-z0-9 (),:.-]+$/', $value ) ) {
				throw new \InvalidArgumentException( 'A style media value may only contain letters, numbers, spaces, and CSS media punctuation.' );
			}
			$media = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
		}
		if ( '' === $media ) {
			return '';
		}
		return ' media="' . $media . '"';
	}

	/**
	 * CSS for a style element. Selectors and braces are allowed. Breakouts are not.
	 *
	 * @param string $css Stylesheet text.
	 * @return string
	 */
	public static function sanitize_stylesheet( $css ) {
		$css = trim( (string) $css );
		if ( '' === $css ) {
			throw new \InvalidArgumentException( 'A style block is empty.' );
		}
		if ( preg_match( '/<|@import|@charset|@namespace|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding|\\\\/i', $css ) ) {
			throw new \InvalidArgumentException( 'That style block was rejected.' );
		}
		if ( preg_match_all( '/url\(\s*([\'"]?)(.*?)\1\s*\)/i', $css, $matches ) ) {
			foreach ( $matches[2] as $url ) {
				if ( ! self::is_safe_css_url( $url ) ) {
					throw new \InvalidArgumentException( 'CSS url() values must be http(s) or site-relative paths.' );
				}
			}
		}
		return $css;
	}

	/**
	 * A Google Fonts stylesheet built from a family name, or a URL that matches that host and path.
	 *
	 * @param string $family Family name.
	 * @param string $url Optional caller URL. Rejected unless it is a fonts.googleapis.com /css or /css2 URL with no markup.
	 * @return string
	 */
	public static function google_font_url( $family, $url = '' ) {
		$family = trim( (string) $family );
		if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9 -]{0,60}$/', $family ) ) {
			throw new \InvalidArgumentException( 'Font family names may contain letters, numbers, spaces, and hyphens.' );
		}
		$built = 'https://fonts.googleapis.com/css2?family=' . str_replace( ' ', '+', $family ) . '&display=swap';
		$url   = trim( (string) $url );
		if ( '' === $url ) {
			return $built;
		}
		if ( preg_match( '/["\'<>\s\\\\]/', $url ) ) {
			throw new \InvalidArgumentException( 'That font URL was rejected.' );
		}
		$parts = wp_parse_url( $url );
		$path  = is_array( $parts ) && isset( $parts['path'] ) ? $parts['path'] : '';
		if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' || strtolower( (string) ( $parts['host'] ?? '' ) ) !== 'fonts.googleapis.com' ) {
			throw new \InvalidArgumentException( 'Only https://fonts.googleapis.com stylesheets can be registered.' );
		}
		if ( '/css' !== $path && '/css2' !== $path ) {
			throw new \InvalidArgumentException( 'A Google Fonts URL must use the /css or /css2 path.' );
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) || isset( $parts['fragment'] ) ) {
			throw new \InvalidArgumentException( 'That font URL was rejected.' );
		}
		$query = isset( $parts['query'] ) ? (string) $parts['query'] : '';
		if ( '' !== $query && ! preg_match( '/^[A-Za-z0-9=&+,:;@.%_-]+$/', $query ) ) {
			throw new \InvalidArgumentException( 'That font URL was rejected.' );
		}
		$safe = 'https://fonts.googleapis.com' . $path . ( '' !== $query ? '?' . $query : '' );
		if ( function_exists( 'esc_url_raw' ) ) {
			$safe = esc_url_raw( $safe );
		}
		if ( ! is_string( $safe ) || 0 !== strpos( $safe, 'https://fonts.googleapis.com/' ) ) {
			throw new \InvalidArgumentException( 'That font URL was rejected.' );
		}
		return $safe;
	}

	/**
	 * Make a stored font URL safe to drop into an HTML attribute.
	 *
	 * Kirki prints fontUrl without escaping. This is applied on the way out
	 * for values already saved by an older plugin version or by Kirki itself.
	 *
	 * @param string $url Stored URL.
	 * @return string
	 */
	public static function escape_font_url( $url ) {
		$url = str_replace( array( '&#038;', '&amp;', '&#38;' ), '&', (string) $url );
		if ( function_exists( 'esc_url_raw' ) ) {
			$safe = esc_url_raw( $url );
			return is_string( $safe ) ? $safe : '';
		}
		return preg_replace( '/["\'<>\s\\\\]/', '', $url );
	}

	/**
	 * Escape both URLs Kirki may print for one custom font.
	 *
	 * @param array $font Font record.
	 * @return array
	 */
	public static function escape_font_record( array $font ) {
		foreach ( array( 'fontUrl', 'localUrl' ) as $field ) {
			if ( isset( $font[ $field ] ) && is_string( $font[ $field ] ) ) {
				$font[ $field ] = self::escape_font_url( $font[ $field ] );
			}
		}
		return $font;
	}

	/**
	 * Remove shortcode brackets from text Kirki will pass through do_shortcode.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function plain_meta( $text ) {
		$text = preg_replace( '/\[[^\]]*\]/', '', (string) $text );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		return $text;
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
			return self::attribute_url( $url );
		}

		if ( '#' === $url[0] ) {
			$url = self::attribute_url( $url );
			if ( ! preg_match( '/^#[A-Za-z0-9_%.-]+$/', $url ) ) {
				throw new \InvalidArgumentException( 'Fragment links must look like #section-name.' );
			}
			return $url;
		}

		$clean = $url;
		if ( function_exists( 'esc_url_raw' ) ) {
			$clean = esc_url_raw( $url );
			if ( ! is_string( $clean ) || '' === $clean ) {
				throw new \InvalidArgumentException( 'That URL was rejected.' );
			}
		} elseif ( ! preg_match( '#^(https?:)?//#i', $url ) && ! preg_match( '#^/#', $url ) ) {
			throw new \InvalidArgumentException( 'URLs must be absolute http(s) addresses or site-relative paths.' );
		}

		if ( preg_match( '/javascript\s*:/i', $clean ) ) {
			throw new \InvalidArgumentException( 'That URL was rejected.' );
		}

		// esc_url_raw keeps apostrophes. Kirki prints link-text hrefs inside single quotes.
		return self::attribute_url( $clean );
	}

	/**
	 * Percent-encode characters that break out of a quoted href.
	 *
	 * Safe to call more than once: encoded characters are not encoded again.
	 *
	 * @param string $url URL that may already have been through esc_url_raw.
	 * @return string
	 */
	public static function attribute_url( $url ) {
		return strtr(
			(string) $url,
			array(
				"'"  => '%27',
				'"'  => '%22',
				'`'  => '%60',
				'<'  => '%3C',
				'>'  => '%3E',
				' '  => '%20',
				"\t" => '%09',
				"\n" => '%0A',
				"\r" => '%0D',
			)
		);
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
