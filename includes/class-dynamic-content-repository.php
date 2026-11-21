<?php
/**
 * Dynamic content bindings stored on Kirki elements.
 *
 * The binding shape is the free Kirki 6.4 renderer format
 * (`properties.dynamicContent`: type, value, and optional format/meta fields).
 * The Pro editor writes the same keys.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catalog and write dynamic-content bindings.
 */
class Dynamic_Content_Repository {

	const TYPES   = array( 'content', 'image', 'video', 'anchor' );
	const SOURCES = array( 'manual', 'post', 'term', 'comment', 'user', 'site', 'others', 'author', 'menu' );

	/**
	 * Field catalog from Kirki when present, else the documented defaults.
	 *
	 * @return array
	 */
	public static function fields() {
		if ( class_exists( '\Kirki\Ajax\DynamicContent' ) && method_exists( '\Kirki\Ajax\DynamicContent', 'get_default_dynamic_content_fields' ) ) {
			$fields = \Kirki\Ajax\DynamicContent::get_default_dynamic_content_fields();
			if ( is_array( $fields ) ) {
				return $fields;
			}
		}
		return array(
			'type'       => self::TYPES,
			'typeValues' => array(
				'content' => self::SOURCES,
				'image'   => array( 'manual', 'post', 'site', 'author', 'user' ),
				'video'   => array( 'manual', 'post' ),
				'anchor'  => array( 'manual', 'post', 'term', 'site' ),
			),
			'note'       => 'Kirki Ajax\\DynamicContent was not loaded; returning the documented default types.',
		);
	}

	/**
	 * Validate a binding object.
	 *
	 * @param array $binding Binding.
	 * @return array
	 */
	public static function sanitize( $binding ) {
		if ( ! is_array( $binding ) ) {
			throw new \InvalidArgumentException( 'dynamic_content must be an object.' );
		}
		$type = isset( $binding['type'] ) ? (string) $binding['type'] : '';
		if ( ! in_array( $type, self::SOURCES, true ) && ! in_array( $type, self::TYPES, true ) ) {
			throw new \InvalidArgumentException( 'Unsupported dynamic content type.' );
		}
		$out = array(
			'type'  => $type,
			'value' => isset( $binding['value'] ) ? self::safe_token( $binding['value'] ) : '',
		);
		foreach ( array( 'meta', 'format', 'timeFormat', 'cmFieldType', 'taxonomy', 'collectionType' ) as $key ) {
			if ( isset( $binding[ $key ] ) && is_scalar( $binding[ $key ] ) ) {
				$out[ $key ] = self::safe_token( $binding[ $key ] );
			}
		}
		if ( isset( $binding['postExcerptLength'] ) ) {
			$out['postExcerptLength'] = max( 1, min( 500, (int) $binding['postExcerptLength'] ) );
		}
		if ( isset( $binding['items'] ) ) {
			$out['items'] = max( 1, min( 100, (int) $binding['items'] ) );
		}
		if ( isset( $binding['offset'] ) ) {
			$out['offset'] = max( 0, min( 1000, (int) $binding['offset'] ) );
		}
		if ( isset( $binding['inherit'] ) ) {
			$out['inherit'] = (bool) $binding['inherit'];
		}
		return $out;
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function safe_token( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = preg_replace( '/[^A-Za-z0-9_\-:]/', '', $value );
		if ( strlen( $value ) > 80 ) {
			$value = substr( $value, 0, 80 );
		}
		return $value;
	}
}
