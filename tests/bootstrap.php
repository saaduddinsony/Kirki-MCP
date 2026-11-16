<?php
/**
 * Loads the pure compiler classes without WordPress.
 *
 * @package KirkiMCP
 */

define( 'ABSPATH', __DIR__ . '/' );

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
