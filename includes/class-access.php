<?php
/**
 * Permissions aligned with Kirki's role map.
 *
 * Kirki grants builder access to administrators and editors only
 * (kirki/app/Constants/AccessLevels.php). A WordPress capability such as
 * edit_pages is not enough on its own: a shop manager has that capability
 * and still cannot open the Kirki editor.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capability checks.
 */
class Access {

	/**
	 * Builder access: Kirki administrator or editor, and edit_pages.
	 *
	 * @return bool
	 */
	public static function can_build() {
		return self::kirki( 'has_builder_access' ) && true === current_user_can( 'edit_pages' );
	}

	/**
	 * Publish pages. Kirki treats this as builder access plus publish_pages.
	 *
	 * @return bool
	 */
	public static function can_publish() {
		return self::can_build() && true === current_user_can( 'publish_pages' );
	}

	/**
	 * Upload images. Builder access plus upload_files.
	 *
	 * @return bool
	 */
	public static function can_upload() {
		return self::can_build() && true === current_user_can( 'upload_files' );
	}

	/**
	 * Whether this user may edit one page.
	 *
	 * @param int $page_id Page id.
	 * @return bool
	 */
	public static function can_edit_page( $page_id ) {
		return self::can_build() && true === current_user_can( 'edit_post', (int) $page_id );
	}

	/**
	 * Whether this user may trash one page.
	 *
	 * @param int $page_id Page id.
	 * @return bool
	 */
	public static function can_delete_page( $page_id ) {
		return self::can_build() && true === current_user_can( 'delete_post', (int) $page_id );
	}

	/**
	 * Read-only site context, including the "unsupported Kirki version" error.
	 *
	 * @return bool
	 */
	public static function can_read_context() {
		return true === current_user_can( 'read' );
	}

	/**
	 * Merchant-admin capabilities used by Kirki eCommerce admin routes.
	 *
	 * @return bool
	 */
	public static function can_manage_store() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * Theme mods and Customizer changesets need the same cap WordPress uses for the Customizer.
	 */
	public static function can_customize() {
		return function_exists( 'current_user_can' ) && current_user_can( 'edit_theme_options' );
	}

	/**
	 * Payment and checkout writes need manage_options plus an explicit site opt-in.
	 */
	public static function store_payment_writes_allowed() {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}
		$value = get_option( 'kirki_mcp_allow_store_payment_writes', false );
		return ( true === $value || 1 === $value || '1' === $value );
	}

	/**
	 * Enable or disable payment/checkout writes. Requires manage_options.
	 *
	 * @param bool $enabled Enabled.
	 * @return bool
	 */
	public static function set_store_payment_writes( $enabled ) {
		if ( ! function_exists( 'update_option' ) ) {
			throw new \RuntimeException( 'WordPress options are not available.' );
		}
		update_option( 'kirki_mcp_allow_store_payment_writes', (bool) $enabled, false );
		return (bool) $enabled;
	}

	/**
	 * @return bool
	 */
	public static function can_write_store_payments() {
		return self::can_manage_store() && self::store_payment_writes_allowed();
	}

	/**
	 * Custom code is printed raw by Kirki, so it needs unfiltered_html as well as builder access.
	 *
	 * @return bool
	 */
	public static function can_edit_custom_code() {
		return self::can_build() && true === current_user_can( 'unfiltered_html' );
	}

	/**
	 * Call a Kirki user access method. Missing Kirki means no access.
	 *
	 * @param string $method has_builder_access or has_edit_access.
	 * @return bool
	 */
	private static function kirki( $method ) {
		if ( ! function_exists( '\Kirki\Framework\user' ) ) {
			return false;
		}
		$user = \Kirki\Framework\user();
		if ( ! is_object( $user ) || ! method_exists( $user, $method ) ) {
			return false;
		}
		if ( method_exists( $user, 'sync' ) ) {
			$user_id = (int) get_current_user_id();
			if ( $user_id > 0 ) {
				$user->sync( $user_id );
			}
		}
		return true === $user->{$method}();
	}
}
