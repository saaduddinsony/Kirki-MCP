<?php
/**
 * A small status screen with the MCP endpoint and dependency checks.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings screen.
 */
class Admin {

	/**
	 * Hook the menu.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );
	}

	/**
	 * @return void
	 */
	public static function menu() {
		add_options_page(
			__( 'Kirki MCP', 'kirki-mcp' ),
			__( 'Kirki MCP', 'kirki-mcp' ),
			'manage_options',
			'kirki-mcp',
			array( self::class, 'render' )
		);
	}

	/**
	 * @return void
	 */
	public static function notices() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( ! defined( 'KIRKI_VERSION' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Kirki MCP needs the free Kirki 6 plugin to be active.', 'kirki-mcp' ) . '</p></div>';
			return;
		}
		$reason = Compatibility::unsupported_reason();
		if ( '' !== $reason ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $reason ) . '</p></div>';
		}
		if ( ! defined( 'WP_MCP_VERSION' ) && ! class_exists( '\WP\MCP\Plugin' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Kirki MCP abilities are registered. Install and activate the official MCP Adapter plugin so agents can call them over MCP.', 'kirki-mcp' ) . '</p></div>';
		}
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$endpoint = rest_url( 'mcp/mcp-adapter-default-server' );
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Kirki MCP', 'kirki-mcp' ) . '</h1>';
		echo '<p>' . esc_html__( 'Agents connect to the MCP Adapter endpoint with an application password. Kirki MCP does not store that password.', 'kirki-mcp' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:760px"><tbody>';
		self::row( __( 'MCP endpoint', 'kirki-mcp' ), $endpoint );
		self::row( __( 'Kirki', 'kirki-mcp' ), defined( 'KIRKI_VERSION' ) ? KIRKI_VERSION : __( 'Not active', 'kirki-mcp' ) );
		self::row( __( 'MCP Adapter', 'kirki-mcp' ), class_exists( '\WP\MCP\Plugin' ) ? __( 'Active', 'kirki-mcp' ) : __( 'Not active', 'kirki-mcp' ) );
		self::row( __( 'This plugin', 'kirki-mcp' ), KIRKI_MCP_VERSION );
		echo '</tbody></table>';
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'profile.php#application-passwords-section' ) ) . '">' . esc_html__( 'Create an application password', 'kirki-mcp' ) . '</a></p>';
		echo '<h2>' . esc_html__( 'What an agent should call', 'kirki-mcp' ) . '</h2>';
		echo '<p>' . esc_html__( 'The adapter exposes three meta-tools. Call mcp-adapter-execute-ability with ability_name set to one of the Kirki abilities, for example kirki-mcp/create-page.', 'kirki-mcp' ) . '</p>';
		echo '</div>';
	}

	/**
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	private static function row( $label, $value ) {
		echo '<tr><th style="width:180px">' . esc_html( $label ) . '</th><td><code>' . esc_html( $value ) . '</code></td></tr>';
	}
}
