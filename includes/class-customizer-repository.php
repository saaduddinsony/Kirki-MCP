<?php
/**
 * WordPress Customizer values and Kirki framework fields.
 *
 * Since Kirki 5.0.0 the Customizer framework, including formerly Pro controls
 * (responsive, tabs, input-slider, headline-divider, margin-padding), ships in
 * the free plugin. Field *registration* still belongs in theme or plugin PHP.
 * These abilities read and write values and changesets; they do not invent
 * new panels or control types at runtime.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme mods, registered Kirki fields, and customize changesets.
 */
class Customizer_Repository {

	/**
	 * @return array
	 */
	public static function list_mods() {
		if ( ! function_exists( 'get_theme_mods' ) ) {
			throw new \RuntimeException( 'WordPress theme mods are not available.' );
		}
		$mods = get_theme_mods();
		return array(
			'theme'  => function_exists( 'get_stylesheet' ) ? get_stylesheet() : '',
			'mods'   => is_array( $mods ) ? $mods : array(),
			'fields' => self::registered_fields(),
		);
	}

	/**
	 * @param string $name Theme mod name.
	 * @return array
	 */
	public static function get_mod( $name ) {
		$name = self::safe_key( $name );
		if ( ! function_exists( 'get_theme_mod' ) ) {
			throw new \RuntimeException( 'WordPress theme mods are not available.' );
		}
		return array(
			'name'  => $name,
			'value' => get_theme_mod( $name, null ),
		);
	}

	/**
	 * @param string $name Theme mod name.
	 * @param mixed  $value Value. Must be scalar or array of scalars.
	 * @return array
	 */
	public static function set_mod( $name, $value ) {
		$name  = self::safe_key( $name );
		$value = self::safe_value( $value );
		if ( ! function_exists( 'set_theme_mod' ) ) {
			throw new \RuntimeException( 'WordPress theme mods are not available.' );
		}
		set_theme_mod( $name, $value );
		return self::get_mod( $name );
	}

	/**
	 * @param string $name Option name.
	 * @return array
	 */
	public static function get_option( $name ) {
		$name = self::safe_option_key( $name );
		if ( ! function_exists( 'get_option' ) ) {
			throw new \RuntimeException( 'WordPress options are not available.' );
		}
		return array(
			'name'  => $name,
			'value' => get_option( $name, null ),
		);
	}

	/**
	 * @param string $name Option name, must be a known Kirki option or already exist.
	 * @param mixed  $value Value.
	 * @return array
	 */
	public static function set_option( $name, $value ) {
		$name  = self::safe_option_key( $name );
		$value = self::safe_value( $value );
		if ( ! function_exists( 'update_option' ) ) {
			throw new \RuntimeException( 'WordPress options are not available.' );
		}
		$exists = false;
		if ( function_exists( 'get_option' ) ) {
			$exists = false !== get_option( $name, false ) || self::is_registered_option( $name );
		}
		if ( ! $exists ) {
			throw new \InvalidArgumentException( 'Refusing to create an unknown option. Pass a registered Kirki option or an option that already exists.' );
		}
		update_option( $name, $value );
		return self::get_option( $name );
	}

	/**
	 * @return array
	 */
	public static function list_changesets() {
		if ( ! class_exists( '\WP_Query' ) ) {
			throw new \RuntimeException( 'WordPress is not available.' );
		}
		$query = new \WP_Query(
			array(
				'post_type'      => 'customize_changeset',
				'post_status'    => array( 'auto-draft', 'draft', 'publish', 'future', 'trash' ),
				'posts_per_page' => 20,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'     => (int) $post->ID,
				'uuid'   => $post->post_name,
				'status' => $post->post_status,
				'title'  => $post->post_title,
			);
		}
		return array( 'changesets' => $items );
	}

	/**
	 * @param int $changeset_id Changeset post id.
	 * @return array
	 */
	public static function publish_changeset( $changeset_id ) {
		$post = get_post( (int) $changeset_id );
		if ( ! $post || 'customize_changeset' !== $post->post_type ) {
			throw new \InvalidArgumentException( 'Customize changeset not found.' );
		}
		$result = wp_update_post(
			array(
				'ID'          => (int) $post->ID,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( $result->get_error_message() );
		}
		return array(
			'id'     => (int) $post->ID,
			'status' => 'publish',
			'note'   => 'The changeset post was published. WordPress applies changeset data when the Customizer publishes it; confirm the front end if the site uses a custom publish flow.',
		);
	}

	/**
	 * @return array
	 */
	public static function registered_fields() {
		$fields  = array();
		$sources = array();
		if ( class_exists( '\Kirki\Compatibility\Kirki' ) && isset( \Kirki\Compatibility\Kirki::$fields ) && is_array( \Kirki\Compatibility\Kirki::$fields ) ) {
			$sources[] = \Kirki\Compatibility\Kirki::$fields;
		}
		if ( class_exists( '\Kirki' ) && isset( \Kirki::$fields ) && is_array( \Kirki::$fields ) ) {
			$sources[] = \Kirki::$fields;
		}
		foreach ( $sources as $group ) {
			foreach ( $group as $id => $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$fields[] = array(
					'settings'    => isset( $field['settings'] ) ? $field['settings'] : $id,
					'type'        => isset( $field['type'] ) ? $field['type'] : '',
					'label'       => isset( $field['label'] ) ? $field['label'] : '',
					'section'     => isset( $field['section'] ) ? $field['section'] : '',
					'option_type' => isset( $field['option_type'] ) ? $field['option_type'] : 'theme_mod',
					'option_name' => isset( $field['option_name'] ) ? $field['option_name'] : '',
				);
			}
		}
		return $fields;
	}

	/**
	 * @param string $name Option key.
	 * @return bool
	 */
	public static function is_registered_option( $name ) {
		foreach ( self::registered_fields() as $field ) {
			if ( 'option' === ( $field['option_type'] ?? '' ) && ( $field['settings'] ?? '' ) === $name ) {
				return true;
			}
			if ( 'option' === ( $field['option_type'] ?? '' ) && ! empty( $field['option_name'] ) && 0 === strpos( $name, (string) $field['option_name'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $name Key.
	 * @return string
	 */
	public static function safe_key( $name ) {
		$name = is_string( $name ) ? $name : '';
		$name = preg_replace( '/[^A-Za-z0-9_\-:]/', '', $name );
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'A theme mod name is required.' );
		}
		return $name;
	}

	/**
	 * Rejects option keys that look like secrets or core auth options.
	 *
	 * @param string $name Option name.
	 * @return string
	 */
	public static function safe_option_key( $name ) {
		$name = is_string( $name ) ? $name : '';
		$name = preg_replace( '/[^A-Za-z0-9_\-:]/', '', $name );
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'An option name is required.' );
		}
		$blocked = array( 'siteurl', 'home', 'admin_email', 'users_can_register', 'default_role', 'rewrite_rules', 'active_plugins', 'recently_activated', 'cron', 'auth_key', 'secure_auth_key', 'logged_in_key', 'nonce_key', 'auth_salt', 'secure_auth_salt', 'logged_in_salt', 'nonce_salt' );
		$lower   = strtolower( $name );
		if ( in_array( $lower, $blocked, true ) || false !== strpos( $lower, 'password' ) || false !== strpos( $lower, 'secret' ) || false !== strpos( $lower, 'license_key' ) || 0 === strpos( $lower, 'kirki_ecommerce_payment' ) ) {
			throw new \InvalidArgumentException( 'That option cannot be read or written through Kirki MCP.' );
		}
		return $name;
	}

	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function safe_value( $value ) {
		if ( is_int( $value ) || is_float( $value ) || is_bool( $value ) || is_null( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			if ( strlen( $value ) > 20000 ) {
				throw new \InvalidArgumentException( 'Values are limited to 20,000 characters.' );
			}
			return $value;
		}
		if ( is_array( $value ) ) {
			$out = array();
			$n   = 0;
			foreach ( $value as $key => $item ) {
				++$n;
				if ( $n > 200 ) {
					throw new \InvalidArgumentException( 'Arrays are limited to 200 items.' );
				}
				$out[ is_string( $key ) ? self::safe_key( $key ) : $key ] = self::safe_value( $item );
			}
			return $out;
		}
		throw new \InvalidArgumentException( 'Values must be scalars or arrays of scalars.' );
	}
}
