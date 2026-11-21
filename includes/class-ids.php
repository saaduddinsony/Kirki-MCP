<?php
/**
 * Kirki-shaped element and style identifiers.
 *
 * Kirki's editor generates ids with g_(): "k-" plus eight base-36 characters.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Identifier helper.
 */
class Ids {

	/**
	 * Generate one unused id.
	 *
	 * @param array $used Map of ids that are already taken.
	 * @return string
	 */
	public static function make( array $used = array() ) {
		do {
			$id = 'k-' . self::chunk() . self::chunk();
		} while ( isset( $used[ $id ] ) );

		return $id;
	}

	/**
	 * Four base-36 characters, matching the editor's random id chunk.
	 *
	 * @return string
	 */
	private static function chunk() {
		$n = random_int( 0, 1679615 );
		return substr( str_pad( base_convert( (string) $n, 10, 36 ), 4, '0', STR_PAD_LEFT ), -4 );
	}
}
