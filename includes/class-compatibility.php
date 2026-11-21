<?php
/**
 * Supported Kirki version range.
 *
 * The page, stage, and symbol code talks to Kirki 6.4 internals. A later 6.x
 * release can change them, so the default range is the tested 6.4 line.
 * kirki_mcp_supported_kirki_range can widen it for a site that has verified
 * a newer build.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version gate.
 */
class Compatibility {

	/**
	 * Inclusive minimum and exclusive maximum.
	 *
	 * @return array{0:string,1:string}
	 */
	public static function range() {
		$range = apply_filters( 'kirki_mcp_supported_kirki_range', array( '6.4.0', '6.5.0' ) );
		if ( ! is_array( $range ) || ! isset( $range[0], $range[1] ) ) {
			return array( '6.4.0', '6.5.0' );
		}
		return array( (string) $range[0], (string) $range[1] );
	}

	/**
	 * Human-readable reason this site cannot use the builder abilities.
	 *
	 * @return string Empty when the installed Kirki is supported.
	 */
	public static function unsupported_reason() {
		list( $min, $max ) = self::range();
		if ( ! defined( 'KIRKI_VERSION' ) ) {
			return 'The Kirki plugin is not active. Kirki MCP ' . KIRKI_MCP_VERSION . ' needs Kirki ' . $min . ' up to, but not including, ' . $max . '.';
		}
		if ( version_compare( KIRKI_VERSION, $min, '<' ) || version_compare( KIRKI_VERSION, $max, '>=' ) ) {
			return 'Kirki MCP ' . KIRKI_MCP_VERSION . ' supports Kirki ' . $min . ' up to, but not including, ' . $max . '. This site is running Kirki ' . KIRKI_VERSION . '.';
		}
		$missing = self::missing_classes();
		if ( $missing ) {
			return 'Kirki ' . KIRKI_VERSION . ' is missing ' . implode( ', ', $missing ) . '. Kirki MCP was tested against Kirki 6.4.2.';
		}
		return '';
	}

	/**
	 * @return bool
	 */
	public static function supported() {
		return '' === self::unsupported_reason();
	}

	/**
	 * Kirki classes this plugin calls directly.
	 *
	 * @return string[] Missing class names.
	 */
	public static function missing_classes() {
		$required = array(
			'\Kirki\App\DTO\Page\PagePayloadDTO',
			'\Kirki\App\Services\PageService',
			'\Kirki\App\Supports\Facades\Page',
			'\Kirki\App\Supports\Facades\GlobalData',
			'\Kirki\App\Managers\SymbolManager',
			'\Kirki\App\DTO\Symbol\SymbolDTO',
			'\Kirki\App\Constants\PageMetaKeys',
		);
		$missing  = array();
		foreach ( $required as $class ) {
			if ( ! class_exists( $class ) ) {
				$missing[] = $class;
			}
		}
		return $missing;
	}
}
