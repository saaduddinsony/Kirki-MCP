<?php
/**
 * Coverage for 0.3.0 repositories: eCommerce allowlist, Customizer keys, Pro detection, Content Manager routes.
 *
 * @package KirkiMCP
 */

use KirkiMCP\Collection_Repository;
use KirkiMCP\Compatibility;
use KirkiMCP\Customizer_Repository;
use KirkiMCP\Ecommerce_Repository;
use KirkiMCP\Spec_Compiler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests that do not need a WordPress install.
 */
class CoverageTest extends TestCase {

	/**
	 * Kirki eCommerce reports as absent when its constants and class are missing.
	 */
	public function test_ecommerce_status_when_not_installed() {
		$this->assertFalse( Ecommerce_Repository::installed() );
		$status = Ecommerce_Repository::status();
		$this->assertFalse( $status['installed'] );
		$this->assertSame( 'kirki-ecommerce', $status['plugin_slug'] );
		$this->assertSame( 'kirki/ecommerce/v1', $status['rest_namespace'] );
		$this->assertFalse( $status['requires_kirki_builder'] );
		$this->assertSame( '', $status['version'] );
	}

	/**
	 * The request() method explains the missing plugin instead of calling REST.
	 */
	public function test_ecommerce_request_is_graceful_when_missing() {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Kirki eCommerce is not installed' );
		Ecommerce_Repository::request( 'GET', '/products' );
	}

	/**
	 * Allowlisted catalog, order, coupon, migration, and settings routes pass.
	 */
	public function test_ecommerce_allowlist_accepts_merchant_routes() {
		$ok = array(
			array( 'GET', '/products' ),
			array( 'GET', '/products/12' ),
			array( 'POST', '/products' ),
			array( 'PUT', '/products/12' ),
			array( 'DELETE', '/products/12' ),
			array( 'GET', '/orders' ),
			array( 'POST', '/orders' ),
			array( 'PATCH', '/orders/3/action' ),
			array( 'GET', '/coupons/validate' ),
			array( 'GET', '/settings/general' ),
			array( 'PUT', '/settings' ),
			array( 'GET', '/dashboard/overview' ),
			array( 'GET', '/analytics/metrics/sales' ),
			array( 'GET', '/migration/sources' ),
			array( 'POST', '/migration/scan' ),
			array( 'POST', '/migration/runs' ),
			array( 'POST', '/migration/runs/4/chunk' ),
			array( 'GET', '/shipping-methods' ),
			array( 'GET', '/payment-methods' ),
			array( 'GET', '/online-payments' ),
			array( 'GET', '/offline-payments/1' ),
		);
		foreach ( $ok as $row ) {
			$this->assertTrue( Ecommerce_Repository::is_allowed_route( $row[0], $row[1] ), $row[0] . ' ' . $row[1] );
		}
	}

	/**
	 * Payment webhooks, gateway installs, and password changes stay blocked.
	 */
	public function test_ecommerce_allowlist_rejects_sensitive_routes() {
		$blocked = array(
			array( 'POST', '/payment/webhook/stripe' ),
			array( 'GET', '/payment/webhook/paypal' ),
			array( 'POST', '/online-payments/install' ),
			array( 'POST', '/online-payments/update' ),
			array( 'PUT', '/online-payments/1' ),
			array( 'DELETE', '/online-payments/1' ),
			array( 'PUT', '/password-change' ),
			array( 'POST', '/checkout' ),
			array( 'GET', '/cart' ),
			array( 'POST', '/wp-admin/anything' ),
		);
		foreach ( $blocked as $row ) {
			$this->assertFalse( Ecommerce_Repository::is_allowed_route( $row[0], $row[1] ), $row[0] . ' ' . $row[1] );
		}
	}

	/**
	 * Namespace prefixes and query strings are stripped before matching.
	 */
	public function test_ecommerce_normalize_path() {
		$this->assertSame( '/products', Ecommerce_Repository::normalize_path( 'kirki/ecommerce/v1/products?page=2' ) );
		$this->assertSame( '/products/9', Ecommerce_Repository::normalize_path( '/kirki/ecommerce/v1/products/9/' ) );
		$this->assertTrue( Ecommerce_Repository::is_allowed_route( 'GET', '/kirki/ecommerce/v1/products?foo=1' ) );
	}

	/**
	 * Request still refuses a disallowed path even if the plugin appeared installed.
	 *
	 * We cannot flip installed() here; is_allowed_route is the gate the mutate ability relies on.
	 */
	public function test_ecommerce_disallowed_route_message() {
		// installed() is false, so request() fails on the missing plugin first.
		// The allowlist itself is what agents must consult.
		$this->assertFalse( Ecommerce_Repository::is_allowed_route( 'POST', '/payment/webhook/stripe' ) );
	}

	/**
	 * Customizer option keys that look like secrets or core settings are rejected.
	 */
	public function test_customizer_blocks_secret_option_keys() {
		$blocked = array( 'siteurl', 'home', 'admin_email', 'auth_key', 'auth_salt', 'license_key', 'mail_password', 'api_secret', 'kirki_ecommerce_payment_stripe' );
		foreach ( $blocked as $name ) {
			try {
				Customizer_Repository::safe_option_key( $name );
				$this->fail( 'Expected rejection for ' . $name );
			} catch ( InvalidArgumentException $error ) {
				$this->assertStringContainsString( 'cannot be read or written', $error->getMessage() );
			}
		}
		$this->assertSame( 'my_theme_option', Customizer_Repository::safe_option_key( 'my_theme_option' ) );
	}

	/**
	 * Theme mod names are sanitized; values are limited.
	 */
	public function test_customizer_safe_key_and_value() {
		$this->assertSame( 'header_textcolor', Customizer_Repository::safe_key( 'header_textcolor' ) );
		$this->assertSame( 12, Customizer_Repository::safe_value( 12 ) );
		$this->assertSame( array( 'a' => 'b' ), Customizer_Repository::safe_value( array( 'a' => 'b' ) ) );
		$this->expectException( InvalidArgumentException::class );
		Customizer_Repository::safe_value( fopen( 'php://memory', 'r' ) );
	}

	/**
	 * Pro detection is false when Kirki HelperFunctions is absent, and does not invent a key.
	 */
	public function test_pro_detection_without_helper() {
		$this->assertFalse( class_exists( '\Kirki\HelperFunctions', false ) );
		$this->assertFalse( Compatibility::is_pro_user() );
	}

	/**
	 * Content Manager routes are allowlisted; eCommerce collection paths are not confused with them.
	 */
	public function test_content_manager_allowlist() {
		$this->assertTrue( Collection_Repository::is_allowed_route( 'GET', '/content-manager/collections' ) );
		$this->assertTrue( Collection_Repository::is_allowed_route( 'POST', '/content-manager/collections' ) );
		$this->assertTrue( Collection_Repository::is_allowed_route( 'DELETE', '/content-manager/collections/4' ) );
		$this->assertTrue( Collection_Repository::is_allowed_route( 'GET', '/content-manager/collections/items' ) );
		$this->assertFalse( Collection_Repository::is_allowed_route( 'GET', '/collections' ) );
		$this->assertFalse( Collection_Repository::is_allowed_route( 'POST', '/payment/webhook/x' ) );
		$this->assertFalse( Collection_Repository::is_allowed_route( 'GET', '/content-manager/other' ) );
	}

	/**
	 * New compiler types are advertised in the schema.
	 */
	public function test_schema_includes_video_slider_tabs() {
		$types = Spec_Compiler::element_types();
		$this->assertContains( 'video', $types );
		$this->assertContains( 'slider', $types );
		$this->assertContains( 'tabs', $types );
		$schema = Spec_Compiler::schema();
		$this->assertStringContainsString( 'slider', $schema['element']['type'] );
	}

	/**
	 * A video element stores a sanitized https source.
	 */
	public function test_video_compiles() {
		$compiled = Spec_Compiler::compile_element_tree(
			array(
				'type' => 'video',
				'src'  => 'https://example.com/clip.mp4',
			),
			'root'
		);
		$root_id  = $compiled['root_id'];
		$block    = $compiled['blocks'][ $root_id ];
		$this->assertSame( 'video', $block['name'] );
		$this->assertSame( 'https://example.com/clip.mp4', $block['properties']['attributes']['src'] );
	}

	/**
	 * A slider compiles each slide as a child frame.
	 */
	public function test_slider_compiles_slides() {
		$compiled = Spec_Compiler::compile_element_tree(
			array(
				'type'   => 'slider',
				'slides' => array(
					array(
						'title'    => 'One',
						'elements' => array(
							array(
								'type' => 'heading',
								'text' => 'First',
							),
						),
					),
					array(
						'title'    => 'Two',
						'elements' => array(
							array(
								'type' => 'paragraph',
								'text' => 'Second',
							),
						),
					),
				),
			),
			'root'
		);
		$root     = $compiled['blocks'][ $compiled['root_id'] ];
		$this->assertSame( 'slider', $root['name'] );
		$this->assertCount( 2, $root['children'] );
	}

	/**
	 * Tabs compile a tab_menu and tab_content with matching panes.
	 */
	public function test_tabs_compile_menu_and_panes() {
		$compiled = Spec_Compiler::compile_element_tree(
			array(
				'type' => 'tabs',
				'tabs' => array(
					array(
						'title'    => 'A',
						'elements' => array(
							array(
								'type' => 'paragraph',
								'text' => 'Alpha',
							),
						),
					),
					array(
						'title'    => 'B',
						'elements' => array(
							array(
								'type' => 'paragraph',
								'text' => 'Beta',
							),
						),
					),
				),
			),
			'root'
		);
		$root     = $compiled['blocks'][ $compiled['root_id'] ];
		$this->assertSame( 'tabs', $root['name'] );
		$this->assertCount( 2, $root['children'] );
		$menu = $compiled['blocks'][ $root['children'][0] ];
		$body = $compiled['blocks'][ $root['children'][1] ];
		$this->assertSame( 'tab_menu', $menu['name'] );
		$this->assertSame( 'tab_content', $body['name'] );
		$this->assertCount( 2, $menu['children'] );
		$this->assertCount( 2, $body['children'] );
		$this->assertSame( 'tab', $compiled['blocks'][ $menu['children'][0] ]['name'] );
		$this->assertSame( 'tab_pane', $compiled['blocks'][ $body['children'][0] ]['name'] );
	}

	/**
	 * Tabs need at least two titled tabs.
	 */
	public function test_tabs_require_two_tabs() {
		$this->expectException( InvalidArgumentException::class );
		Spec_Compiler::compile_element_tree(
			array(
				'type' => 'tabs',
				'tabs' => array(
					array( 'title' => 'Only' ),
				),
			),
			'root'
		);
	}
}
