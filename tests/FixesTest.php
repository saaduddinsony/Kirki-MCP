<?php
/**
 * Tests for the v0.2 review fixes that do not need WordPress.
 *
 * @package KirkiMCP
 */

use KirkiMCP\Css;
use KirkiMCP\Document;
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
}
