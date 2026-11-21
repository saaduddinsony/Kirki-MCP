<?php
/**
 * Persistent Customizer panel/section/field registration.
 *
 * Registrations are stored in an option and re-applied on init through the
 * Kirki compatibility API when Kirki is loaded, or through WP_Customize_Manager
 * as a fallback.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runtime Customizer registry.
 */
class Customizer_Registry {

	const OPTION = 'kirki_mcp_customizer_registry';
	const CONFIG = 'kirki_mcp';

	/**
	 * Allowed Kirki / WP control types (free 6.4 field classes).
	 */
	const TYPES = array(
		'text',
		'textarea',
		'url',
		'number',
		'checkbox',
		'switch',
		'toggle',
		'radio',
		'radio-buttonset',
		'radio-image',
		'select',
		'typography',
		'color',
		'color-palette',
		'react-colorful',
		'dimension',
		'slider',
		'input-slider',
		'image',
		'upload',
		'editor',
		'code',
		'date',
		'dropdown-pages',
		'headline',
		'divider',
		'margin',
		'padding',
		'tabs',
		'responsive',
		'sortable',
		'repeater',
		'palette',
		'preset',
		'generic',
		'dashicons',
		'multicheck',
		'multicolor',
	);

	/**
	 * Hook registration so persisted items exist on the front and in the Customizer.
	 *
	 * @return void
	 */
	public static function boot() {
		add_action( 'init', array( self::class, 'apply' ), 20 );
	}

	/**
	 * @return array{panels:array,sections:array,fields:array}
	 */
	public static function all() {
		if ( ! function_exists( 'get_option' ) ) {
			return array(
				'panels'   => array(),
				'sections' => array(),
				'fields'   => array(),
			);
		}
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array(
			'panels'   => isset( $stored['panels'] ) && is_array( $stored['panels'] ) ? $stored['panels'] : array(),
			'sections' => isset( $stored['sections'] ) && is_array( $stored['sections'] ) ? $stored['sections'] : array(),
			'fields'   => isset( $stored['fields'] ) && is_array( $stored['fields'] ) ? $stored['fields'] : array(),
		);
	}

	/**
	 * Re-register stored items.
	 *
	 * @return void
	 */
	public static function apply() {
		$all = self::all();
		if ( class_exists( '\Kirki' ) && method_exists( '\Kirki', 'add_config' ) ) {
			if ( method_exists( '\Kirki', 'add_config' ) ) {
				\Kirki::add_config(
					self::CONFIG,
					array(
						'capability'  => 'edit_theme_options',
						'option_type' => 'theme_mod',
					)
				);
			}
			foreach ( $all['panels'] as $panel ) {
				if ( isset( $panel['id'] ) ) {
					\Kirki::add_panel( $panel['id'], $panel['args'] );
				}
			}
			foreach ( $all['sections'] as $section ) {
				if ( isset( $section['id'] ) ) {
					\Kirki::add_section( $section['id'], $section['args'] );
				}
			}
			foreach ( $all['fields'] as $field ) {
				if ( isset( $field['args'] ) ) {
					\Kirki::add_field( self::CONFIG, $field['args'] );
				}
			}
			return;
		}
		if ( class_exists( '\Kirki\Compatibility\Kirki' ) && method_exists( '\Kirki\Compatibility\Kirki', 'add_field' ) ) {
			foreach ( $all['panels'] as $panel ) {
				if ( isset( $panel['id'] ) ) {
					\Kirki\Compatibility\Kirki::add_panel( $panel['id'], $panel['args'] );
				}
			}
			foreach ( $all['sections'] as $section ) {
				if ( isset( $section['id'] ) ) {
					\Kirki\Compatibility\Kirki::add_section( $section['id'], $section['args'] );
				}
			}
			foreach ( $all['fields'] as $field ) {
				if ( isset( $field['args'] ) ) {
					\Kirki\Compatibility\Kirki::add_field( self::CONFIG, $field['args'] );
				}
			}
		}
	}

	/**
	 * @param string $id Panel id.
	 * @param array  $args Args.
	 * @return array
	 */
	public static function register_panel( $id, array $args ) {
		$id                   = self::safe_id( $id );
		$args                 = self::safe_args( $args, array( 'title', 'description', 'priority', 'icon' ) );
		$args['title']        = isset( $args['title'] ) ? $args['title'] : $id;
		$all                  = self::all();
		$all['panels'][ $id ] = array(
			'id'   => $id,
			'args' => $args,
		);
		self::save( $all );
		return $all['panels'][ $id ];
	}

	/**
	 * @param string $id Section id.
	 * @param array  $args Args.
	 * @return array
	 */
	public static function register_section( $id, array $args ) {
		$id            = self::safe_id( $id );
		$args          = self::safe_args( $args, array( 'title', 'description', 'priority', 'panel', 'icon', 'type' ) );
		$args['title'] = isset( $args['title'] ) ? $args['title'] : $id;
		if ( isset( $args['panel'] ) ) {
			$args['panel'] = self::safe_id( $args['panel'] );
		}
		$all                    = self::all();
		$all['sections'][ $id ] = array(
			'id'   => $id,
			'args' => $args,
		);
		self::save( $all );
		return $all['sections'][ $id ];
	}

	/**
	 * @param array $args Field args.
	 * @return array
	 */
	public static function register_field( array $args ) {
		if ( empty( $args['settings'] ) ) {
			throw new \InvalidArgumentException( 'A field needs a settings key.' );
		}
		if ( empty( $args['section'] ) ) {
			throw new \InvalidArgumentException( 'A field needs a section.' );
		}
		$type = isset( $args['type'] ) ? (string) $args['type'] : 'text';
		if ( ! in_array( $type, self::TYPES, true ) ) {
			throw new \InvalidArgumentException( 'Unsupported Customizer control type "' . $type . '".' );
		}
		$settings = Customizer_Repository::safe_key( $args['settings'] );
		$section  = self::safe_id( $args['section'] );
		$clean    = array(
			'settings' => $settings,
			'section'  => $section,
			'type'     => $type,
			'label'    => isset( $args['label'] ) ? self::safe_text( $args['label'] ) : $settings,
		);
		if ( isset( $args['description'] ) ) {
			$clean['description'] = self::safe_text( $args['description'] );
		}
		if ( isset( $args['default'] ) ) {
			$clean['default'] = Customizer_Repository::safe_value( $args['default'] );
		}
		if ( isset( $args['priority'] ) ) {
			$clean['priority'] = (int) $args['priority'];
		}
		if ( isset( $args['choices'] ) && is_array( $args['choices'] ) ) {
			$choices = array();
			$n       = 0;
			foreach ( $args['choices'] as $key => $label ) {
				++$n;
				if ( $n > 50 ) {
					break;
				}
				$choices[ Customizer_Repository::safe_key( (string) $key ) ] = self::safe_text( $label );
			}
			$clean['choices'] = $choices;
		}
		$all                        = self::all();
		$all['fields'][ $settings ] = array(
			'id'   => $settings,
			'args' => $clean,
		);
		self::save( $all );
		return $all['fields'][ $settings ];
	}

	/**
	 * @param string $kind panels, sections, or fields.
	 * @param string $id Id.
	 * @return array
	 */
	public static function unregister( $kind, $id ) {
		$map  = array(
			'panel'    => 'panels',
			'section'  => 'sections',
			'field'    => 'fields',
			'panels'   => 'panels',
			'sections' => 'sections',
			'fields'   => 'fields',
		);
		$kind = isset( $map[ (string) $kind ] ) ? $map[ (string) $kind ] : '';
		if ( '' === $kind ) {
			throw new \InvalidArgumentException( 'kind must be panel, section, or field.' );
		}
		$id  = self::safe_id( $id );
		$all = self::all();
		if ( ! isset( $all[ $kind ][ $id ] ) ) {
			throw new \InvalidArgumentException( 'That Customizer item is not in the Kirki MCP registry.' );
		}
		unset( $all[ $kind ][ $id ] );
		self::save( $all );
		return array(
			'removed' => $id,
			'kind'    => $kind,
		);
	}

	/**
	 * @param array $all Registry.
	 * @return void
	 */
	private static function save( array $all ) {
		if ( ! function_exists( 'update_option' ) ) {
			throw new \RuntimeException( 'WordPress options are not available.' );
		}
		update_option( self::OPTION, $all, false );
	}

	/**
	 * @param string $id Id.
	 * @return string
	 */
	public static function safe_id( $id ) {
		$id = is_string( $id ) ? $id : '';
		$id = strtolower( $id );
		if ( ! preg_match( '/^[a-z][a-z0-9_\-]{0,80}$/', $id ) ) {
			throw new \InvalidArgumentException( 'Ids must start with a letter and contain only letters, numbers, hyphens, and underscores.' );
		}
		return $id;
	}

	/**
	 * @param array    $args Args.
	 * @param string[] $keys Allowed keys.
	 * @return array
	 */
	private static function safe_args( array $args, array $keys ) {
		$out = array();
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $args ) ) {
				continue;
			}
			if ( 'priority' === $key ) {
				$out[ $key ] = (int) $args[ $key ];
				continue;
			}
			$out[ $key ] = self::safe_text( $args[ $key ] );
		}
		return $out;
	}

	/**
	 * @param mixed $text Text.
	 * @return string
	 */
	private static function safe_text( $text ) {
		$text = is_scalar( $text ) ? (string) $text : '';
		$text = wp_strip_all_tags( $text );
		if ( strlen( $text ) > 200 ) {
			$text = substr( $text, 0, 200 );
		}
		return $text;
	}
}
