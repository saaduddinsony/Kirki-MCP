<?php
/**
 * Coverage for 0.4.0 repositories: templates, dynamic content, interactions, Customizer registry, payment safety.
 *
 * @package KirkiMCP
 */

use KirkiMCP\Access;
use KirkiMCP\Audit;
use KirkiMCP\Compatibility;
use KirkiMCP\Customizer_Registry;
use KirkiMCP\Document;
use KirkiMCP\Dynamic_Content_Repository;
use KirkiMCP\Ecommerce_Repository;
use KirkiMCP\Interaction_Repository;
use KirkiMCP\Spec_Compiler;
use KirkiMCP\Template_Repository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests that do not need a WordPress install.
 */
class Coverage040Test extends TestCase {

	/**
	 * Reset option stubs between tests.
	 */
	public function setUp(): void {
		$GLOBALS['kirki_mcp_test_options'] = array();
		$GLOBALS['kirki_mcp_test_can']     = false;
	}

	/**
	 * The Pro add-on is not loaded in the unit suite.
	 */
	public function test_pro_plugin_not_installed_in_unit_suite() {
		$this->assertFalse( Compatibility::pro_plugin_installed() );
		$this->assertSame( '', Compatibility::pro_plugin_version() );
		$this->assertFalse( Compatibility::is_pro_user() );
	}

	/**
	 * Payment writes stay off until the opt-in option is set.
	 */
	public function test_store_payment_writes_default_off() {
		$this->assertFalse( Access::store_payment_writes_allowed() );
		Access::set_store_payment_writes( true );
		$this->assertTrue( Access::store_payment_writes_allowed() );
		$this->assertFalse( Access::can_write_store_payments() );
		$GLOBALS['kirki_mcp_test_can'] = true;
		$this->assertTrue( Access::can_write_store_payments() );
		Access::set_store_payment_writes( false );
		$this->assertFalse( Access::store_payment_writes_allowed() );
	}

	/**
	 * Stored '1' still counts as enabled.
	 */
	public function test_store_payment_writes_accepts_string_one() {
		update_option( 'kirki_mcp_allow_store_payment_writes', '1' );
		$this->assertTrue( Access::store_payment_writes_allowed() );
	}

	/**
	 * Credential keys are redacted on the way out.
	 */
	public function test_ecommerce_redacts_secret_keys() {
		$redacted = Ecommerce_Repository::redact(
			array(
				'name'            => 'Stripe',
				'secret_key'      => 'sk_live_example',
				'publishable_key' => 'pk_live_example',
				'nested'          => array(
					'api_key' => 'abc',
					'label'   => 'ok',
				),
			)
		);
		$this->assertSame( 'Stripe', $redacted['name'] );
		$this->assertSame( '[redacted]', $redacted['secret_key'] );
		$this->assertSame( '[redacted]', $redacted['publishable_key'] );
		$this->assertSame( '[redacted]', $redacted['nested']['api_key'] );
		$this->assertSame( 'ok', $redacted['nested']['label'] );
		$this->assertTrue( Ecommerce_Repository::is_secret_key( 'client_secret' ) );
		$this->assertFalse( Ecommerce_Repository::is_secret_key( 'title' ) );
	}

	/**
	 * A dry-run payment write is previewed without requiring the plugin or the opt-in.
	 */
	public function test_ecommerce_dry_run_payment_write() {
		$result = Ecommerce_Repository::request(
			'POST',
			'/checkout',
			array(
				'email'      => 'buyer@example.com',
				'secret_key' => 'sk_test',
			),
			array( 'dry_run' => true )
		);
		$this->assertTrue( $result['dry_run'] );
		$this->assertSame( 'POST', $result['method'] );
		$this->assertSame( '/checkout', $result['path'] );
		$this->assertTrue( $result['sensitive'] );
		$this->assertSame( '[redacted]', $result['body']['secret_key'] );
		$this->assertSame( 'buyer@example.com', $result['body']['email'] );
		$log = Audit::list_entries( 5 );
		$this->assertNotEmpty( $log );
		$this->assertSame( 'ecommerce-dry-run', $log[0]['action'] );
		$this->assertSame( array( 'email', 'secret_key' ), $log[0]['context']['param_keys'] );
	}

	/**
	 * Refunds, currency writes, customer delete, email tests, and bulk stay allowlisted.
	 */
	public function test_ecommerce_expanded_merchant_routes() {
		$ok = array(
			array( 'DELETE', '/customers/4' ),
			array( 'POST', '/currencies' ),
			array( 'PUT', '/currencies' ),
			array( 'DELETE', '/currencies/1' ),
			array( 'POST', '/currencies/bulk' ),
			array( 'POST', '/currency-exchange/sync' ),
			array( 'POST', '/orders/9/refunds' ),
			array( 'POST', '/settings/email/order/admin/new/preview/test-mail' ),
			array( 'POST', '/onboarding/sample-data' ),
			array( 'POST', '/products/bulk' ),
			array( 'POST', '/orders/bulk' ),
			array( 'POST', '/coupons/bulk' ),
		);
		foreach ( $ok as $row ) {
			$this->assertTrue( Ecommerce_Repository::is_allowed_route( $row[0], $row[1] ), $row[0] . ' ' . $row[1] );
			$this->assertFalse( Ecommerce_Repository::is_sensitive_route( $row[0], $row[1] ), 'not payment ' . $row[1] );
		}
	}

	/**
	 * Dynamic content bindings are allowlisted and sanitized.
	 */
	public function test_dynamic_content_sanitize() {
		$clean = Dynamic_Content_Repository::sanitize(
			array(
				'type'              => 'post',
				'value'             => 'post_title',
				'format'            => 'text',
				'postExcerptLength' => 40,
			)
		);
		$this->assertSame( 'post', $clean['type'] );
		$this->assertSame( 'post_title', $clean['value'] );
		$this->assertSame( 40, $clean['postExcerptLength'] );
		$fields = Dynamic_Content_Repository::fields();
		$this->assertArrayHasKey( 'type', $fields );
		$this->expectException( InvalidArgumentException::class );
		Dynamic_Content_Repository::sanitize(
			array(
				'type'  => 'javascript',
				'value' => 'x',
			)
		);
	}

	/**
	 * Interactions keep allowlisted events and drop unknown keys that are unsafe.
	 */
	public function test_interactions_sanitize() {
		$clean = Interaction_Repository::sanitize_interactions(
			array(
				'deviceAndClassList' => array(
					'device'    => 'md',
					'classList' => array( 'fade-in', 'bad class!' ),
				),
				'elementAsTrigger'   => array(
					'click' => array(
						'default' => array(
							array(
								'data' => array(
									'target1'         => array( 'opacity' => 1 ),
									'target1____info' => array( 'type' => 'fade' ),
								),
							),
						),
					),
				),
			)
		);
		$this->assertSame( array( 'fade-in' ), $clean['deviceAndClassList']['classList'] );
		$this->assertSame( 'md', $clean['deviceAndClassList']['device'] );
		$this->assertArrayHasKey( 'click', $clean['elementAsTrigger'] );
		$this->assertArrayHasKey( 'target1', $clean['elementAsTrigger']['click']['default'][0]['data'] );
		$this->expectException( InvalidArgumentException::class );
		Interaction_Repository::sanitize_interactions( 'nope' );
	}

	/**
	 * Interaction library items are capped and sanitized.
	 */
	public function test_interaction_library_sanitize() {
		$clean = Interaction_Repository::sanitize_library(
			array(
				array(
					'name' => 'Hero timeline',
					'data' => array(
						'md' => array(
							'animation-target' => array(
								'targets' => array(),
							),
						),
					),
				),
			)
		);
		$this->assertSame( 'Hero timeline', $clean[0]['name'] );
	}

	/**
	 * Document setters write and clear dynamic content on a real element.
	 */
	public function test_document_sets_dynamic_content() {
		$document = Document::blank();
		$compiled = $document->insert_element(
			array(
				'type' => 'heading',
				'text' => 'Hello',
			),
			'body'
		);
		$element  = $compiled['root_id'];
		$this->assertNotSame( '', $element );
		$this->assertNotSame( 'root', $element );
		$binding = Dynamic_Content_Repository::sanitize(
			array(
				'type'  => 'post',
				'value' => 'post_title',
			)
		);
		$document->set_dynamic_content( $element, $binding );
		$this->assertSame( 'post', $document->blocks[ $element ]['properties']['dynamicContent']['type'] );
		$document->set_dynamic_content( $element, null );
		$this->assertFalse( isset( $document->blocks[ $element ]['properties']['dynamicContent'] ) );
	}

	/**
	 * The MCP Customizer registry persists panels, sections, and fields.
	 */
	public function test_customizer_registry_round_trip() {
		Customizer_Registry::register_panel( 'mcp_brand', array( 'title' => 'Brand' ) );
		Customizer_Registry::register_section(
			'mcp_colors',
			array(
				'title' => 'Colors',
				'panel' => 'mcp_brand',
			)
		);
		Customizer_Registry::register_field(
			array(
				'settings' => 'mcp_accent',
				'type'     => 'color',
				'label'    => 'Accent',
				'section'  => 'mcp_colors',
				'default'  => '#112233',
			)
		);
		$all = Customizer_Registry::all();
		$this->assertArrayHasKey( 'mcp_brand', $all['panels'] );
		$this->assertArrayHasKey( 'mcp_colors', $all['sections'] );
		$this->assertArrayHasKey( 'mcp_accent', $all['fields'] );
		$this->assertSame( 'color', $all['fields']['mcp_accent']['args']['type'] );
		Customizer_Registry::unregister( 'field', 'mcp_accent' );
		$all = Customizer_Registry::all();
		$this->assertArrayNotHasKey( 'mcp_accent', $all['fields'] );
	}

	/**
	 * Unsafe Customizer ids and types are rejected.
	 */
	public function test_customizer_registry_rejects_bad_input() {
		try {
			Customizer_Registry::safe_id( 'Not Valid' );
			$this->fail( 'Expected invalid id' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertStringContainsString( 'id', strtolower( $error->getMessage() ) );
		}
		$this->expectException( InvalidArgumentException::class );
		Customizer_Registry::register_field(
			array(
				'settings' => 'mcp_bad',
				'type'     => 'malware',
				'section'  => 'mcp_colors',
			)
		);
	}

	/**
	 * A page-pack zip is detected; traversal paths are rejected.
	 */
	public function test_template_zip_kind_and_path_rules() {
		$path = sys_get_temp_dir() . '/kirki-mcp-page-' . uniqid( '', true ) . '.zip';
		$zip  = new ZipArchive();
		$this->assertTrue( true === $zip->open( $path, ZipArchive::CREATE ) );
		$zip->addFromString( 'kirki-data.json', '{"blocks":{},"styles":{}}' );
		$zip->close();
		$this->assertSame( 'page', Template_Repository::zip_kind( $path ) );
		$this->assertSame( $path, Template_Repository::assert_local_zip( $path ) );
		unlink( $path );
		try {
			Template_Repository::assert_local_zip( '/tmp/../etc/passwd.zip' );
			$this->fail( 'Expected path rejection' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertStringContainsString( 'not allowed', $error->getMessage() );
		}
	}

	/**
	 * Official catalog URLs stay on the Kirki assets host.
	 */
	public function test_template_catalog_url_is_official() {
		$this->assertSame( 'https://d31d7414w5c76z.cloudfront.net', Template_Repository::ASSETS_BASE );
		$this->assertArrayHasKey( 'heroes', Template_Repository::PREBUILT );
		$this->assertArrayHasKey( 'pages', Template_Repository::PREBUILT );
	}

	/**
	 * Site export without Kirki classes explains the missing dependency.
	 */
	public function test_site_export_requires_kirki_classes() {
		$this->expectException( RuntimeException::class );
		Template_Repository::start_export();
	}

	/**
	 * The audit log caps and never needs secret values in context to function.
	 */
	public function test_audit_log_records_and_lists() {
		for ( $i = 0; $i < 5; $i++ ) {
			Audit::record( 'test-action', array( 'n' => $i ) );
		}
		$entries = Audit::list_entries( 3 );
		$this->assertCount( 3, $entries );
		$this->assertSame( 'test-action', $entries[0]['action'] );
		$this->assertSame( 4, $entries[0]['context']['n'] );
	}
}
