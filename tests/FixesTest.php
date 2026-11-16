<?php
/**
 * Tests for the v0.2 review fixes that do not need WordPress.
 *
 * @package KirkiMCP
 */

use KirkiMCP\Css;
use KirkiMCP\Document;
use KirkiMCP\Global_Repository;
use KirkiMCP\Json_Data;
use KirkiMCP\Page_Repository;
use KirkiMCP\Spec_Compiler;
use KirkiMCP\Symbol_Repository;
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
		$section  = $document->blocks['body']['children'][0];
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
		$form     = '';
		$submit   = 0;
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
		$section  = $document->blocks['body']['children'][0];
		$heading  = $document->blocks[ $section ]['children'][0];
		$style    = $document->blocks[ $heading ]['styleIds'][0];
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
		$section  = $document->blocks['body']['children'][0];
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
		$first    = $document->blocks['body']['children'][0];
		$second   = $document->blocks['body']['children'][1];
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
		$link     = '';
		foreach ( $document->blocks as $id => $block ) {
			if ( 'link-text' === $block['name'] ) {
				$link = $id;
			}
		}
		$document->update_element( $link, array( 'href' => '/about' ) );
		$this->assertSame( '/about', $document->blocks[ $link ]['properties']['attributes']['href'] );
	}

	/**
	 * A stored localUrl cannot break out of Kirki's link tag, and entities are decoded.
	 */
	public function test_stored_font_local_url_is_escaped() {
		$font = Css::escape_font_record(
			array(
				'fontUrl'  => 'https://fonts.googleapis.com/css2?family=Inter&#038;display=swap',
				'localUrl' => 'x.css" onload="alert(1)',
			)
		);
		$this->assertStringNotContainsString( '"', $font['localUrl'] );
		$this->assertStringNotContainsString( ' ', $font['localUrl'] );
		$this->assertStringNotContainsString( '&#038;', $font['fontUrl'] );
		$this->assertStringContainsString( '&display=swap', $font['fontUrl'] );
	}

	/**
	 * Saving slugs a section title that was stored before the input check existed.
	 */
	public function test_stored_section_titles_are_slugged_on_clean() {
		$payload                               = 'x"/tabindex="1"/autofocus/onfocus="alert(document.domain)';
		$document                              = Document::from_sections(
			array(
				array(
					'title' => 'Card',
				),
			)
		);
		$section                               = $document->blocks['body']['children'][0];
		$document->blocks[ $section ]['title'] = $payload;
		$document->sanitize_stored_titles();
		$this->assertStringNotContainsString( '"', $document->blocks[ $section ]['title'] );
		$this->assertStringNotContainsString( '/', $document->blocks[ $section ]['title'] );
		$unsafe = $document->unsafe_titles();
		$this->assertSame( array(), $unsafe );
	}

	/**
	 * A symbol record is slugged even when the caller does not send a new name.
	 */
	public function test_symbol_record_slugs_a_stored_name() {
		$record = Symbol_Repository::prepare_record(
			array(
				'name' => 'x"/onload="alert(1)',
				'root' => 'root',
				'data' => array(
					'root' => array(
						'id'         => 'root',
						'name'       => 'section',
						'title'      => 'x"/onload="alert(1)',
						'parentId'   => '',
						'children'   => array(),
						'properties' => array( 'tag' => 'header' ),
					),
				),
			)
		);
		$this->assertStringNotContainsString( '"', $record['name'] );
		$this->assertStringNotContainsString( '"', $record['data']['root']['title'] );
		$this->assertSame( $record['name'], $record['data']['root']['title'] );
	}

	/**
	 * Accented Latin is kept as ASCII. A title with no Latin letters becomes section-N.
	 */
	public function test_non_latin_titles_transliterate_or_fall_back() {
		$this->assertSame( 'Cafe', Spec_Compiler::safe_label( 'Café' ) );
		$document = Document::from_sections(
			array(
				array(
					'title' => 'বাংলা',
				),
			)
		);
		$section  = $document->blocks['body']['children'][0];
		$this->assertSame( 'section-1', $document->blocks[ $section ]['title'] );
	}

	/**
	 * card-1 is the id Kirki gives the second "Card", so an explicit anchor cannot take it.
	 */
	public function test_explicit_anchor_avoids_numbered_repeats() {
		$ok = Document::from_sections(
			array(
				array( 'title' => 'Card' ),
				array( 'anchor' => 'card-1' ),
			)
		);
		$this->assertCount( 2, $ok->blocks['body']['children'] );
		$this->expectException( InvalidArgumentException::class );
		Document::from_sections(
			array(
				array( 'title' => 'Card' ),
				array( 'title' => 'Card' ),
				array( 'anchor' => 'card-1' ),
			)
		);
	}

	/**
	 * A key on a form field is not reserved, because the field is not an element.
	 */
	public function test_form_field_keys_are_not_reserved() {
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type'   => 'form',
							'email'  => 'hello@example.com',
							'fields' => array(
								array(
									'key'   => 'email-field',
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
		$this->assertArrayNotHasKey( 'email-field', $document->keys );
	}

	/**
	 * A media value cannot smuggle another attribute.
	 */
	/**
	 * An older unpublished revision is not the open stage after a newer publish.
	 */
	public function test_stale_stage_is_not_reopened() {
		$this->assertNull( Page_Repository::usable_unpublished_stage( 1, 2 ) );
		$this->assertNull( Page_Repository::usable_unpublished_stage( 2, 2 ) );
		$this->assertSame( 3, Page_Repository::usable_unpublished_stage( 3, 2 ) );
		$this->assertNull( Page_Repository::usable_unpublished_stage( null, 2 ) );
	}

	/**
	 * Navigation stays visible: hamburger mode is off, so Kirki does not hide the menu.
	 */
	public function test_navigation_is_not_a_closed_hamburger() {
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type'  => 'navigation',
							'items' => array(
								array(
									'text' => 'Work',
									'href' => '/work',
								),
							),
						),
					),
				),
			)
		);
		$nav      = null;
		foreach ( $document->blocks as $block ) {
			if ( 'navigation' === $block['name'] ) {
				$nav = $block;
			}
		}
		$this->assertFalse( $nav['properties']['navigation']['hamburger'] );
	}

	/**
	 * A symbol keeps a copy of each global class it uses.
	 */
	public function test_symbol_style_blocks_include_global_classes() {
		$styles = Symbol_Repository::include_global_classes(
			array(
				'el' => array(
					'properties' => array(
						'classesIds' => array( 'class-1' ),
					),
				),
			),
			array(),
			array(
				'class-1' => array(
					'id'   => 'class-1',
					'name' => 't-display',
					'type' => 'class',
				),
			)
		);
		$this->assertSame( 't-display', $styles['class-1']['name'] );
	}

	/**
	 * Forms show a success message and can include a select with a labelled control.
	 */
	public function test_form_success_message_and_select() {
		$document    = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type'            => 'form',
							'email'           => 'hello@example.com',
							'success_message' => 'Thanks. We have the message.',
							'fields'          => array(
								array(
									'name'     => 'need',
									'label'    => 'Need',
									'type'     => 'select',
									'required' => true,
									'options'  => array( 'Site', 'Brand' ),
								),
							),
						),
					),
				),
			)
		);
		$saw_message = false;
		$saw_select  = false;
		$saw_for     = false;
		foreach ( $document->blocks as $block ) {
			if ( isset( $block['properties']['attributes']['kirki-form'] ) && 'success-message' === $block['properties']['attributes']['kirki-form'] ) {
				$saw_message = true;
			}
			if ( 'select' === $block['name'] ) {
				$saw_select = true;
				$this->assertMatchesRegularExpression( '/^field-.+-need$/', $block['properties']['attributes']['id'] );
			}
			if ( isset( $block['properties']['attributes']['for'] ) && preg_match( '/^field-.+-need$/', $block['properties']['attributes']['for'] ) ) {
				$saw_for = true;
			}
		}
		$this->assertTrue( $saw_message );
		$this->assertTrue( $saw_select );
		$this->assertTrue( $saw_for );
		$schema = Spec_Compiler::schema();
		$this->assertStringContainsString( 'navigation', $schema['element']['type'] );
		$this->assertArrayHasKey( 'success_message', $schema['element'] );
	}

	/**
	 * A media value cannot smuggle another attribute.
	 */
	/**
	 * Select and radio option values cannot break out of Kirki's single-quoted attributes.
	 */
	public function test_option_values_and_labels_are_safe() {
		$payload = "x' autofocus onfocus='alert(document.domain)";
		$angle   = "a < b' onmouseover='alert(1)";
		foreach ( array( 'select', 'radio' ) as $type ) {
			try {
				Document::from_sections(
					array(
						array(
							'elements' => array(
								array(
									'type'   => 'form',
									'email'  => 'hello@example.com',
									'fields' => array(
										array(
											'name'    => 'plan',
											'label'   => 'Plan',
											'type'    => $type,
											'options' => array( $payload ),
										),
									),
								),
							),
						),
					)
				);
				$this->fail( $type . ' accepted a quoted option value.' );
			} catch ( InvalidArgumentException $error ) {
				$this->assertStringContainsString( 'option value', strtolower( $error->getMessage() ) );
			}

			$document = Document::from_sections(
				array(
					array(
						'elements' => array(
							array(
								'type'   => 'form',
								'email'  => 'hello@example.com',
								'fields' => array(
									array(
										'name'     => 'plan',
										'label'    => 'Plan',
										'type'     => $type,
										'required' => true,
										'options'  => array(
											array(
												'value' => 'site',
												'label' => $payload,
											),
											array(
												'value' => 'angle',
												'label' => $angle,
											),
										),
									),
								),
							),
						),
					),
				)
			);
			$labels   = array();
			$required = null;
			foreach ( $document->blocks as $block ) {
				if ( 'select' === $block['name'] && isset( $block['properties']['options']['list'] ) ) {
					foreach ( $block['properties']['options']['list'] as $option ) {
						$labels[] = $option['contents'];
					}
				}
				if ( 'radio-group' === $block['name'] ) {
					$required = $block['properties']['attributes']['required'];
					foreach ( $block['properties']['data']['child'] as $option ) {
						$labels[] = $option['content'];
					}
				}
			}
			$this->assertNotEmpty( $labels );
			foreach ( $labels as $label ) {
				$this->assertStringNotContainsString( "'", $label );
				$this->assertStringNotContainsString( '<', $label );
				$this->assertStringContainsString( '&#039;', $label );
			}
			if ( 'radio' === $type ) {
				$this->assertTrue( $required );
			}
		}
	}

	/**
	 * Two forms on one page do not share an input id.
	 */
	public function test_form_field_ids_are_unique_per_form() {
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type'   => 'form',
							'email'  => 'one@example.com',
							'fields' => array(
								array(
									'name'  => 'email',
									'label' => 'Email',
									'type'  => 'email',
								),
							),
						),
						array(
							'type'   => 'form',
							'email'  => 'two@example.com',
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
		$ids      = array();
		foreach ( $document->blocks as $block ) {
			if ( isset( $block['properties']['attributes']['id'] ) && isset( $block['properties']['attributes']['name'] ) && 'email' === $block['properties']['attributes']['name'] ) {
				$ids[] = $block['properties']['attributes']['id'];
			}
		}
		$this->assertCount( 2, array_unique( $ids ) );
	}

	/**
	 * Updating a class refreshes the copy stored on a symbol that uses it.
	 */
	public function test_symbol_class_copy_refreshes() {
		$updated = Symbol_Repository::refresh_class_copy(
			array(
				'data'        => array(
					'el' => array(
						'properties' => array(
							'classesIds' => array( 'class-1' ),
						),
					),
				),
				'styleBlocks' => array(
					'class-1' => array( 'name' => 'old' ),
				),
			),
			'class-1',
			array(
				'name'    => 't-display',
				'variant' => array( 'md' => 'font-size:64px;' ),
			)
		);
		$this->assertSame( 't-display', $updated['styleBlocks']['class-1']['name'] );
	}

	/**
	 * A media value cannot smuggle another attribute.
	 */
	/**
	 * A duplicate is always a draft, with a sanitized title and slug.
	 */
	public function test_duplicate_fields_are_a_draft() {
		$fields = Page_Repository::duplicate_fields( 'Published page', '', '' );
		$this->assertSame( 'draft', $fields['post_status'] );
		$this->assertSame( 'Published page copy', $fields['post_title'] );
		$this->assertSame( 'published-page-copy', $fields['post_name'] );
		$custom = Page_Repository::duplicate_fields( 'Published page', ' <b>New title</b> ', 'My Slug!' );
		$this->assertSame( 'draft', $custom['post_status'] );
		$this->assertSame( 'New title', $custom['post_title'] );
		$this->assertSame( 'my-slug', $custom['post_name'] );
		$this->assertStringContainsString( 'caf', Page_Repository::slug_base( 'Café menu' ) );
		$this->assertNotSame( 'page', Page_Repository::slug_base( 'বাংলা' ) );
	}

	/**
	 * Classes on navigation items and inline links are resolved, not dropped.
	 */
	public function test_classes_on_nested_items_are_resolved() {
		$sections = array(
			array(
				'elements' => array(
					array(
						'type'  => 'navigation',
						'items' => array(
							array(
								'text'    => 'Work',
								'href'    => '/work',
								'classes' => array( 'nav-link' ),
							),
						),
					),
					array(
						'type'   => 'paragraph',
						'inline' => array(
							'See ',
							array(
								'text'    => 'privacy',
								'href'    => '/privacy',
								'classes' => array( 'Missing' ),
							),
						),
					),
				),
			),
		);
		try {
			Page_Repository::apply_class_map( $sections, array( 'nav-link' => 'class-9' ) );
			$this->fail( 'An unknown class should be rejected.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertStringContainsString( 'missing', $error->getMessage() );
		}
		$sections[0]['elements'][1]['inline'][1]['classes'] = array( 'nav-link' );
		Page_Repository::apply_class_map( $sections, array( 'nav-link' => 'class-9' ) );
		$this->assertSame( array( 'class-9' ), $sections[0]['elements'][0]['items'][0]['class_ids'] );
		$this->assertSame( array( 'class-9' ), $sections[0]['elements'][1]['inline'][1]['class_ids'] );
	}

	/**
	 * Header and footer roots get different HTML ids, and a shared anchor is rejected.
	 */
	public function test_symbol_root_ids_are_unique() {
		$this->assertSame( 'makerspace-header-19', Symbol_Repository::root_html_id( 'Makerspace header', 19 ) );
		$this->assertSame( 'makerspace-footer-20', Symbol_Repository::root_html_id( 'Makerspace footer', 20 ) );
		$this->assertNotSame(
			Symbol_Repository::root_html_id( 'Makerspace header', 19 ),
			Symbol_Repository::root_html_id( 'Makerspace header', 20 )
		);
		$header = Symbol_Repository::stamp_root_id(
			array(
				'name' => 'Makerspace header',
				'root' => 'root',
				'data' => array(
					'root' => array(
						'title'      => 'Makerspace header',
						'properties' => array(),
					),
				),
			),
			19
		);
		$footer = Symbol_Repository::stamp_root_id(
			array(
				'name' => 'Makerspace header',
				'root' => 'root',
				'data' => array(
					'root' => array( 'title' => 'Makerspace header' ),
				),
			),
			20
		);
		$this->assertSame( '', Symbol_Repository::first_id_collision( Symbol_Repository::rendered_ids( $header ), Symbol_Repository::rendered_ids( $footer ) ) );
		$clash = Symbol_Repository::first_id_collision( array( 'menu' ), array( 'menu' ) );
		$this->assertSame( 'menu', $clash );
	}

	/**
	 * A class update refreshes the copy on a header symbol.
	 */
	public function test_header_symbol_class_copy_refreshes() {
		$updated = Symbol_Repository::refresh_class_copy(
			array(
				'setAs'       => 'header',
				'data'        => array(
					'nav' => array(
						'properties' => array(
							'classesIds' => array( 'class-1' ),
						),
					),
				),
				'styleBlocks' => array(
					'class-1' => array( 'name' => 'old' ),
				),
			),
			'class-1',
			array(
				'id'      => 'class-1',
				'name'    => 'nav-link',
				'variant' => array( 'md' => 'color:#111;' ),
			)
		);
		$this->assertSame( 'header', $updated['setAs'] );
		$this->assertSame( 'nav-link', $updated['styleBlocks']['class-1']['name'] );
	}

	/**
	 * A media value cannot smuggle another attribute.
	 */
	public function test_style_media_value_is_restricted() {
		$this->expectException( InvalidArgumentException::class );
		Css::head_style_blocks( '<style media=\'a" onload="alert(1)\'>body{color:red}</style>' );
	}

	/**
	 * esc_url_raw keeps apostrophes, and Kirki prints link hrefs inside single quotes.
	 */
	public function test_link_urls_cannot_break_single_quoted_hrefs() {
		$payload = "https://example.com/'onmouseover='alert(document.domain)'style='position:fixed;inset:0";
		$safe    = Css::url( $payload );
		$this->assertStringNotContainsString( "'", $safe );
		$this->assertStringNotContainsString( '"', $safe );
		$this->assertStringNotContainsString( '`', $safe );
		$this->assertStringNotContainsString( '<', $safe );
		$this->assertStringNotContainsString( '>', $safe );
		$this->assertStringContainsString( '%27', $safe );
		$spaced = Css::url( 'https://example.com/a b' );
		$this->assertStringNotContainsString( ' ', $spaced );
		$again = Css::attribute_url( $safe );
		$this->assertSame( $safe, $again );
	}

	/**
	 * Section links cannot target a heading, nav item, or form label, and a renamed target is slugged.
	 */
	public function test_section_links_only_use_safe_targets() {
		$payload  = "x' onfocus='alert(1)";
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type'  => 'heading',
							'tag'   => 'h1',
							'title' => $payload,
							'text'  => 'Hi',
						),
						array(
							'type' => 'button',
							'text' => 'Go',
							'href' => 'https://example.com',
						),
						array(
							'type'  => 'navigation',
							'items' => array(
								array(
									'text' => $payload,
									'href' => '/work',
								),
							),
						),
						array(
							'type'   => 'form',
							'email'  => 'hello@example.com',
							'name'   => 'contact" onclick="alert(1)',
							'fields' => array(
								array(
									'name'  => 'email',
									'label' => $payload,
									'type'  => 'email',
								),
							),
						),
					),
				),
			)
		);
		$button   = $this->first_block( $document, 'button' );
		foreach ( array( 'heading', 'navigation-item', 'label' ) as $type ) {
			$target = $this->first_block( $document, $type );
			try {
				$document->update_element( $button, array( 'section_id' => $target ) );
				$this->fail( $type . ' was accepted as a section link.' );
			} catch ( InvalidArgumentException $error ) {
				$this->assertStringContainsString( 'section or an element with an anchor', $error->getMessage() );
			}
		}
		$form = $this->first_block( $document, 'form' );
		$this->assertStringNotContainsString( '"', $document->blocks[ $form ]['properties']['attributes']['name'] );

		$anchored = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type' => 'heading',
							'tag'  => 'h1',
							'key'  => 'greet',
							'text' => 'Hi',
						),
						array(
							'type'     => 'button',
							'text'     => 'Go',
							'link_key' => 'greet',
						),
					),
				),
			)
		);
		$heading  = $anchored->keys['greet'];
		$anchored->update_element( $heading, array( 'title' => $payload ) );
		$this->assertStringNotContainsString( "'", $anchored->blocks[ $heading ]['title'] );
	}

	/**
	 * An apostrophe is a clear error, and a non-string does not become the word Array.
	 */
	public function test_option_apostrophe_and_non_string_are_rejected() {
		try {
			Document::from_sections(
				array(
					array(
						'elements' => array(
							array(
								'type'   => 'form',
								'email'  => 'hello@example.com',
								'fields' => array(
									array(
										'name'    => 'who',
										'label'   => 'Who',
										'type'    => 'select',
										'options' => array( "O'Brien" ),
									),
								),
							),
						),
					),
				)
			);
			$this->fail( 'An apostrophe was accepted.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertStringContainsString( 'apostrophe', $error->getMessage() );
		}
		try {
			Document::from_sections(
				array(
					array(
						'elements' => array(
							array(
								'type'   => 'form',
								'email'  => 'hello@example.com',
								'fields' => array(
									array(
										'name'    => 'who',
										'label'   => 'Who',
										'type'    => 'select',
										'options' => array(
											array(
												'value' => array( 'nested' ),
												'label' => 'Nested',
											),
										),
									),
								),
							),
						),
					),
				)
			);
			$this->fail( 'An array option value was accepted.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertStringContainsString( 'must be text', $error->getMessage() );
		}
	}

	/**
	 * Form field classes are stored on the control.
	 */
	public function test_form_field_classes_are_applied() {
		$document = Document::from_sections(
			array(
				array(
					'elements' => array(
						array(
							'type'   => 'form',
							'email'  => 'hello@example.com',
							'fields' => array(
								array(
									'name'      => 'email',
									'label'     => 'Email',
									'type'      => 'email',
									'class_ids' => array( 'class-9' ),
								),
							),
						),
					),
				),
			)
		);
		$field    = $this->first_block( $document, 'input' );
		$this->assertSame( array( 'class-9' ), $document->blocks[ $field ]['properties']['classesIds'] );
		try {
			Document::from_sections(
				array(
					array(
						'elements' => array(
							array(
								'type'   => 'form',
								'email'  => 'hello@example.com',
								'fields' => array(
									array(
										'name'    => 'email',
										'label'   => 'Email',
										'type'    => 'email',
										'classes' => array( 'not-resolved' ),
									),
								),
							),
						),
					),
				)
			);
			$this->fail( 'An unresolved form field class was ignored.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertStringContainsString( 'was not applied', $error->getMessage() );
		}
	}

	/**
	 * Two symbols rendered in one request no longer share an HTML id.
	 */
	public function test_two_symbols_on_one_page_have_different_ids() {
		$shared = array(
			'name' => 'Makerspace header',
			'root' => 'root',
			'data' => array(
				'root'  => array(
					'id'       => 'root',
					'name'     => 'section',
					'title'    => 'Makerspace header',
					'children' => array( 'child' ),
				),
				'child' => array(
					'id'       => 'child',
					'name'     => 'paragraph',
					'title'    => 'Copy',
					'parentId' => 'root',
				),
			),
		);
		$same   = Symbol_Repository::page_section_ids( array( $shared, $shared ) );
		$this->assertSame( $same[0], $same[1] );
		$header = Symbol_Repository::rekey_root( $shared, 19 );
		$footer = Symbol_Repository::rekey_root( $shared, 20 );
		$ids    = Symbol_Repository::page_section_ids( array( $header, $footer ) );
		$this->assertNotSame( $ids[0], $ids[1] );
		$this->assertSame( 'root-19', $header['root'] );
		$this->assertSame( 'root-20', $footer['data']['root-20']['id'] );
		$this->assertSame( 'root-20', $footer['data']['child']['parentId'] );
		$this->assertArrayNotHasKey( 'root', $footer['data'] );
	}

	/**
	 * Kirki editor symbols are not rewritten unless a caller names them.
	 */
	public function test_symbol_migration_skips_editor_symbols() {
		$editor = array(
			'name' => 'Mine',
			'root' => 'root',
			'data' => array(
				'root' => array( 'title' => 'Mine' ),
			),
		);
		$this->assertFalse( Symbol_Repository::should_migrate_symbol( $editor, 4, false ) );
		$this->assertTrue( Symbol_Repository::should_migrate_symbol( array( 'kirkiMcp' => 1 ), 4, false ) );
		$stamped                          = $editor;
		$stamped['data']['root']['title'] = Symbol_Repository::root_html_id( 'Mine', 4 );
		$this->assertTrue( Symbol_Repository::should_migrate_symbol( $stamped, 4, false ) );
		$this->assertTrue( Symbol_Repository::should_migrate_symbol( $editor, 4, true ) );
		try {
			Symbol_Repository::migrate_one( 4, (object) array( 'root' => 'root' ), true );
			$this->fail( 'A PHP object was loaded.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertStringContainsString( 'PHP object', $error->getMessage() );
		}
	}

	/**
	 * A symbol anchor that a page already uses is a collision.
	 */
	public function test_symbol_anchors_collide_with_page_anchors() {
		$ids = Symbol_Repository::anchor_ids_in_blocks(
			array(
				'hero' => array(
					'name'  => 'section',
					'title' => 'Hero',
				),
			)
		);
		$this->assertSame( 'hero', Symbol_Repository::first_id_collision( array( 'hero' ), $ids ) );
	}

	/**
	 * symbol_id must be a symbol post.
	 */
	public function test_symbol_id_rejects_other_post_types() {
		$this->expectException( InvalidArgumentException::class );
		Symbol_Repository::assert_symbol_post( (object) array( 'post_type' => 'page' ) );
	}

	/**
	 * Meta writes keep backslashes that WordPress would otherwise strip.
	 */
	public function test_meta_writes_keep_backslashes() {
		$original = 'content:"\\2014"';
		$stored   = Json_Data::slash( $original );
		$this->assertNotSame( $original, $stored );
		$this->assertSame( $original, stripslashes( $stored ) );
	}

	/**
	 * One class update writes a bounded batch, with header and footer first.
	 */
	public function test_symbol_class_refresh_is_batched() {
		$plan = Global_Repository::refresh_plan( range( 1, 45 ), 0 );
		$this->assertCount( Global_Repository::REFRESH_BATCH, $plan['ids'] );
		$this->assertFalse( $plan['done'] );
		$order = Global_Repository::refresh_order(
			array(
				array(
					'id'     => 3,
					'set_as' => '',
				),
				array(
					'id'     => 1,
					'set_as' => 'header',
				),
				array(
					'id'     => 2,
					'set_as' => 'footer',
				),
			)
		);
		$this->assertSame( array( 1, 2, 3 ), $order );
	}

	/**
	 * @param Document $document Document.
	 * @param string   $name Element name.
	 * @return string
	 */
	private function first_block( Document $document, $name ) {
		foreach ( $document->blocks as $id => $block ) {
			if ( isset( $block['name'] ) && $name === $block['name'] ) {
				return (string) $id;
			}
		}
		$this->fail( 'Missing ' . $name );
		return '';
	}
}
