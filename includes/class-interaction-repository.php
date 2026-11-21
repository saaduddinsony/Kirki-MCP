<?php
/**
 * Interaction and animation timeline data on Kirki elements.
 *
 * Shape verified in free Kirki 6.4 DataHelper/Preview and the Pro 6.4.3 editor:
 * properties.interactions (deviceAndClassList, elementAsTrigger) and
 * properties.interactionLibrary (timeline targets per viewport).
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validate interaction payloads.
 */
class Interaction_Repository {

	const EVENTS = array( 'click', 'hover', 'mouseenter', 'mouseleave', 'scroll', 'load', 'inview', 'delay', 'focus', 'blur', 'mousedown', 'mouseup', 'touchstart', 'touchend' );

	/**
	 * @param mixed $interactions Interactions object.
	 * @return array
	 */
	public static function sanitize_interactions( $interactions ) {
		if ( ! is_array( $interactions ) ) {
			throw new \InvalidArgumentException( 'interactions must be an object.' );
		}
		$out = array();
		if ( isset( $interactions['deviceAndClassList'] ) && is_array( $interactions['deviceAndClassList'] ) ) {
			$list    = $interactions['deviceAndClassList'];
			$classes = array();
			if ( isset( $list['classList'] ) && is_array( $list['classList'] ) ) {
				foreach ( $list['classList'] as $name ) {
					if ( is_string( $name ) && preg_match( '/^[A-Za-z][A-Za-z0-9_\-]{0,80}$/', $name ) ) {
						$classes[] = $name;
					}
				}
			}
			$out['deviceAndClassList'] = array( 'classList' => $classes );
			if ( isset( $list['device'] ) && is_string( $list['device'] ) && in_array( $list['device'], array( 'md', 'tablet', 'mobile', 'all' ), true ) ) {
				$out['deviceAndClassList']['device'] = $list['device'];
			}
		}
		if ( isset( $interactions['elementAsTrigger'] ) && is_array( $interactions['elementAsTrigger'] ) ) {
			$out['elementAsTrigger'] = self::sanitize_triggers( $interactions['elementAsTrigger'] );
		}
		return $out;
	}

	/**
	 * @param mixed $library Interaction library (timeline).
	 * @return array
	 */
	public static function sanitize_library( $library ) {
		if ( ! is_array( $library ) ) {
			throw new \InvalidArgumentException( 'interaction_library must be an array.' );
		}
		if ( count( $library ) > 50 ) {
			throw new \InvalidArgumentException( 'An interaction library is limited to 50 items.' );
		}
		$out = array();
		foreach ( $library as $item ) {
			if ( ! is_array( $item ) ) {
				throw new \InvalidArgumentException( 'Each interaction library item must be an object.' );
			}
			$row = array();
			if ( isset( $item['name'] ) ) {
				$row['name'] = substr( preg_replace( '/[^A-Za-z0-9_\- ]/', '', (string) $item['name'] ), 0, 80 );
			}
			if ( isset( $item['data'] ) && is_array( $item['data'] ) ) {
				$row['data'] = self::sanitize_library_data( $item['data'] );
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * @param array $triggers Trigger map.
	 * @return array
	 */
	private static function sanitize_triggers( array $triggers ) {
		$out = array();
		$n   = 0;
		foreach ( $triggers as $event => $groups ) {
			++$n;
			if ( $n > 20 ) {
				throw new \InvalidArgumentException( 'Too many interaction events.' );
			}
			$event = is_string( $event ) ? strtolower( $event ) : '';
			if ( ! in_array( $event, self::EVENTS, true ) ) {
				throw new \InvalidArgumentException( 'Unsupported interaction event "' . $event . '".' );
			}
			if ( ! is_array( $groups ) ) {
				throw new \InvalidArgumentException( 'Each event must map to an object of action lists.' );
			}
			$out[ $event ] = self::sanitize_action_groups( $groups );
		}
		return $out;
	}

	/**
	 * @param array $groups Groups.
	 * @return array
	 */
	private static function sanitize_action_groups( array $groups ) {
		$out = array();
		foreach ( $groups as $kind => $actions ) {
			$kind = is_string( $kind ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', $kind ) : '';
			if ( '' === $kind ) {
				continue;
			}
			if ( ! is_array( $actions ) ) {
				throw new \InvalidArgumentException( 'Action lists must be arrays.' );
			}
			if ( count( $actions ) > 30 ) {
				throw new \InvalidArgumentException( 'Too many actions on one event.' );
			}
			$clean = array();
			foreach ( $actions as $action ) {
				if ( ! is_array( $action ) ) {
					throw new \InvalidArgumentException( 'Each action must be an object.' );
				}
				$entry = array();
				if ( isset( $action['type'] ) ) {
					$entry['type'] = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $action['type'] );
				}
				if ( isset( $action['data'] ) && is_array( $action['data'] ) ) {
					$entry['data'] = self::sanitize_action_data( $action['data'] );
				}
				$clean[] = $entry;
			}
			$out[ $kind ] = $clean;
		}
		return $out;
	}

	/**
	 * @param array $data Action data keyed by element id.
	 * @return array
	 */
	private static function sanitize_action_data( array $data ) {
		if ( count( $data ) > 80 ) {
			throw new \InvalidArgumentException( 'Too many animation targets on one action.' );
		}
		$out = array();
		foreach ( $data as $key => $value ) {
			$key = is_string( $key ) ? $key : (string) $key;
			if ( ! preg_match( '/^[A-Za-z0-9_\-]+(?:____info)?$/', $key ) ) {
				throw new \InvalidArgumentException( 'Invalid animation target id.' );
			}
			$out[ $key ] = self::sanitize_animation_value( $value );
		}
		return $out;
	}

	/**
	 * @param array $data Viewport map.
	 * @return array
	 */
	private static function sanitize_library_data( array $data ) {
		$out = array();
		foreach ( $data as $viewport => $payload ) {
			$viewport = is_string( $viewport ) ? $viewport : '';
			if ( ! in_array( $viewport, array( 'md', 'tablet', 'mobile' ), true ) ) {
				continue;
			}
			if ( ! is_array( $payload ) ) {
				continue;
			}
			$out[ $viewport ] = $payload;
		}
		return $out;
	}

	/**
	 * @param mixed $value Animation or info.
	 * @return mixed
	 */
	private static function sanitize_animation_value( $value ) {
		if ( is_null( $value ) || is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			if ( strlen( $value ) > 500 ) {
				throw new \InvalidArgumentException( 'Animation values are limited to 500 characters.' );
			}
			return $value;
		}
		if ( is_array( $value ) ) {
			$out = array();
			$n   = 0;
			foreach ( $value as $k => $item ) {
				++$n;
				if ( $n > 40 ) {
					throw new \InvalidArgumentException( 'Animation objects are limited to 40 keys.' );
				}
				$key         = is_string( $k ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', $k ) : $k;
				$out[ $key ] = self::sanitize_animation_value( $item );
			}
			return $out;
		}
		throw new \InvalidArgumentException( 'Unsupported animation value.' );
	}
}
