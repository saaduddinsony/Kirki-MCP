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
		add_filter( 'get_post_metadata', array( self::class, 'escape_stored_links' ), 10, 4 );
		add_action( 'admin_init', array( self::class, 'upgrade' ) );
		add_action( 'kirki_mcp_refresh_symbol_classes', array( Global_Repository::class, 'refresh_symbol_batch' ) );
		Admin::register();
	}

	/**
	 * Activation and version upgrades. Not run for anonymous front-end requests.
	 *
	 * @return void
	 */
	public static function activate() {
		self::run_migrations();
		if ( function_exists( 'update_option' ) ) {
			update_option( 'kirki_mcp_version', KIRKI_MCP_VERSION );
		}
	}

	/**
	 * Run stored-data migrations when an administrator loads wp-admin after an upgrade.
	 *
	 * @return void
	 */
	public static function upgrade() {
		self::migrate_if_needed();
	}

	/**
	 * Migrate once from an authorized request. The flags are autoloaded.
	 *
	 * A failure still sets the flag so a bad record cannot break every later request.
	 *
	 * @return void
	 */
	public static function migrate_if_needed() {
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}
		$symbols = (string) get_option( 'kirki_mcp_symbol_root_ids', '' );
		$urls    = (string) get_option( 'kirki_mcp_stored_urls', '' );
		$version = (string) get_option( 'kirki_mcp_version', '' );
		if ( '1' === $symbols && '1' === $urls && KIRKI_MCP_VERSION === $version ) {
			return;
		}
		$allowed = false;
		if ( class_exists( Access::class ) && Access::can_build() ) {
			$allowed = true;
		}
		if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
			$allowed = true;
		}
		if ( ! $allowed ) {
			return;
		}
		self::run_migrations();
		update_option( 'kirki_mcp_version', KIRKI_MCP_VERSION );
	}

	/**
	 * @return void
	 */
	public static function run_migrations() {
		Symbol_Repository::migrate_plugin_symbols();
		Page_Repository::migrate_stored_urls();
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
	public static function escape_stored_font_urls( $value, $object_id, $meta_key, $single ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress passes $single; the return shape covers both.
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
			if ( is_array( $font ) ) {
				$fonts[ $key ] = Css::escape_font_record( $font );
			}
		}
		// WordPress returns index 0 of this list when $single is true.
		return array( $fonts );
	}

	/**
	 * Encode quotes in hrefs Kirki will print raw, including values already stored.
	 *
	 * Returning null leaves the normal meta lookup in place. Failures leave
	 * the original value so a bad record cannot take the site down.
	 *
	 * @param mixed  $value Short-circuit value.
	 * @param int    $object_id Post id.
	 * @param string $meta_key Meta key.
	 * @param bool   $single Whether a single value was requested.
	 * @return mixed
	 */
	public static function escape_stored_links( $value, $object_id, $meta_key, $single ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress passes $single; the return shape covers both.
		if ( 'kirki' !== $meta_key || null !== $value ) {
			return $value;
		}
		remove_filter( 'get_post_metadata', array( self::class, 'escape_stored_links' ), 10 );
		try {
			$meta = get_post_meta( (int) $object_id, 'kirki', true );
			if ( ! is_array( $meta ) ) {
				return $value;
			}
			return array( Document::harden_kirki_meta( $meta ) );
		} catch ( \Throwable $error ) {
			unset( $error );
			return $value;
		} finally {
			add_filter( 'get_post_metadata', array( self::class, 'escape_stored_links' ), 10, 4 );
		}
	}
}
