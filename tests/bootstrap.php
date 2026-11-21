<?php
/**
 * Loads the pure compiler classes without WordPress.
 *
 * @package KirkiMCP
 */

define( 'ABSPATH', __DIR__ . '/' );
if ( ! defined( 'KIRKI_MCP_VERSION' ) ) {
	define( 'KIRKI_MCP_VERSION', '0.4.0' );
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

if ( ! isset( $GLOBALS['kirki_mcp_test_options'] ) ) {
	$GLOBALS['kirki_mcp_test_options'] = array();
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Test stand-in for the WordPress function.
	 *
	 * @param string $name Name.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	function get_option( $name, $default_value = false ) {
		return array_key_exists( $name, $GLOBALS['kirki_mcp_test_options'] ) ? $GLOBALS['kirki_mcp_test_options'][ $name ] : $default_value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Test stand-in for the WordPress function.
	 *
	 * @param string $name Name.
	 * @param mixed  $value Value.
	 * @param mixed  $autoload Autoload (unused).
	 * @return bool
	 */
	function update_option( $name, $value, $autoload = true ) {
		unset( $autoload );
		$GLOBALS['kirki_mcp_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * Test stand-in for the WordPress function.
	 *
	 * @return int
	 */
	function get_current_user_id() {
		return 0;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Test stand-in for the WordPress function.
	 *
	 * @param string $cap Capability.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		unset( $cap );
		return ! empty( $GLOBALS['kirki_mcp_test_can'] );
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
require dirname( __DIR__ ) . '/includes/class-access.php';
require dirname( __DIR__ ) . '/includes/class-audit.php';
require dirname( __DIR__ ) . '/includes/class-dynamic-content-repository.php';
require dirname( __DIR__ ) . '/includes/class-interaction-repository.php';
require dirname( __DIR__ ) . '/includes/class-customizer-registry.php';
require dirname( __DIR__ ) . '/includes/class-settings-repository.php';
require dirname( __DIR__ ) . '/includes/class-template-repository.php';
