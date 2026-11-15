<?php
/**
 * Plugin bootstrap.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires hooks.
 */
class Plugin {

	/**
	 * Register runtime hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'wp_abilities_api_categories_init', array( Abilities::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( Abilities::class, 'register' ) );
		add_filter( 'get_post_metadata', array( self::class, 'escape_stored_font_urls' ), 10, 4 );
		Admin::register();
	}

	/**
	 * Escape font stylesheet URLs Kirki will print into a link href.
	 *
	 * Returning null leaves the normal meta lookup in place.
	 *
	 * @param mixed  $value Short-circuit value.
	 * @param int    $object_id Post id.
	 * @param string $meta_key Meta key.
	 * @param bool   $single Whether a single value was requested.
	 * @return mixed
	 */
	public static function escape_stored_font_urls( $value, $object_id, $meta_key, $single ) {
		if ( 'kirki_user_custom_fonts' !== $meta_key || null !== $value ) {
			return $value;
		}
		remove_filter( 'get_post_metadata', array( self::class, 'escape_stored_font_urls' ), 10 );
		$fonts = get_post_meta( (int) $object_id, 'kirki_user_custom_fonts', true );
		add_filter( 'get_post_metadata', array( self::class, 'escape_stored_font_urls' ), 10, 4 );
		if ( ! is_array( $fonts ) ) {
			return $value;
		}
		foreach ( $fonts as $key => $font ) {
			if ( is_array( $font ) && isset( $font['fontUrl'] ) && is_string( $font['fontUrl'] ) ) {
				$fonts[ $key ]['fontUrl'] = Css::escape_font_url( $font['fontUrl'] );
			}
		}
		// WordPress returns index 0 when $single is true, and the list otherwise.
		if ( $single ) {
			return array( $fonts );
		}
		return array( $fonts );
	}
}
