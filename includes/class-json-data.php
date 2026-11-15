<?php
/**
 * Accepts JSON-shaped data and rejects PHP objects.
 *
 * Kirki symbol payloads have been a PHP object-injection vector. This plugin
 * never calls unserialize() or maybe_unserialize() on agent input. WordPress
 * still serializes arrays when they are stored in post meta; on the way back
 * out, anything that is not an array or a scalar is discarded.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JSON shape guard.
 */
class Json_Data {

	/**
	 * Require an array and reject nested objects.
	 *
	 * @param mixed  $value Value that came from JSON or from post meta.
	 * @param string $label What the value is, for the error message.
	 * @return array
	 */
	public static function only_array( $value, $label ) {
		if ( ! is_array( $value ) ) {
			throw new \InvalidArgumentException( $label . ' must be a JSON object or array. PHP objects are not accepted.' );
		}
		self::reject_objects( $value, $label );
		return $value;
	}

	/**
	 * Walk a value and throw when a PHP object is present.
	 *
	 * @param mixed  $value Value to walk.
	 * @param string $label Context for the error.
	 * @return void
	 */
	public static function reject_objects( $value, $label ) {
		if ( is_object( $value ) ) {
			throw new \InvalidArgumentException( $label . ' contained a PHP object. Kirki MCP only accepts JSON.' );
		}
		if ( ! is_array( $value ) ) {
			return;
		}
		foreach ( $value as $item ) {
			if ( is_array( $item ) || is_object( $item ) ) {
				self::reject_objects( $item, $label );
			}
		}
	}
}
