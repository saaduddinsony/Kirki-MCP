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
		Customizer_Registry::boot();
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
		if ( self::run_migrations() ) {
			update_option( 'kirki_mcp_version', KIRKI_MCP_VERSION );
		}
	}

	/**
	 * @return bool Whether this request finished the migration.
	 */
	public static function run_migrations() {
		if ( ! self::claim_migration_lock() ) {
			return false;
		}
		try {
			if ( function_exists( 'get_option' ) && KIRKI_MCP_VERSION === (string) get_option( 'kirki_mcp_version', '' ) ) {
				return true;
			}
			Symbol_Repository::migrate_plugin_symbols();
			Page_Repository::migrate_stored_urls();
			return true;
		} finally {
			self::release_migration_lock();
		}
	}

	/**
	 * @return bool
	 */
	private static function claim_migration_lock() {
		if ( ! function_exists( 'add_option' ) ) {
			return true;
		}
		$now   = time();
		$added = add_option( 'kirki_mcp_migration_lock', (string) $now, '', false );
		$held  = (int) get_option( 'kirki_mcp_migration_lock', 0 );
		$which = Global_Repository::refresh_lock_decision( (bool) $added, $held, $now );
		if ( 'busy' === $which ) {
			return false;
		}
		if ( 'stolen' === $which ) {
			update_option( 'kirki_mcp_migration_lock', (string) $now, false );
		}
		return true;
	}

	/**
	 * @return void
	 */
	private static function release_migration_lock() {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( 'kirki_mcp_migration_lock' );
		}
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
		if ( 'kirki' !== $meta_key || null !== $value || self::$reading_raw_meta ) {
			return $value;
		}
		$object_id = (int) $object_id;
		remove_filter( 'get_post_metadata', array( self::class, 'escape_stored_links' ), 10 );
		try {
			$meta = get_post_meta( $object_id, 'kirki', true );
			if ( ! is_array( $meta ) ) {
				return $value;
			}
			$hash = md5( (string) wp_json_encode( $meta ) );
			if ( isset( self::$link_cache[ $object_id ] ) && self::$link_cache[ $object_id ]['hash'] === $hash ) {
				return self::$link_cache[ $object_id ]['value'];
			}
			$hardened = array( Document::harden_kirki_meta( $meta ) );
			if ( count( self::$link_cache ) > 30 ) {
				self::$link_cache = array();
			}
			self::$link_cache[ $object_id ] = array(
				'hash'  => $hash,
				'value' => $hardened,
			);
			return $hardened;
		} catch ( \Throwable $error ) {
			unset( $error );
			return $value;
		} finally {
			add_filter( 'get_post_metadata', array( self::class, 'escape_stored_links' ), 10, 4 );
		}
	}

	/**
	 * Read post meta without this plugin's output filters.
	 *
	 * The link filter would otherwise return already-hardened data, and the
	 * migration would decide there was nothing to store.
	 *
	 * @param int    $post_id Post id.
	 * @param string $key Meta key.
	 * @return mixed
	 */
	public static function read_raw_post_meta( $post_id, $key ) {
		$result = null;
		self::without_meta_filters(
			function () use ( $post_id, $key, &$result ) {
				$result = get_post_meta( (int) $post_id, $key, true );
			}
		);
		return $result;
	}

	/**
	 * Write post meta while output filters are off.
	 *
	 * WordPress compares the new value with get_post_meta before writing. With the
	 * link filter on, that comparison sees the hardened value and skips storing it.
	 *
	 * @param int    $post_id Post id.
	 * @param string $key Meta key.
	 * @param mixed  $value Meta value.
	 * @return void
	 */
	public static function write_raw_post_meta( $post_id, $key, $value ) {
		self::without_meta_filters(
			function () use ( $post_id, $key, $value ) {
				update_post_meta( (int) $post_id, $key, Json_Data::slash( $value ) );
			}
		);
		if ( 'kirki' === $key ) {
			self::forget_link_cache( $post_id );
		}
	}

	/**
	 * @param callable $work Callback.
	 * @return void
	 */
	private static function without_meta_filters( $work ) {
		self::$reading_raw_meta = true;
		remove_filter( 'get_post_metadata', array( self::class, 'escape_stored_links' ), 10 );
		remove_filter( 'get_post_metadata', array( self::class, 'escape_stored_font_urls' ), 10 );
		try {
			call_user_func( $work );
		} finally {
			self::$reading_raw_meta = false;
			add_filter( 'get_post_metadata', array( self::class, 'escape_stored_font_urls' ), 10, 4 );
			add_filter( 'get_post_metadata', array( self::class, 'escape_stored_links' ), 10, 4 );
		}
	}

	/**
	 * Whether a caller should run the stored-data migration.
	 *
	 * @param string $method Ability method name.
	 * @return bool
	 */
	public static function migrates_for( $method ) {
		$reads = array(
			'get_site_context',
			'list_pages',
			'get_page',
			'list_global_classes',
			'list_global_variables',
			'list_symbols',
			'get_symbol',
			'audit_unsafe_titles',
			'list_templates',
		);
		return ! in_array( $method, $reads, true );
	}

	/**
	 * Drop a cached read so the next lookup sees a write from this request.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function forget_link_cache( $post_id ) {
		unset( self::$link_cache[ (int) $post_id ] );
	}

	/**
	 * @var bool
	 */
	private static $reading_raw_meta = false;

	/**
	 * @var array<int,array{hash:string,value:array}>
	 */
	private static $link_cache = array();
}
