<?php
/**
 * Global classes, colors, fonts, and viewports.
 *
 * Classes live in kirki_class_manager_style_blocks. Variables live in
 * kirki_user_saved_data.variableData, in the four groups Kirki already uses:
 * color, size, text-style, and font-family.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global design tokens.
 */
class Global_Repository {

	/**
	 * Site context an agent should read before designing.
	 *
	 * @return array
	 */
	public static function context() {
		Page_Repository::assert_ready();
		$controller = array();
		if ( class_exists( '\Kirki\App\Supports\Facades\GlobalData' ) ) {
			$controller = \Kirki\App\Supports\Facades\GlobalData::get_global_ui_controller();
		}
		if ( ! is_array( $controller ) || empty( $controller ) ) {
			$controller = array(
				'viewport' => \Kirki\HelperFunctions::get_initial_view_ports(),
			);
		}

		return array(
			'kirki_version'  => KIRKI_VERSION,
			'plugin_version' => KIRKI_MCP_VERSION,
			'viewports'      => isset( $controller['viewport'] ) ? $controller['viewport'] : $controller,
			'variables'      => self::list_variables(),
			'classes'        => self::list_classes(),
			'symbols'        => Symbol_Repository::summaries(),
			'guidance'       => array(
				'Desktop CSS is the md variant. Tablet is tablet. Phone is mobile. Hover is md_hover.',
				'Reference a color variable as var(--ID), using the id returned when it was created.',
				'New pages are WordPress drafts until publish-page. Content is written to Kirki post meta, or to the unpublished editor stage when one exists.',
				'remove-element and trash-page do nothing unless confirm is true.',
			),
		);
	}

	/**
	 * Global classes.
	 *
	 * @return array
	 */
	public static function list_classes() {
		$classes = array();
		foreach ( self::style_blocks() as $id => $style ) {
			if ( ! is_array( $style ) ) {
				continue;
			}
			$name = isset( $style['name'] ) ? (string) $style['name'] : '';
			$classes[] = array(
				'id'        => (string) ( isset( $style['id'] ) ? $style['id'] : $id ),
				'name'      => $name,
				'css_class' => '.' . strtolower( str_replace( ' ', '-', $name ) ),
				'variant'   => isset( $style['variant'] ) && is_array( $style['variant'] ) ? $style['variant'] : array(),
			);
		}
		return $classes;
	}

	/**
	 * Map of lowercase class name to style-block id.
	 *
	 * @return array
	 */
	public static function name_map() {
		$map = array();
		foreach ( self::list_classes() as $class ) {
			if ( '' === $class['name'] ) {
				continue;
			}
			$map[ strtolower( $class['name'] ) ] = $class['id'];
		}
		return $map;
	}

	/**
	 * Create or update a global class.
	 *
	 * @param string $name Class name.
	 * @param array  $styles Variant CSS. Replaces those keys.
	 * @return array The class record.
	 */
	public static function upsert_class( $name, array $styles ) {
		Page_Repository::assert_ready();
		$name = self::class_name( $name );
		if ( empty( $styles ) ) {
			throw new \InvalidArgumentException( 'A global class needs at least one CSS variant.' );
		}

		$blocks = self::style_blocks();
		$id     = null;
		foreach ( $blocks as $style_id => $style ) {
			if ( is_array( $style ) && isset( $style['name'] ) && strtolower( (string) $style['name'] ) === $name ) {
				$id = isset( $style['id'] ) ? (string) $style['id'] : (string) $style_id;
				break;
			}
		}
		if ( null === $id ) {
			$id = Ids::make( $blocks );
			$blocks[ $id ] = array(
				'id'            => $id,
				'type'          => 'class',
				'name'          => $name,
				'isGlobalStyle' => true,
				'isDefault'     => false,
				'variant'       => array(),
			);
		}

		if ( ! isset( $blocks[ $id ]['variant'] ) || ! is_array( $blocks[ $id ]['variant'] ) ) {
			$blocks[ $id ]['variant'] = array();
		}
		foreach ( $styles as $key => $css ) {
			$blocks[ $id ]['variant'][ Css::variant_key( $key ) ] = Css::sanitize( $css );
		}
		$blocks[ $id ]['isGlobalStyle'] = true;
		$blocks[ $id ]['name']          = $name;

		\Kirki\App\Supports\Facades\GlobalData::update_style_blocks( $blocks );

		return array(
			'id'        => $id,
			'name'      => $name,
			'css_class' => '.' . $name,
			'variant'   => $blocks[ $id ]['variant'],
		);
	}

	/**
	 * Variables grouped the way Kirki stores them.
	 *
	 * @return array
	 */
	public static function list_variables() {
		$data = self::variable_groups();
		$out  = array();
		foreach ( $data as $group ) {
			$key = isset( $group['key'] ) ? $group['key'] : '';
			$items = array();
			if ( ! empty( $group['variables'] ) && is_array( $group['variables'] ) ) {
				foreach ( $group['variables'] as $variable ) {
					if ( ! is_array( $variable ) ) {
						continue;
					}
					$items[] = array(
						'id'      => isset( $variable['id'] ) ? $variable['id'] : '',
						'name'    => isset( $variable['title'] ) ? $variable['title'] : '',
						'type'    => isset( $variable['type'] ) ? $variable['type'] : $key,
						'value'   => isset( $variable['value'] ) ? $variable['value'] : array(),
						'css_var' => isset( $variable['id'] ) ? '--' . $variable['id'] : '',
					);
				}
			}
			$out[ $key ] = $items;
		}
		return $out;
	}

	/**
	 * Upsert color, font-family, and size variables.
	 *
	 * @param array $colors Color specs.
	 * @param array $fonts Font specs.
	 * @param array $sizes Size specs.
	 * @return array Current variables.
	 */
	public static function set_variables( array $colors, array $fonts, array $sizes ) {
		Page_Repository::assert_ready();
		$saved = \Kirki\App\Supports\Facades\GlobalData::get_global_ui_saved_data();
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$groups = isset( $saved['variableData']['data'] ) && is_array( $saved['variableData']['data'] )
			? $saved['variableData']['data']
			: self::empty_groups();

		$index = array();
		foreach ( $groups as $i => $group ) {
			if ( isset( $group['key'] ) ) {
				$index[ $group['key'] ] = $i;
			}
		}
		foreach ( array( 'color', 'size', 'font-family', 'text-style' ) as $required ) {
			if ( ! isset( $index[ $required ] ) ) {
				$groups[] = array(
					'title'     => $required,
					'key'       => $required,
					'modes'     => array(
						array(
							'title' => 'Default',
							'key'   => 'default',
						),
					),
					'variables' => array(),
				);
				$index[ $required ] = count( $groups ) - 1;
			}
		}

		foreach ( $colors as $color ) {
			self::upsert_variable( $groups[ $index['color'] ], 'color', $color, Css::color( isset( $color['value'] ) ? $color['value'] : '' ) );
		}
		foreach ( $fonts as $font ) {
			self::upsert_variable( $groups[ $index['font-family'] ], 'font-family', $font, Css::font_family( isset( $font['value'] ) ? $font['value'] : '' ) );
		}
		foreach ( $sizes as $size ) {
			$value = isset( $size['value'] ) ? $size['value'] : null;
			$unit  = isset( $size['unit'] ) ? (string) $size['unit'] : 'px';
			if ( ! is_numeric( $value ) ) {
				throw new \InvalidArgumentException( 'A size variable needs a numeric value.' );
			}
			if ( ! in_array( $unit, array( 'px', 'rem', 'em', '%', 'vh', 'vw' ), true ) ) {
				throw new \InvalidArgumentException( 'Size units must be px, rem, em, %, vh, or vw.' );
			}
			self::upsert_variable(
				$groups[ $index['size'] ],
				'size',
				$size,
				array(
					'value' => (float) $value,
					'unit'  => $unit,
				)
			);
		}

		$saved['variableData'] = array( 'data' => $groups );
		\Kirki\App\Supports\Facades\GlobalData::update_global_ui_saved_data( $saved );
		return self::list_variables();
	}

	/**
	 * Current style-block map.
	 *
	 * @return array
	 */
	private static function style_blocks() {
		Page_Repository::assert_ready();
		$blocks = \Kirki\App\Supports\Facades\GlobalData::get_style_blocks();
		return is_array( $blocks ) ? $blocks : array();
	}

	/**
	 * Variable groups from saved data.
	 *
	 * @return array
	 */
	private static function variable_groups() {
		Page_Repository::assert_ready();
		$saved = \Kirki\App\Supports\Facades\GlobalData::get_global_ui_saved_data();
		if ( is_array( $saved ) && ! empty( $saved['variableData']['data'] ) && is_array( $saved['variableData']['data'] ) ) {
			return $saved['variableData']['data'];
		}
		return self::empty_groups();
	}

	/**
	 * Insert or replace one variable inside a group.
	 *
	 * @param array  $group Group, by reference.
	 * @param string $type Variable type.
	 * @param array  $spec Spec with name.
	 * @param mixed  $value Stored default-mode value.
	 * @return void
	 */
	private static function upsert_variable( array &$group, $type, array $spec, $value ) {
		$name = isset( $spec['name'] ) ? sanitize_text_field( $spec['name'] ) : '';
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'Each variable needs a name.' );
		}
		if ( ! isset( $group['variables'] ) || ! is_array( $group['variables'] ) ) {
			$group['variables'] = array();
		}
		foreach ( $group['variables'] as $i => $variable ) {
			if ( is_array( $variable ) && isset( $variable['title'] ) && strtolower( (string) $variable['title'] ) === strtolower( $name ) ) {
				$group['variables'][ $i ]['value']['default'] = $value;
				$group['variables'][ $i ]['type']             = $type;
				return;
			}
		}
		$used = array();
		foreach ( $group['variables'] as $variable ) {
			if ( isset( $variable['id'] ) ) {
				$used[ $variable['id'] ] = true;
			}
		}
		$group['variables'][] = array(
			'id'    => Ids::make( $used ),
			'title' => $name,
			'type'  => $type,
			'value' => array(
				'default' => $value,
			),
		);
	}

	/**
	 * Empty variable groups in Kirki's order.
	 *
	 * @return array
	 */
	private static function empty_groups() {
		$groups = array();
		foreach ( array(
			'color'       => 'Colors',
			'size'        => 'Numbers',
			'text-style'  => 'Text Styles',
			'font-family' => 'Font Family',
		) as $key => $title ) {
			$groups[] = array(
				'title'     => $title,
				'key'       => $key,
				'modes'     => array(
					array(
						'title' => 'Default',
						'key'   => 'default',
					),
				),
				'variables' => array(),
			);
		}
		return $groups;
	}

	/**
	 * Validate a class name.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	private static function class_name( $name ) {
		$name = strtolower( trim( (string) $name ) );
		$name = str_replace( ' ', '-', $name );
		if ( ! preg_match( '/^[a-z][a-z0-9_-]{0,60}$/', $name ) ) {
			throw new \InvalidArgumentException( 'Class names must start with a letter and use only letters, numbers, hyphens, and underscores.' );
		}
		return $name;
	}
}
