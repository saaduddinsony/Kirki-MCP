<?php
/**
 * Compiler and document tests.
 *
 * @package KirkiMCP
 */

use KirkiMCP\Css;
use KirkiMCP\Document;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Kirki graph compiler.
 */
class CompilerTest extends TestCase {

	/**
	 * A landing-page spec becomes root, body, a heading, and a linked button.
	 */
	public function test_page_matches_kirki_shape() {
		$document = Document::from_sections(
			array(
				array(
					'key'      => 'hero',
					'styles'   => array(
						'md' => 'background:#0f172a;color:#fff;padding:64px;',
					),
					'elements' => array(
						array(
							'type' => 'heading',
							'text' => 'Hello <script>',
							'tag'  => 'h1',
						),
						array(
							'type'     => 'button',
							'text'     => 'Talk to us',
							'link_key' => 'contact',
						),
					),
				),
				array(
					'key'      => 'contact',
					'title'    => 'Contact',
					'elements' => array(
						array(
							'type' => 'paragraph',
							'text' => 'We reply the same day.',
						),
					),
				),
			)
		);

		$this->assertSame( 'root', $document->blocks['root']['name'] );
		$this->assertSame( array( 'body' ), $document->blocks['root']['children'] );
		$this->assertSame( 'kirki-page', $document->blocks['body']['className'] );
		$this->assertCount( 2, $document->blocks['body']['children'] );

		$hero_id = $document->keys['hero'];
		$hero    = $document->blocks[ $hero_id ];
		$this->assertSame( 'section', $hero['name'] );
		$this->assertSame( 'body', $hero['parentId'] );
		$this->assertTrue( $hero['template_mounted'] );
		$this->assertArrayNotHasKey( 'children', $document->blocks[ $hero['children'][0] ] );

		$heading = $document->blocks[ $hero['children'][0] ];
		$this->assertSame( 'h1', $heading['properties']['tag'] );
		$this->assertSame( 'Hello', $heading['properties']['contents'][0] );

		$button = $document->blocks[ $hero['children'][1] ];
		$this->assertSame( 'button', $button['name'] );
		$this->assertSame( 'section', $button['properties']['type'] );
		$this->assertSame( $document->keys['contact'], $button['properties']['attributes']['href'] );
		$label = $document->blocks[ $button['children'][0] ];
		$this->assertSame( 'text', $label['name'] );
		$this->assertSame( 'Talk to us', $label['properties']['contents'][0] );
		$this->assertArrayNotHasKey( 'children', $label );

		$style_id = $hero['styleIds'][0];
		$this->assertSame( 'class', $document->styles[ $style_id ]['type'] );
		$this->assertSame( $style_id, $document->styles[ $style_id ]['name'] );
		$this->assertStringContainsString( 'display:flex', $document->styles[ $style_id ]['variant']['md'] );
		$this->assertStringContainsString( 'background:#0f172a', $document->styles[ $style_id ]['variant']['md'] );

		$tree = $document->tree();
		$this->assertSame( 'root', $tree['name'] );
		$this->assertSame( 'Hello', $tree['children'][0]['children'][0]['children'][0]['text'] );
	}

	/**
	 * Unsafe CSS is rejected before it can be stored.
	 */
	public function test_unsafe_css_is_rejected() {
		$this->expectException( InvalidArgumentException::class );
		Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type'   => 'heading',
							'text'   => 'Hi',
							'styles' => array(
								'md' => 'color:red;</style><script>',
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Removing an element requires an explicit flag and drops its style block.
	 */
	public function test_remove_requires_confirm_and_drops_the_subtree() {
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type' => 'heading',
							'text' => 'Keep',
						),
						array(
							'type' => 'paragraph',
							'text' => 'Drop',
						),
					),
				),
			)
		);
		$section  = $document->blocks['body']['children'][0];
		$paragraph = $document->blocks[ $section ]['children'][1];

		try {
			$document->remove_element( $paragraph, false );
			$this->fail( 'Removal without confirm should throw.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertStringContainsString( 'confirm', $error->getMessage() );
		}

		$removed = $document->remove_element( $paragraph, true );
		$this->assertContains( $paragraph, $removed );
		$this->assertArrayNotHasKey( $paragraph, $document->blocks );
		$this->assertCount( 1, $document->blocks[ $section ]['children'] );
	}

	/**
	 * Style updates replace a variant instead of appending forever.
	 */
	public function test_style_update_replaces_a_variant() {
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type' => 'heading',
							'text' => 'Title',
						),
					),
				),
			)
		);
		$section = $document->blocks['body']['children'][0];
		$heading = $document->blocks[ $section ]['children'][0];
		$document->update_styles( $heading, array( 'tablet' => 'font-size:28px' ) );
		$style_id = $document->blocks[ $heading ]['styleIds'][0];
		$this->assertSame( 'font-size:28px;', $document->styles[ $style_id ]['variant']['tablet'] );
	}

	/**
	 * Color and URL sanitizers.
	 */
	public function test_sanitizers() {
		$this->assertSame( '#112233', Css::color( '#112233' ) );
		$this->assertSame( 'https://example.com/a.png', Css::url( 'https://example.com/a.png' ) );
		$this->expectException( InvalidArgumentException::class );
		Css::color( 'expression(alert(1))' );
	}
}
