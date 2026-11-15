<?php
/**
 * Loads the pure compiler classes without WordPress.
 *
 * @package KirkiMCP
 */

define( 'ABSPATH', __DIR__ . '/' );

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
