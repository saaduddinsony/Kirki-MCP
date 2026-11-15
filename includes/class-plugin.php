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
		Admin::register();
	}
}
