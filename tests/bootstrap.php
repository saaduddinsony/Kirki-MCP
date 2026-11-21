<?php
/**
 * Loads the pure compiler classes without WordPress.
 *
 * @package KirkiMCP
 */

define( 'ABSPATH', __DIR__ . '/' );
if ( ! defined( 'KIRKI_MCP_VERSION' ) ) {
	define( 'KIRKI_MCP_VERSION', '0.3.0' );
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Test stand-in for the WordPress function.
	 *
	 * @param string $url URL.
	 * @return array|false
	 */
	function wp_parse_url( $url ) {
		return parse_url( $url );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Test stand-in for the WordPress function.
	 *
	 * @param mixed $data Data.
	 * @param int   $options Options.
	 * @param int   $depth Depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Stub for the unit suite.
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Test stand-in for the WordPress function.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function wp_strip_all_tags( $text ) {
		return strip_tags( $text );
	}
}

require dirname( __DIR__ ) . '/includes/class-css.php';
require dirname( __DIR__ ) . '/includes/class-json-data.php';
require dirname( __DIR__ ) . '/includes/class-ids.php';
require dirname( __DIR__ ) . '/includes/class-spec-compiler.php';
require dirname( __DIR__ ) . '/includes/class-document.php';
require dirname( __DIR__ ) . '/includes/class-symbol-repository.php';
require dirname( __DIR__ ) . '/includes/class-page-repository.php';
require dirname( __DIR__ ) . '/includes/class-global-repository.php';
require dirname( __DIR__ ) . '/includes/class-plugin.php';
require dirname( __DIR__ ) . '/includes/class-compatibility.php';
require dirname( __DIR__ ) . '/includes/class-ecommerce-repository.php';
require dirname( __DIR__ ) . '/includes/class-customizer-repository.php';
require dirname( __DIR__ ) . '/includes/class-collection-repository.php';
