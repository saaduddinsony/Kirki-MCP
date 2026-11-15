<?php
/**
 * Tests for the v0.2 review fixes that do not need WordPress.
 *
 * @package KirkiMCP
 */

use KirkiMCP\Css;
use KirkiMCP\Document;
use KirkiMCP\Spec_Compiler;
use PHPUnit\Framework\TestCase;

/**
 * Review fixes.
 */
class FixesTest extends TestCase {

	/**
	 * CSS cannot break out of a declaration list.
	 */
	public function test_css_rejects_selector_breakout() {
		$this->expectException( InvalidArgumentException::class );
		Css::sanitize( 'color:red;} body{display:none} .x{color:blue' );
	}

	/**
	 * Unknown viewports and pseudos are rejected.
	 */
	public function test_variant_keys_follow_kirki() {
		$this->assertSame( 'tablet_hover', Css::variant_key( 'tablet_hover' ) );
		$this->expectException( InvalidArgumentException::class );
		Css::variant_key( 'desktop_focus' );
	}

	/**
	 * Font stacks stay quoted so Kirki does not wrap the whole list.
	 */
	public function test_font_stack_is_quoted_per_family() {
		$this->assertSame( '"Inter", "Helvetica Neue", "Helvetica", "Arial", sans-serif', Css::font_stack( 'Inter, Helvetica Neue, Helvetica, Arial, sans-serif' ) );
	}

	/**
	 * A stored page without body is not replaced by a blank canvas.
	 */
	public function test_missing_body_is_rejected() {
		$this->expectException( InvalidArgumentException::class );
		Document::from_stored(
			array(
				'root'   => array(
					'id'       => 'root',
					'name'     => 'root',
					'children' => array( 'custom' ),
				),
				'custom' => array(
					'id'   => 'custom',
					'name' => 'section',
				),
			),
			array()
		);
	}

	/**
	 * Symbol trees keep their own root instead of inventing a body.
	 */
	public function test_symbol_tree_includes_its_content() {
		$document = Document::from_symbol(
			array(
				'root'  => array(
					'id'         => 'root',
					'name'       => 'section',
					'title'      => 'Header',
					'parentId'   => '',
					'children'   => array( 'title' ),
					'properties' => array( 'tag' => 'header' ),
				),
				'title' => array(
					'id'         => 'title',
					'name'       => 'heading',
					'title'      => 'Heading',
					'parentId'   => 'root',
					'properties' => array(
						'tag'      => 'h1',
						'contents' => array( 'Northwind' ),
					),
				),
			),
			array(),
			'root'
		);
		$tree     = $document->tree( 'root' );
		$this->assertSame( 'Northwind', $tree['children'][0]['text'] );
		$document->validate( array(), 'symbol', 'root' );
	}

	/**
	 * Navigation, forms, and inline links compile into connected elements.
	 */
	public function test_navigation_form_and_inline_link() {
		$document = Document::from_sections(
			array(
				array(
					'key'      => 'contact',
					'elements' => array(
						array(
							'type'  => 'navigation',
							'items' => array(
								array(
									'text' => 'Home',
									'href' => '/',
								),
								array(
									'text'     => 'Contact',
									'link_key' => 'contact',
								),
							),
						),
						array(
							'type'   => 'form',
							'email'  => 'hello@example.com',
							'fields' => array(
								array(
									'name'     => 'email',
									'label'    => 'Email',
									'type'     => 'email',
									'required' => true,
								),
							),
						),
						array(
							'type'   => 'paragraph',
							'inline' => array(
								'Email ',
								array(
									'text' => 'us',
									'href' => 'mailto:hello@example.com',
								),
							),
						),
					),
				),
			)
		);
		$document->validate();
		$section = $document->tree()['children'][0]['children'][0];
		$this->assertSame( 'navigation', $section['children'][0]['name'] );
		$this->assertSame( 'form', $section['children'][1]['name'] );
		$paragraph = $section['children'][2];
		$this->assertSame( 'us', $paragraph['inline'][0]['text'] );
	}

	/**
	 * Raw class ids cannot be written onto an element.
	 */
	/**
	 * A classed element does not keep the local defaults that were covering the class.
	 */
	public function test_classed_elements_drop_default_css_and_keys_become_anchors() {
		$document = Document::from_sections(
			array(
				array(
					'key'      => 'damex-digital',
					'elements' => array(
						array(
							'type'    => 'heading',
							'tag'     => 'h1',
							'text'    => 'Hello',
							'classes' => array( 't-display' ),
							'styles'  => array( 'md' => 'color:#111' ),
						),
						array(
							'type'    => 'list',
							'items'   => array( 'One', 'Two' ),
							'hide_on' => array( 'mobile' ),
						),
					),
				),
			)
		);
		$section = $document->blocks['body']['children'][0];
		$this->assertSame( 'damex-digital', $document->blocks[ $section ]['title'] );
		$heading = $document->blocks[ $section ]['children'][0];
		$css     = $document->styles[ $document->blocks[ $heading ]['styleIds'][0] ]['variant']['md'];
		$this->assertStringNotContainsString( 'font-size:16px', $css );
		$this->assertStringContainsString( 'color:#111 !important', $css );
		$list = $document->blocks[ $section ]['children'][1];
		$this->assertTrue( $document->blocks[ $list ]['properties']['isTextList'] );
		$this->assertSame( 'display:none;', $document->styles[ $document->blocks[ $list ]['styleIds'][0] ]['variant']['mobile'] );
	}

	/**
	 * An unknown element type names the supported types.
	 */
	public function test_unknown_element_type_is_explicit() {
		$document = Document::blank();
		try {
			$document->insert_element( array( 'type' => 'widget' ), 'body', null );
			$this->fail( 'An unknown element type should be rejected.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertStringContainsString( 'Unknown element type "widget"', $error->getMessage() );
			$this->assertStringContainsString( 'form', $error->getMessage() );
		}
	}

	/**
	 * Raw class ids cannot be written onto an element.
	 */
	public function test_raw_class_ids_are_rejected() {
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type' => 'heading',
							'text' => 'Hello',
						),
					),
				),
			)
		);
		$section  = $document->blocks['body']['children'][0];
		$heading  = $document->blocks[ $section ]['children'][0];
		$this->expectException( InvalidArgumentException::class );
		$document->update_element( $heading, array( 'class_ids' => array( 'not-a-real-class' ) ) );
	}

	/**
	 * Head code is style blocks only, and a broken font URL is rejected.
	 */
	public function test_custom_code_and_font_urls_reject_markup() {
		$this->assertSame(
			'<style>:root{--ink:#17181a}</style>',
			Css::head_style_blocks( '<style>:root{--ink:#17181a}</style>' )
		);
		$this->assertSame(
			'<style>.a > .b{color:red}</style>',
			Css::head_style_blocks( '<style>.a > .b{color:red}</style>' )
		);
		$this->assertSame(
			'<style media="screen">body{color:red}</style>',
			Css::head_style_blocks( '<style media="screen">body{color:red}</style>' )
		);
		$this->assertStringNotContainsString( '"', Css::escape_font_url( 'https://fonts.googleapis.com/css2?family=X"><script>alert(1)</script>' ) );
		$this->assertStringNotContainsString( '<', Css::escape_font_url( 'https://fonts.googleapis.com/css2?family=X"><script>alert(1)</script>' ) );
		$rejected = array(
			'<iframe srcdoc="&lt;script&gt;parent.alert(document.domain)&lt;/script&gt;">',
			'<a href="javascript&#58;alert(1)">x</a>',
			'<object data="data:text/html;base64,PHNjcmlwdD4=">',
			'<svg><a xlink:href="&#106;avascript:alert(1)">',
			'<base href="https://evil.example/">',
			'<link rel="stylesheet" href="https://evil.example/x.css">',
			'<style>@import url(https://evil.example/x.css);</style>',
			'<style></style><script>alert(1)</script>',
			'<style>a{color:red}</style><p>after</p>',
			'<style>a{color:red}</style></style>',
		);
		foreach ( $rejected as $html ) {
			try {
				Css::head_style_blocks( $html );
				$this->fail( 'Head code should have been rejected: ' . $html );
			} catch ( InvalidArgumentException $error ) {
				$this->assertNotSame( '', $error->getMessage() );
			}
		}

		$this->assertSame(
			'https://fonts.googleapis.com/css2?family=Fraunces&display=swap',
			Css::google_font_url( 'Fraunces' )
		);
		$this->expectException( InvalidArgumentException::class );
		Css::google_font_url( 'Fraunces', 'https://fonts.googleapis.com/css2?family=X"><script>alert(document.domain)</script>' );
	}

	/**
	 * Shortcodes are removed before Kirki runs them in a meta description.
	 */
	public function test_meta_text_drops_shortcodes() {
		$this->assertSame( 'Hello there', Css::plain_meta( 'Hello [caption] there' ) );
	}

	/**
	 * A form can be submitted, and a button can be added to it later.
	 */
	public function test_forms_have_a_submit_button() {
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type'   => 'form',
							'email'  => 'hello@example.com',
							'fields' => array(
								array(
									'name'  => 'email',
									'label' => 'Email',
									'type'  => 'email',
								),
							),
						),
					),
				),
			)
		);
		$form   = '';
		$submit = 0;
		foreach ( $document->blocks as $id => $block ) {
			if ( 'form' === $block['name'] ) {
				$form = $id;
			}
			if ( 'button' === $block['name'] && isset( $block['properties']['attributes']['type'] ) && 'submit' === $block['properties']['attributes']['type'] ) {
				++$submit;
			}
		}
		$this->assertSame( 1, $submit );
		$document->insert_element(
			array(
				'type'   => 'button',
				'text'   => 'Send again',
				'submit' => true,
			),
			$form,
			null
		);
		$submit = 0;
		foreach ( $document->blocks as $block ) {
			if ( 'button' === $block['name'] && isset( $block['properties']['attributes']['type'] ) && 'submit' === $block['properties']['attributes']['type'] ) {
				++$submit;
			}
		}
		$this->assertSame( 2, $submit );
	}

	/**
	 * Classes attached after creation remove the local defaults that covered them.
	 */
	public function test_classes_added_later_drop_default_css() {
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type' => 'heading',
							'tag'  => 'h1',
							'text' => 'Hello',
						),
					),
				),
			)
		);
		$section = $document->blocks['body']['children'][0];
		$heading = $document->blocks[ $section ]['children'][0];
		$style   = $document->blocks[ $heading ]['styleIds'][0];
		$this->assertStringContainsString( 'font-size:16px', $document->styles[ $style ]['variant']['md'] );
		$document->set_class_ids( $heading, array( 't-display' ) );
		$this->assertStringNotContainsString( 'font-size:16px', $document->styles[ $style ]['variant']['md'] );
	}

	/**
	 * Section titles and symbol names cannot break out of Kirki's unescaped id attribute.
	 */
	public function test_section_titles_and_symbol_names_are_safe_ids() {
		$payload  = 'x"/tabindex="1"/autofocus/onfocus="alert(document.domain)';
		$document = Document::from_sections(
			array(
				array(
					'title' => $payload,
				),
			)
		);
		$section = $document->blocks['body']['children'][0];
		$this->assertStringNotContainsString( '"', $document->blocks[ $section ]['title'] );
		$this->assertStringNotContainsString( '/', $document->blocks[ $section ]['title'] );
		$this->assertStringNotContainsString( '=', $document->blocks[ $section ]['title'] );
		$this->assertSame( '', preg_replace( '/[A-Za-z0-9 _.-]/', '', $document->blocks[ $section ]['title'] ) );
		$document->update_element( $section, array( 'title' => $payload ) );
		$this->assertStringNotContainsString( '"', $document->blocks[ $section ]['title'] );
		$this->assertSame( '', preg_replace( '/[A-Za-z0-9 _.-]/', '', Spec_Compiler::safe_label( $payload ) ) );
	}

	/**
	 * Kirki numbers repeated section titles. Explicit anchors still collide after slugging.
	 */
	public function test_repeated_section_titles_are_allowed() {
		$document = Document::from_sections(
			array(
				array( 'title' => 'Card' ),
				array( 'title' => 'Card' ),
			)
		);
		$first  = $document->blocks['body']['children'][0];
		$second = $document->blocks['body']['children'][1];
		$this->assertSame( 'Card', $document->blocks[ $first ]['title'] );
		$this->assertSame( 'Card', $document->blocks[ $second ]['title'] );
	}

	/**
	 * Our Work and our-work are the same explicit anchor.
	 */
	public function test_anchor_collision_is_normalized() {
		$this->expectException( InvalidArgumentException::class );
		Document::from_sections(
			array(
				array( 'anchor' => 'Our Work' ),
				array( 'key' => 'our-work' ),
			)
		);
	}

	/**
	 * Keys on inline links and navigation items are real element ids.
	 */
	public function test_inline_and_nav_keys_are_reserved() {
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type'   => 'paragraph',
							'inline' => array(
								'See ',
								array(
									'key'  => 'privacy',
									'text' => 'privacy',
									'href' => '/privacy',
								),
							),
						),
						array(
							'type'  => 'navigation',
							'items' => array(
								array(
									'key'  => 'work',
									'text' => 'Work',
									'href' => '/work',
								),
							),
						),
					),
				),
			)
		);
		$this->assertNotEmpty( $document->keys['privacy'] );
		$this->assertArrayHasKey( $document->keys['privacy'], $document->blocks );
		$this->assertSame( 'link-text', $document->blocks[ $document->keys['privacy'] ]['name'] );
		$this->assertNotEmpty( $document->keys['work'] );
		$this->assertSame( 'link-text', $document->blocks[ $document->keys['work'] ]['name'] );
	}

	/**
	 * Two elements cannot share an anchor.
	 */
	public function test_duplicate_anchors_are_rejected() {
		$this->expectException( InvalidArgumentException::class );
		Document::from_sections(
			array(
				array( 'key' => 'damex-digital' ),
				array( 'anchor' => 'damex-digital' ),
			)
		);
	}

	/**
	 * An inline link href can be changed after the paragraph is created.
	 */
	public function test_link_text_href_can_be_edited() {
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type'   => 'paragraph',
							'inline' => array(
								'Read the ',
								array(
									'text' => 'privacy policy',
									'href' => '/privacy',
								),
							),
						),
					),
				),
			)
		);
		$link = '';
		foreach ( $document->blocks as $id => $block ) {
			if ( 'link-text' === $block['name'] ) {
				$link = $id;
			}
		}
		$document->update_element( $link, array( 'href' => '/about' ) );
		$this->assertSame( '/about', $document->blocks[ $link ]['properties']['attributes']['href'] );
	}
}
