<?php
/**
 * Audit log for sensitive store and template operations.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Append-only option log. Credential values are never stored.
 */
class Audit {

	const OPTION = 'kirki_mcp_audit_log';
	const MAX    = 200;

	/**
	 * @param string $action Action name.
	 * @param array  $context Context without secrets.
	 * @return void
	 */
	public static function record( $action, array $context = array() ) {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}
		$entry = array(
			'time'    => function_exists( 'gmdate' ) ? gmdate( 'c' ) : '',
			'action'  => (string) $action,
			'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			'context' => $context,
		);
		$log   = get_option( self::OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::MAX );
		update_option( self::OPTION, $log, false );
	}

	/**
	 * @param int $limit Max entries.
	 * @return array
	 */
	public static function list_entries( $limit = 50 ) {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}
		$log = get_option( self::OPTION, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}
		return array_slice( $log, 0, max( 1, min( 200, (int) $limit ) ) );
	}
}
