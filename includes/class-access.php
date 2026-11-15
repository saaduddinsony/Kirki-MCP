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
		return true === $user->{$method}();
	}
}
