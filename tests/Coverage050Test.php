<?php
/**
 * Coverage for 0.5.0 Kirki WooCommerce Bridge proxy and catalog.
 *
 * @package KirkiMCP
 */

use KirkiMCP\Access;
use KirkiMCP\Woo_Bridge_Repository;

/**
 * @covers \KirkiMCP\Woo_Bridge_Repository
 */
class Coverage050Test extends PHPUnit\Framework\TestCase {

	/**
	 * Reset capability stubs.
	 */
	protected function setUp(): void {
		$GLOBALS['kirki_mcp_test_can'] = false;
	}

	/**
	 * The official catalog is 21 operations, even without the Bridge plugin.
	 */
	public function test_catalog_has_twenty_one_operations() {
		$ops = Woo_Bridge_Repository::catalog();
		$this->assertCount( 21, $ops );
		$this->assertCount( 21, Woo_Bridge_Repository::operations() );
		$this->assertCount( 21, Woo_Bridge_Repository::ability_names() );
	}

	/**
	 * Ability names are the official slugs with a woo- prefix.
	 */
	public function test_ability_names_match_official_slugs() {
		$expected = array(
			'woo-get-status',
			'woo-list-elements',
			'woo-get-element',
			'woo-render-element',
			'woo-build-block',
			'woo-list-dynamic-fields',
			'woo-resolve-dynamic-field',
			'woo-get-settings',
			'woo-update-settings',
			'woo-list-template-contexts',
			'woo-list-templates',
			'woo-create-template',
			'woo-resolve-template',
			'woo-get-template',
			'woo-update-template',
			'woo-delete-template',
			'woo-list-designs',
			'woo-create-design',
			'woo-get-design',
			'woo-add-design-elements',
			'woo-delete-design',
		);
		$this->assertSame( $expected, Woo_Bridge_Repository::ability_names() );
		$this->assertSame( $expected, array_values( array_unique( $expected ) ) );
	}

	/**
	 * Caps, hints, and required path fields follow docs/API.md.
	 */
	public function test_operation_caps_and_hints() {
		$by_name = array();
		foreach ( Woo_Bridge_Repository::operations() as $op ) {
			$by_name[ $op['ability'] ] = $op;
			$this->assertContains( $op['cap'], array( 'edit_posts', 'manage_woocommerce' ) );
			$this->assertContains( $op['method'], array( 'GET', 'POST', 'PUT', 'DELETE' ) );
			if ( $op['readonly'] ) {
				$this->assertTrue( $op['idempotent'] );
				$this->assertFalse( $op['destructive'] );
			}
		}

		$read_posts = array( 'get-status', 'list-elements', 'get-element', 'render-element', 'build-block', 'list-dynamic-fields', 'resolve-dynamic-field', 'list-template-contexts' );
		foreach ( $read_posts as $name ) {
			$this->assertSame( 'edit_posts', $by_name[ $name ]['cap'] );
			$this->assertTrue( $by_name[ $name ]['readonly'] );
		}

		$this->assertSame( 'manage_woocommerce', $by_name['get-settings']['cap'] );
		$this->assertTrue( $by_name['get-settings']['readonly'] );
		$this->assertSame( 'manage_woocommerce', $by_name['update-settings']['cap'] );
		$this->assertTrue( $by_name['update-settings']['idempotent'] );
		$this->assertFalse( $by_name['update-settings']['readonly'] );

		$this->assertTrue( $by_name['delete-template']['destructive'] );
		$this->assertTrue( $by_name['delete-template']['idempotent'] );
		$this->assertTrue( $by_name['delete-design']['destructive'] );
		$this->assertFalse( $by_name['delete-design']['idempotent'] );

		$schema = Woo_Bridge_Repository::input_schema( $by_name['delete-design'] );
		$this->assertContains( 'confirm', $schema['required'] );
		$this->assertArrayHasKey( 'confirm', $schema['properties'] );
		$this->assertContains( 'id', $schema['required'] );

		$keep = Woo_Bridge_Repository::input_schema( $by_name['get-status'] );
		$this->assertArrayNotHasKey( 'confirm', $keep['properties'] );
	}

	/**
	 * Route substitution fills named captures and rejects missing ids.
	 */
	public function test_fill_route_substitutes_path_params() {
		$this->assertSame(
			'/templates/abc123',
			Woo_Bridge_Repository::fill_route( '/templates/(?P<id>[a-z0-9]+)', array( 'id' => 'abc123' ) )
		);
		$this->assertSame(
			'/designs/42/elements',
			Woo_Bridge_Repository::fill_route( '/designs/(?P<id>[0-9]+)/elements', array( 'id' => 42 ) )
		);
		$this->assertSame(
			'/elements/kirki-woo-product-title',
			Woo_Bridge_Repository::fill_route( '/elements/(?P<name>[a-z0-9-]+)', array( 'name' => 'kirki-woo-product-title' ) )
		);
		$this->assertSame( array( 'id' ), Woo_Bridge_Repository::route_params( '/templates/(?P<id>[a-z0-9]+)' ) );
		$this->expectException( InvalidArgumentException::class );
		Woo_Bridge_Repository::fill_route( '/templates/(?P<id>[a-z0-9]+)', array() );
	}

	/**
	 * The unit suite does not load the Bridge plugin.
	 */
	public function test_bridge_not_installed_in_unit_suite() {
		$this->assertFalse( Woo_Bridge_Repository::installed() );
		$status = Woo_Bridge_Repository::status();
		$this->assertFalse( $status['installed'] );
		$this->assertFalse( $status['ready'] );
		$this->assertSame( 'kirki-woocommerce-bridge', $status['plugin_slug'] );
		$this->assertSame( 'kirki-woo/v1', $status['rest_namespace'] );
		$this->assertSame( 21, $status['operations'] );
		$this->assertSame( 'https://github.com/saaduddinsony/kirki-woocommerce-bridge', $status['repo'] );
	}

	/**
	 * Proxying without the plugin explains how to install it.
	 */
	public function test_run_without_plugin_explains_install() {
		$op = Woo_Bridge_Repository::operation( 'get-status' );
		try {
			Woo_Bridge_Repository::run( $op, array() );
			$this->fail( 'Expected a RuntimeException.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'not installed', $e->getMessage() );
			$this->assertStringContainsString( 'kirki-woocommerce-bridge', $e->getMessage() );
		}
	}

	/**
	 * Destructive deletes refuse to run without confirm: true.
	 */
	public function test_destructive_ops_require_confirm() {
		$op = Woo_Bridge_Repository::operation( 'delete-template' );
		$this->assertTrue( $op['destructive'] );
		try {
			Woo_Bridge_Repository::run( $op, array( 'id' => 'abc123' ) );
			$this->fail( 'Expected confirm to be required.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'confirm', $e->getMessage() );
		}
	}

	/**
	 * Official contexts and element groups are documented.
	 */
	public function test_contexts_and_groups() {
		$this->assertSame(
			array(
				'single_product',
				'shop',
				'product_archive',
				'product_search',
				'cart',
				'cart_empty',
				'checkout',
				'order_received',
				'my_account',
				'my_account_login',
			),
			Woo_Bridge_Repository::contexts()
		);
		$this->assertSame( array( 'product', 'shop', 'cart', 'account', 'general' ), Woo_Bridge_Repository::element_groups() );
		$list = Woo_Bridge_Repository::operation( 'list-elements' );
		$this->assertSame( Woo_Bridge_Repository::element_groups(), $list['input']['group']['enum'] );
	}

	/**
	 * Unknown official slugs are rejected.
	 */
	public function test_unknown_operation_is_rejected() {
		$this->expectException( InvalidArgumentException::class );
		Woo_Bridge_Repository::operation( 'not-a-real-op' );
	}

	/**
	 * Woo caps follow current_user_can.
	 */
	public function test_woo_access_caps() {
		$GLOBALS['kirki_mcp_test_can'] = false;
		$this->assertFalse( Access::can_read_woo_bridge() );
		$this->assertFalse( Access::can_manage_woo_bridge() );
		$GLOBALS['kirki_mcp_test_can'] = true;
		$this->assertTrue( Access::can_read_woo_bridge() );
		$this->assertTrue( Access::can_manage_woo_bridge() );
	}
}
