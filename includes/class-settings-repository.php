<?php
/**
 * Export and import theme mods, safe registered options, and the MCP Customizer registry.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings dump.
 */
class Settings_Repository {

	/**
	 * @return array
	 */
	public static function export() {
		$mods = array();
		if ( function_exists( 'get_theme_mods' ) ) {
			$raw = get_theme_mods();
			if ( is_array( $raw ) ) {
				foreach ( $raw as $key => $value ) {
					try {
						$safe          = Customizer_Repository::safe_key( (string) $key );
						$mods[ $safe ] = Customizer_Repository::safe_value( $value );
					} catch ( \InvalidArgumentException $e ) {
						continue;
					}
				}
			}
		}
		$options = array();
		foreach ( Customizer_Repository::registered_fields() as $field ) {
			if ( empty( $field['settings'] ) ) {
				continue;
			}
			try {
				$name = Customizer_Repository::safe_option_key( (string) $field['settings'] );
			} catch ( \InvalidArgumentException $e ) {
				continue;
			}
			if ( function_exists( 'get_option' ) ) {
				$options[ $name ] = get_option( $name );
			}
		}
		return array(
			'theme'    => function_exists( 'get_stylesheet' ) ? get_stylesheet() : '',
			'mods'     => $mods,
			'options'  => $options,
			'registry' => Customizer_Registry::all(),
		);
	}

	/**
	 * @param array $dump Dump.
	 * @param bool  $confirm Confirm.
	 * @return array
	 */
	public static function import( array $dump, $confirm ) {
		if ( ! $confirm ) {
			throw new \InvalidArgumentException( 'Importing settings requires confirm: true.' );
		}
		$applied = array(
			'mods'     => 0,
			'options'  => 0,
			'registry' => false,
		);
		if ( isset( $dump['mods'] ) && is_array( $dump['mods'] ) ) {
			foreach ( $dump['mods'] as $name => $value ) {
				Customizer_Repository::set_mod( $name, $value );
				++$applied['mods'];
			}
		}
		if ( isset( $dump['options'] ) && is_array( $dump['options'] ) ) {
			foreach ( $dump['options'] as $name => $value ) {
				Customizer_Repository::set_option( $name, $value );
				++$applied['options'];
			}
		}
		if ( isset( $dump['registry'] ) && is_array( $dump['registry'] ) ) {
			if ( isset( $dump['registry']['panels'] ) && is_array( $dump['registry']['panels'] ) ) {
				foreach ( $dump['registry']['panels'] as $panel ) {
					if ( isset( $panel['id'], $panel['args'] ) ) {
						Customizer_Registry::register_panel( $panel['id'], $panel['args'] );
					}
				}
			}
			if ( isset( $dump['registry']['sections'] ) && is_array( $dump['registry']['sections'] ) ) {
				foreach ( $dump['registry']['sections'] as $section ) {
					if ( isset( $section['id'], $section['args'] ) ) {
						Customizer_Registry::register_section( $section['id'], $section['args'] );
					}
				}
			}
			if ( isset( $dump['registry']['fields'] ) && is_array( $dump['registry']['fields'] ) ) {
				foreach ( $dump['registry']['fields'] as $field ) {
					if ( isset( $field['args'] ) ) {
						Customizer_Registry::register_field( $field['args'] );
					}
				}
			}
			$applied['registry'] = true;
		}
		Audit::record( 'settings-import', $applied );
		return $applied;
	}
}
