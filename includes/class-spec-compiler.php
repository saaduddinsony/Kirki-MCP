<?php
/**
 * Turns a small layout spec into a Kirki element graph and style blocks.
 *
 * The saved shape matches elements the Kirki 6.4 editor inserts: visibility,
 * collapse, name, title, properties, styleIds, className, source, id, parentId,
 * and children plus template_mounted on containers. Style blocks use type
 * "class" and a variant map whose keys are viewport names (md, tablet, mobile).
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spec compiler.
 */
class Spec_Compiler {

	const MAX_ELEMENTS = 400;
	const MAX_SECTIONS = 40;

	/**
	 * Element types an agent may create.
	 *
	 * @return string[]
	 */
	public static function element_types() {
		return array( 'section', 'div', 'heading', 'paragraph', 'text', 'button', 'image', 'link-block', 'link-text', 'navigation', 'form' );
	}

	/**
	 * Description returned by the get-element-schema ability.
	 *
	 * @return array
	 */
	public static function schema() {
		return array(
			'element_types'       => self::element_types(),
			'style_variants'      => Css::variant_vocabulary(),
			'style_variant_notes' => array(
				'md'        => 'Desktop. This is the default viewport.',
				'tablet'    => 'Tablet.',
				'mobile'    => 'Phone.',
				'md_hover'  => 'Desktop hover. tablet_hover and mobile_hover work the same way.',
				'md_active' => 'Desktop active.',
			),
			'notes'               => array(
				'styles are ordinary CSS declaration lists, such as "color:#fff;padding:24px;". They are appended to Kirki\'s default rules for that element, so later declarations win.',
				'Give a section or element a key such as "hero" and point a button at it with link_key. The button becomes an in-page link to that section.',
				'classes is a list of global class names created with upsert-global-class. They are stored on properties.classesIds, which is how Kirki attaches global classes.',
				'Button labels are stored both on the button and on a child text element, because the editor shows the child and the front end renders it.',
				'Do not send raw Kirki JSON. Use this spec. The plugin writes the element map Kirki expects.',
			),
			'section'             => array(
				'key'      => 'Optional stable name you can pass to link_key.',
				'tag'      => 'section, div, main, header, footer, or aside. Default section.',
				'styles'   => 'Map of variant key to CSS declarations.',
				'classes'  => 'Global class names.',
				'elements' => 'Child elements: heading, paragraph, text, button, image, div, link-block, or a nested section.',
			),
			'element'             => array(
				'type'          => 'One of the element types.',
				'text'          => 'Plain text for heading, paragraph, text, and button.',
				'tag'           => 'Heading level or an allowed tag for the type.',
				'href'          => 'http(s) URL or site-relative path for button and link-block.',
				'link_key'      => 'Key of a section on this page.',
				'target'        => '_self or _blank.',
				'src'           => 'Image URL.',
				'alt'           => 'Image alt text.',
				'attachment_id' => 'WordPress attachment id for an image, when you have one.',
				'styles'        => 'Variant CSS map.',
				'classes'       => 'Global class names.',
				'elements'      => 'Children of div, section, and link-block.',
			),
		);
	}

	/**
	 * Compile top-level sections.
	 *
	 * @param array $sections Section specs.
	 * @return array{blocks:array,styles:array,body_children:array,keys:array}
	 */
	public static function compile_sections( array $sections ) {
		if ( count( $sections ) > self::MAX_SECTIONS ) {
			throw new \InvalidArgumentException( 'A page can contain at most ' . self::MAX_SECTIONS . ' sections per call.' );
		}

		$keys  = array();
		$count = 0;
		self::reserve_keys( $sections, $keys, true );

		$blocks   = array();
		$styles   = array();
		$children = array();

		foreach ( $sections as $section ) {
			if ( ! is_array( $section ) ) {
				throw new \InvalidArgumentException( 'Each section must be an object.' );
			}
			$section['type'] = 'section';
			$children[]      = self::compile_element( $section, 'body', $blocks, $styles, $keys, $count );
		}

		return array(
			'blocks'        => $blocks,
			'styles'        => $styles,
			'body_children' => $children,
			'keys'          => $keys,
		);
	}

	/**
	 * Compile one element and its descendants.
	 *
	 * @param array  $spec Element spec.
	 * @param string $parent_id Parent element id.
	 * @return array{blocks:array,styles:array,root_id:string,keys:array}
	 */
	public static function compile_element_tree( array $spec, $parent_id ) {
		$keys  = array();
		$count = 0;
		self::reserve_keys( array( $spec ), $keys, false );

		$blocks = array();
		$styles = array();
		$id     = self::compile_element( $spec, $parent_id, $blocks, $styles, $keys, $count );

		return array(
			'blocks'  => $blocks,
			'styles'  => $styles,
			'root_id' => $id,
			'keys'    => $keys,
		);
	}

	/**
	 * Plain text stored in properties.contents. Kirki escapes it at render time.
	 *
	 * @param mixed $text Raw text.
	 * @return string
	 */
	public static function plain_text( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text );
		if ( strlen( $text ) > 5000 ) {
			throw new \InvalidArgumentException( 'Text is longer than 5000 characters.' );
		}
		return $text;
	}

	/**
	 * Allowed tag for an element type.
	 *
	 * @param string $type Element name.
	 * @param string $tag Requested tag.
	 * @return string
	 */
	public static function tag_for( $type, $tag ) {
		$allowed = array(
			'section'    => array( 'section', 'div', 'main', 'header', 'footer', 'aside' ),
			'div'        => array( 'div', 'section', 'main', 'header', 'footer', 'aside' ),
			'heading'    => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ),
			'paragraph'  => array( 'p', 'span' ),
			'text'       => array( 'span' ),
			'button'     => array( 'button', 'a' ),
			'link-block' => array( 'a' ),
			'image'      => array( 'img' ),
		);
		$tag     = strtolower( trim( (string) $tag ) );
		if ( ! isset( $allowed[ $type ] ) || ! in_array( $tag, $allowed[ $type ], true ) ) {
			throw new \InvalidArgumentException( 'Tag "' . $tag . '" is not allowed on a ' . $type . '.' );
		}
		return $tag;
	}

	/**
	 * Assign ids to keys before compiling, so link_key can point forward.
	 *
	 * @param array $elements Specs.
	 * @param array $keys Key map, by reference.
	 * @param bool  $force_section Whether the top level is sections.
	 * @return void
	 */
	private static function reserve_keys( array $elements, array &$keys, $force_section ) {
		unset( $force_section );
		$used = array();
		foreach ( $keys as $id ) {
			$used[ $id ] = true;
		}
		self::reserve_keys_into( $elements, $keys, $used );
	}

	/**
	 * @param array $elements Specs.
	 * @param array $keys Key map.
	 * @param array $used Ids already assigned.
	 * @return void
	 */
	private static function reserve_keys_into( array $elements, array &$keys, array &$used ) {
		foreach ( $elements as $spec ) {
			if ( ! is_array( $spec ) ) {
				continue;
			}
			if ( ! empty( $spec['key'] ) ) {
				$key = self::key_name( $spec['key'] );
				if ( isset( $keys[ $key ] ) ) {
					throw new \InvalidArgumentException( 'Duplicate key "' . $key . '".' );
				}
				$id           = Ids::make( $used );
				$keys[ $key ] = $id;
				$used[ $id ]  = true;
			}
			if ( ! empty( $spec['elements'] ) && is_array( $spec['elements'] ) ) {
				self::reserve_keys_into( $spec['elements'], $keys, $used );
			}
		}
	}

	/**
	 * Compile one element into $blocks and $styles.
	 *
	 * @param array  $spec Spec.
	 * @param string $parent_id Parent id.
	 * @param array  $blocks Block map.
	 * @param array  $styles Style map.
	 * @param array  $keys Reserved keys.
	 * @param int    $count Element counter.
	 * @return string New element id.
	 */
	private static function compile_element( array $spec, $parent_id, array &$blocks, array &$styles, array &$keys, &$count ) {
		++$count;
		if ( $count > self::MAX_ELEMENTS ) {
			throw new \InvalidArgumentException( 'A page can contain at most ' . self::MAX_ELEMENTS . ' elements per call.' );
		}

		$type = isset( $spec['type'] ) ? (string) $spec['type'] : '';
		if ( ! in_array( $type, self::element_types(), true ) ) {
			throw new \InvalidArgumentException( 'Unsupported element type "' . $type . '".' );
		}

		if ( ! empty( $spec['key'] ) ) {
			$id = $keys[ self::key_name( $spec['key'] ) ];
		} else {
			$id = Ids::make( $blocks );
		}

		$style_id = self::add_style( $styles, self::variants_for( $type, isset( $spec['styles'] ) ? $spec['styles'] : array() ) );

		$block = array(
			'visibility' => true,
			'collapse'   => false,
			'name'       => $type,
			'title'      => self::title_for( $type, $spec ),
			'properties' => self::properties_for( $type, $spec, $keys ),
			'styleIds'   => array( $style_id ),
			'className'  => '',
			'source'     => 'kirki',
			'id'         => $id,
			'parentId'   => (string) $parent_id,
		);

		if ( ! empty( $spec['class_ids'] ) && is_array( $spec['class_ids'] ) ) {
			$class_ids = array();
			foreach ( $spec['class_ids'] as $class_id ) {
				$class_id = (string) $class_id;
				if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $class_id ) ) {
					throw new \InvalidArgumentException( 'A global class id is not valid.' );
				}
				$class_ids[] = $class_id;
			}
			$block['properties']['classesIds'] = $class_ids;
		}

		if ( in_array( $type, array( 'section', 'div', 'link-block' ), true ) ) {
			$child_ids = array();
			$nested    = ( isset( $spec['elements'] ) && is_array( $spec['elements'] ) ) ? $spec['elements'] : array();
			foreach ( $nested as $child ) {
				if ( ! is_array( $child ) ) {
					throw new \InvalidArgumentException( 'Each child element must be an object.' );
				}
				$child_ids[] = self::compile_element( $child, $id, $blocks, $styles, $keys, $count );
			}
			$block['children']         = $child_ids;
			$block['template_mounted'] = true;
		}

		if ( in_array( $type, array( 'heading', 'paragraph', 'text' ), true ) && ! empty( $spec['inline'] ) && is_array( $spec['inline'] ) ) {
			$block['properties']['contents'] = self::inline_contents( $spec['inline'], $id, $blocks, $styles, $keys, $count );
		}

		if ( 'navigation' === $type ) {
			$block['children']         = array( self::navigation_items( isset( $spec['items'] ) ? $spec['items'] : array(), $id, $blocks, $styles, $keys, $count ) );
			$block['template_mounted'] = true;
		}

		if ( 'form' === $type ) {
			$block['children']         = self::form_fields( isset( $spec['fields'] ) ? $spec['fields'] : array(), $id, $blocks, $styles, $keys, $count );
			$block['template_mounted'] = true;
		}

		if ( 'button' === $type ) {
			$label = self::plain_text( isset( $spec['text'] ) ? $spec['text'] : 'Button' );
			if ( '' === $label ) {
				$label = 'Button';
			}
			$block['properties']['contents'] = array( $label );
			$text_id                         = Ids::make( $blocks );
			$text_style                      = self::add_style(
				$styles,
				array(
					'md' => 'font-family:inherit;font-size:inherit;font-weight:inherit;line-height:inherit;color:inherit;margin:0;padding:0;',
				)
			);
			$blocks[ $text_id ]              = array(
				'visibility' => true,
				'collapse'   => false,
				'name'       => 'text',
				'title'      => 'Text',
				'properties' => array(
					'contents' => array( $label ),
					'tag'      => 'span',
				),
				'styleIds'   => array( $text_style ),
				'className'  => '',
				'source'     => 'kirki',
				'id'         => $text_id,
				'parentId'   => $id,
			);
			$block['children']               = array( $text_id );
			$block['template_mounted']       = true;
			++$count;
		}

		$blocks[ $id ] = $block;
		return $id;
	}

	/**
	 * Runtime properties for one element.
	 *
	 * @param string $type Element type.
	 * @param array  $spec Spec.
	 * @param array  $keys Reserved keys.
	 * @return array
	 */
	private static function properties_for( $type, array $spec, array $keys ) {
		switch ( $type ) {
			case 'section':
				return array(
					'tag' => self::tag_for( 'section', isset( $spec['tag'] ) ? $spec['tag'] : 'section' ),
				);
			case 'div':
				return array(
					'tag' => self::tag_for( 'div', isset( $spec['tag'] ) ? $spec['tag'] : 'div' ),
				);
			case 'heading':
				return array(
					'contents' => array( self::plain_text( isset( $spec['text'] ) ? $spec['text'] : 'Heading' ) ),
					'tag'      => self::tag_for( 'heading', isset( $spec['tag'] ) ? $spec['tag'] : 'h2' ),
				);
			case 'paragraph':
				return array(
					'contents' => array( self::plain_text( isset( $spec['text'] ) ? $spec['text'] : '' ) ),
					'tag'      => self::tag_for( 'paragraph', isset( $spec['tag'] ) ? $spec['tag'] : 'p' ),
				);
			case 'text':
				return array(
					'contents' => array( self::plain_text( isset( $spec['text'] ) ? $spec['text'] : '' ) ),
					'tag'      => 'span',
				);
			case 'link-text':
				$href = self::href_for( $spec, $keys );
				return array(
					'contents'   => array( self::plain_text( isset( $spec['text'] ) ? $spec['text'] : 'Link' ) ),
					'tag'        => 'a',
					'type'       => $href['type'],
					'attributes' => array(
						'href'   => $href['href'],
						'target' => self::target_for( $spec ),
					),
				);
			case 'navigation':
				return array(
					'tag'        => 'nav',
					'navigation' => array(
						'version'                  => 'v2',
						'hamburger'                => true,
						'navButtonViewPort'        => 'tablet',
						'showNavigationChildrenOn' => 'click',
						'editorProps'              => array(
							'activeNavigationItemId' => '',
							'showOverlay'            => false,
						),
					),
				);
			case 'form':
				$email = isset( $spec['email'] ) ? trim( (string) $spec['email'] ) : '';
				if ( ! preg_match( '/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i', $email ) ) {
					throw new \InvalidArgumentException( 'A form needs an email address for delivery.' );
				}
				$subject = self::plain_text( isset( $spec['subject'] ) ? $spec['subject'] : 'New form submission' );
				return array(
					'tag'        => 'form',
					'form'       => array(
						'actions'       => array(
							array(
								'type'      => 'email',
								'title'     => 'Email',
								'emailList' => $email,
								'replyTo'   => '',
								'name'      => '',
								'subject'   => $subject,
								'body'      => '',
							),
						),
						'onSubmit'      => array(
							'type'  => 'show-notification',
							'value' => array( 'state' => 'none' ),
						),
						'maxEntry'      => array(
							'restricted' => false,
							'value'      => '',
						),
						'responseLimit' => array(
							'restricted' => false,
							'value'      => '',
						),
						'saveData'      => true,
					),
					'attributes' => array(
						'name' => self::plain_text( isset( $spec['name'] ) ? $spec['name'] : 'Contact' ),
					),
				);
			case 'image':
				$attachment = isset( $spec['attachment_id'] ) ? (int) $spec['attachment_id'] : 0;
				return array(
					'tag'              => 'img',
					'noEndTag'         => true,
					'type'             => 'href',
					'load'             => 'lazy',
					'hiDPIStatus'      => false,
					'width'            => array(
						'value' => 'auto',
						'unit'  => '',
					),
					'height'           => array(
						'value' => 'auto',
						'unit'  => '',
					),
					'wp_attachment_id' => $attachment > 0 ? $attachment : 0,
					'attributes'       => array(
						'src'    => Css::url( isset( $spec['src'] ) ? $spec['src'] : '' ),
						'alt'    => self::plain_text( isset( $spec['alt'] ) ? $spec['alt'] : '' ),
						'href'   => '',
						'target' => '',
					),
				);
			case 'button':
			case 'link-block':
				$href = self::href_for( $spec, $keys );
				$link = array(
					'tag'        => 'button' === $type ? 'button' : 'a',
					'type'       => $href['type'],
					'attributes' => array(
						'href'   => $href['href'],
						'target' => self::target_for( $spec ),
					),
				);
				if ( 'link-block' === $type ) {
					$link['isActive'] = false;
					$link['preload']  = 'default';
				}
				if ( '_blank' === $link['attributes']['target'] ) {
					$link['attributes']['rel'] = 'noopener noreferrer';
				}
				return $link;
		}

		throw new \InvalidArgumentException( 'Unsupported element type.' );
	}

	/**
	 * Resolve a button or link href.
	 *
	 * @param array $spec Spec.
	 * @param array $keys Key map.
	 * @return array{type:string,href:string}
	 */
	private static function href_for( array $spec, array $keys ) {
		if ( ! empty( $spec['link_key'] ) ) {
			$key = self::key_name( $spec['link_key'] );
			if ( ! isset( $keys[ $key ] ) ) {
				throw new \InvalidArgumentException( 'Unknown link_key "' . $key . '".' );
			}
			return array(
				'type' => 'section',
				'href' => $keys[ $key ],
			);
		}

		if ( ! empty( $spec['href'] ) ) {
			return array(
				'type' => 'href',
				'href' => Css::url( $spec['href'] ),
			);
		}

		return array(
			'type' => 'href',
			'href' => '',
		);
	}

	/**
	 * Link target.
	 *
	 * @param array $spec Spec.
	 * @return string
	 */
	private static function target_for( array $spec ) {
		$target = isset( $spec['target'] ) ? (string) $spec['target'] : '';
		if ( '' === $target || '_self' === $target ) {
			return '';
		}
		if ( '_blank' === $target ) {
			return '_blank';
		}
		throw new \InvalidArgumentException( 'target must be _self or _blank.' );
	}

	/**
	 * Build the variant map for a new element.
	 *
	 * @param string $type Element type.
	 * @param mixed  $styles Agent styles.
	 * @return array
	 */
	private static function variants_for( $type, $styles ) {
		$defaults = self::default_variants();
		$variants = isset( $defaults[ $type ] ) ? $defaults[ $type ] : array( 'md' => '' );

		if ( ! is_array( $styles ) ) {
			return $variants;
		}

		foreach ( $styles as $key => $css ) {
			$key = Css::variant_key( $key );
			$css = Css::sanitize( $css );
			if ( '' === $css ) {
				continue;
			}
			if ( isset( $variants[ $key ] ) && '' !== $variants[ $key ] ) {
				$variants[ $key ] = rtrim( $variants[ $key ], ';' ) . ';' . $css;
			} else {
				$variants[ $key ] = $css;
			}
		}

		return $variants;
	}

	/**
	 * Default CSS copied from the Kirki 6.4.2 element definitions.
	 *
	 * @return array
	 */
	private static function default_variants() {
		return array(
			'section'    => array(
				'md' => 'display:flex;width:stretch;height:max-content;',
			),
			'div'        => array(
				'md' => 'display:flex;width:stretch;height:max-content;',
			),
			'heading'    => array(
				'md' => 'font-family:Inter,system-ui,sans-serif;font-size:16px;line-height:130%;width:stretch;height:max-content;font-weight:500;margin:0;padding:0;',
			),
			'paragraph'  => array(
				'md' => 'font-family:Inter,system-ui,sans-serif;font-size:16px;font-weight:400;line-height:150%;width:stretch;height:max-content;margin:0;padding:0;',
			),
			'text'       => array(
				'md' => 'font-family:Inter,system-ui,sans-serif;font-size:16px;font-weight:400;line-height:150%;margin:0;padding:0;',
			),
			'button'     => array(
				'md'        => 'cursor:pointer;display:flex;justify-content:center;align-items:center;gap:10px;width:max-content;height:40px;background-color:rgba(0, 0, 0, 0.8);color:#ffffff;font-size:16px;font-family:Inter,system-ui,sans-serif;padding-left:20px;padding-right:20px;border-radius:8px;outline:none;border:none;text-decoration:none;margin:0;',
				'md_hover'  => 'background-color:rgba(0, 0, 0, 1);',
				'md_active' => 'background-color:rgba(0, 0, 0, 0.6);',
			),
			'image'      => array(
				'md' => 'object-fit:cover;width:stretch;height:max-content;display:block;margin:0;padding:0;',
			),
			'link-block' => array(
				'md' => 'margin:0;padding:0;text-decoration:none;display:flex;width:stretch;height:max-content;',
			),
		);
	}

	/**
	 * Insert a style block and return its id.
	 *
	 * @param array $styles Style map.
	 * @param array $variants Variant map.
	 * @return string
	 */
	private static function add_style( array &$styles, array $variants ) {
		$id            = Ids::make( $styles );
		$styles[ $id ] = array(
			'id'        => $id,
			'type'      => 'class',
			'name'      => $id,
			'isDefault' => false,
			'variant'   => $variants,
		);
		return $id;
	}

	/**
	 * Human title stored on the element.
	 *
	 * @param string $type Element type.
	 * @param array  $spec Spec.
	 * @return string
	 */
	private static function title_for( $type, array $spec ) {
		if ( ! empty( $spec['title'] ) ) {
			$title = self::plain_text( $spec['title'] );
			if ( '' !== $title ) {
				return $title;
			}
		}
		$names = array(
			'section'    => 'Section',
			'div'        => 'Frame',
			'heading'    => 'Heading',
			'paragraph'  => 'Paragraph',
			'text'       => 'Text',
			'button'     => 'Button',
			'image'      => 'Image',
			'link-block' => 'Link',
			'link-text'  => 'Link',
			'navigation' => 'Navigation',
			'form'       => 'Form',
		);
		return isset( $names[ $type ] ) ? $names[ $type ] : 'Element';
	}

	/**
	 * Mixed text and inline links. Links are elements, referenced from contents.
	 *
	 * @param array  $parts Inline parts.
	 * @param string $parent_id Parent element id.
	 * @param array  $blocks Blocks.
	 * @param array  $styles Styles.
	 * @param array  $keys Keys.
	 * @param int    $count Counter.
	 * @return array
	 */
	private static function inline_contents( array $parts, $parent_id, array &$blocks, array &$styles, array &$keys, &$count ) {
		$contents = array();
		foreach ( $parts as $part ) {
			if ( is_string( $part ) ) {
				$contents[] = self::plain_text( $part );
				continue;
			}
			if ( ! is_array( $part ) ) {
				throw new \InvalidArgumentException( 'Each inline part must be text or a link.' );
			}
			$part['type'] = 'link-text';
			$id           = self::compile_element( $part, $parent_id, $blocks, $styles, $keys, $count );
			$contents[]   = array( 'id' => $id );
		}
		return $contents;
	}

	/**
	 * Navigation items wrapper.
	 *
	 * @param array  $items Items.
	 * @param string $parent_id Navigation id.
	 * @param array  $blocks Blocks.
	 * @param array  $styles Styles.
	 * @param array  $keys Keys.
	 * @param int    $count Counter.
	 * @return string
	 */
	private static function navigation_items( $items, $parent_id, array &$blocks, array &$styles, array &$keys, &$count ) {
		if ( ! is_array( $items ) || ! $items ) {
			throw new \InvalidArgumentException( 'A navigation needs at least one item.' );
		}
		$list_id   = Ids::make( $blocks );
		$child_ids = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				throw new \InvalidArgumentException( 'Each navigation item must be an object.' );
			}
			++$count;
			$item_id            = Ids::make( $blocks );
			$link               = $item;
			$link['type']       = 'link-text';
			$link_id            = self::compile_element( $link, $item_id, $blocks, $styles, $keys, $count );
			$blocks[ $item_id ] = self::shell(
				'navigation-item',
				self::plain_text( isset( $item['text'] ) ? $item['text'] : 'Item' ),
				$list_id,
				$item_id,
				array( $link_id ),
				array(
					'tag'            => 'div',
					'navigationItem' => array( 'showNavigationChildrenOn' => 'click' ),
				),
				$styles,
				'display:flex;align-items:center;'
			);
			$child_ids[]        = $item_id;
		}
		$blocks[ $list_id ] = self::shell(
			'navigation-items',
			'Menu',
			$parent_id,
			$list_id,
			$child_ids,
			array(
				'tag'        => 'div',
				'attributes' => array( 'kirki-menu-wrapper' => 'true' ),
			),
			$styles,
			'display:flex;column-gap:20px;row-gap:12px;flex-wrap:wrap;align-items:center;margin:0;padding:0;'
		);
		return $list_id;
	}

	/**
	 * Label and field pairs inside a form.
	 *
	 * @param array  $fields Fields.
	 * @param string $parent_id Form id.
	 * @param array  $blocks Blocks.
	 * @param array  $styles Styles.
	 * @param array  $keys Keys.
	 * @param int    $count Counter.
	 * @return string[]
	 */
	private static function form_fields( $fields, $parent_id, array &$blocks, array &$styles, array &$keys, &$count ) {
		if ( ! is_array( $fields ) || ! $fields ) {
			throw new \InvalidArgumentException( 'A form needs at least one field.' );
		}
		$ids = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				throw new \InvalidArgumentException( 'Each form field must be an object.' );
			}
			$name = isset( $field['name'] ) ? strtolower( (string) $field['name'] ) : '';
			if ( ! preg_match( '/^[a-z][a-z0-9_]{0,40}$/', $name ) ) {
				throw new \InvalidArgumentException( 'Form field names must start with a letter and use only letters, numbers, and underscores.' );
			}
			$label = self::plain_text( isset( $field['label'] ) ? $field['label'] : $name );
			$type  = isset( $field['type'] ) ? (string) $field['type'] : 'text';
			if ( ! in_array( $type, array( 'text', 'email', 'tel', 'textarea' ), true ) ) {
				throw new \InvalidArgumentException( 'Form fields must be text, email, tel, or textarea.' );
			}
			++$count;
			$wrap_id             = Ids::make( $blocks );
			$label_id            = Ids::make( $blocks );
			$field_id            = Ids::make( $blocks );
			$blocks[ $label_id ] = self::shell(
				'label',
				$label,
				$wrap_id,
				$label_id,
				array(),
				array(
					'contents' => array( $label ),
					'tag'      => 'label',
				),
				$styles,
				'font-size:14px;font-weight:600;'
			);
			unset( $blocks[ $label_id ]['children'] );
			$attributes = array(
				'name'     => $name,
				'required' => ! empty( $field['required'] ),
			);
			if ( 'textarea' === $type ) {
				$field_props = array(
					'tag'        => 'textarea',
					'attributes' => $attributes,
				);
			} else {
				$attributes['type'] = $type;
				$field_props        = array(
					'tag'        => 'input',
					'noEndTag'   => true,
					'attributes' => $attributes,
				);
			}
			$blocks[ $field_id ] = self::shell( 'textarea' === $type ? 'textarea' : 'input', $label, $wrap_id, $field_id, array(), $field_props, $styles, 'width:100%;' );
			unset( $blocks[ $field_id ]['children'] );
			$blocks[ $wrap_id ] = self::shell( 'div', $label, $parent_id, $wrap_id, array( $label_id, $field_id ), array( 'tag' => 'div' ), $styles, 'display:flex;flex-direction:column;gap:8px;width:100%;' );
			$ids[]              = $wrap_id;
		}
		return $ids;
	}

	/**
	 * One stored element plus its local style.
	 *
	 * @param string $name Element name.
	 * @param string $title Title.
	 * @param string $parent_id Parent.
	 * @param string $id Id.
	 * @param array  $children Children.
	 * @param array  $properties Properties.
	 * @param array  $styles Styles.
	 * @param string $css Desktop CSS.
	 * @return array
	 */
	private static function shell( $name, $title, $parent_id, $id, array $children, array $properties, array &$styles, $css ) {
		$style_id = self::add_style( $styles, array( 'md' => $css ) );
		$element  = array(
			'visibility'       => true,
			'collapse'         => false,
			'name'             => $name,
			'title'            => $title,
			'properties'       => $properties,
			'styleIds'         => array( $style_id ),
			'className'        => '',
			'source'           => 'kirki',
			'id'               => $id,
			'parentId'         => $parent_id,
			'template_mounted' => true,
		);
		if ( $children ) {
			$element['children'] = $children;
		}
		return $element;
	}

	/**
	 * Validate a spec key.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	private static function key_name( $key ) {
		$key = strtolower( trim( (string) $key ) );
		if ( ! preg_match( '/^[a-z][a-z0-9_-]{0,40}$/', $key ) ) {
			throw new \InvalidArgumentException( 'Keys must start with a letter and contain only letters, numbers, hyphens, and underscores.' );
		}
		return $key;
	}
}
