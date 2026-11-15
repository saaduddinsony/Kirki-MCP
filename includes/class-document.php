<?php
/**
 * In-memory Kirki page graph.
 *
 * A page is a flat map of elements in post meta `kirki` under `blocks`, plus
 * style blocks in `kirki_global_style_block_random`. Rendering starts at `root`,
 * whose only child is `body`.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page document.
 */
class Document {

	/**
	 * Element map, including root and body.
	 *
	 * @var array
	 */
	public $blocks = array();

	/**
	 * Local style blocks keyed by id.
	 *
	 * @var array
	 */
	public $styles = array();

	/**
	 * Spec keys assigned by the most recent compile.
	 *
	 * @var array
	 */
	public $keys = array();

	/**
	 * Blank canvas: root plus a flex column body.
	 *
	 * @return self
	 */
	public static function blank() {
		$doc      = new self();
		$style_id = Ids::make();
		$doc->styles[ $style_id ] = array(
			'id'        => $style_id,
			'type'      => 'class',
			'name'      => $style_id,
			'isDefault' => false,
			'variant'   => array(
				'md' => 'display:flex;flex-direction:column;',
			),
		);
		$doc->blocks = array(
			'root' => array(
				'accept'   => '*',
				'children' => array( 'body' ),
				'id'       => 'root',
				'name'     => 'root',
				'styleIds' => array(),
				'title'    => 'Root',
			),
			'body' => array(
				'visibility'       => true,
				'collapse'         => false,
				'name'             => 'body',
				'title'            => 'Body',
				'properties'       => array(
					'tag' => 'div',
				),
				'styleIds'         => array( $style_id ),
				'className'        => 'kirki-page',
				'source'           => 'kirki',
				'id'               => 'body',
				'parentId'         => 'root',
				'children'         => array(),
				'template_mounted' => true,
			),
		);
		return $doc;
	}

	/**
	 * Build a page from section specs.
	 *
	 * @param array $sections Sections.
	 * @return self
	 */
	public static function from_sections( array $sections ) {
		$doc      = self::blank();
		$compiled = Spec_Compiler::compile_sections( $sections );
		$doc->merge_compiled( $compiled, 'body', null, true );
		return $doc;
	}

	/**
	 * Rehydrate a document Kirki already stored.
	 *
	 * @param mixed $blocks Block map or the whole kirki meta value.
	 * @param mixed $styles Style map.
	 * @return self
	 */
	public static function from_stored( $blocks, $styles ) {
		if ( is_array( $blocks ) && isset( $blocks['blocks'] ) && is_array( $blocks['blocks'] ) ) {
			$blocks = $blocks['blocks'];
		}
		if ( ! is_array( $blocks ) ) {
			$blocks = array();
		}
		if ( ! is_array( $styles ) ) {
			$styles = array();
		}

		$doc         = new self();
		$doc->blocks = $blocks;
		$doc->styles = $styles;

		if ( ! isset( $doc->blocks['root'] ) || ! isset( $doc->blocks['body'] ) ) {
			$blank       = self::blank();
			$doc->blocks = $blank->blocks;
			$doc->styles = $blank->styles + $doc->styles;
		}

		if ( ! isset( $doc->blocks['body']['children'] ) || ! is_array( $doc->blocks['body']['children'] ) ) {
			$doc->blocks['body']['children'] = array();
		}

		return $doc;
	}

	/**
	 * Insert one section spec onto the body.
	 *
	 * @param array    $section Section spec.
	 * @param int|null $index Position among body children.
	 * @return array Compiled payload, including keys.
	 */
	public function insert_section( array $section, $index = null ) {
		$compiled = Spec_Compiler::compile_sections( array( $section ) );
		$this->merge_compiled( $compiled, 'body', $index, false );
		return $compiled;
	}

	/**
	 * Insert one element under a container.
	 *
	 * @param array    $spec Element spec.
	 * @param string   $parent_id Parent element id.
	 * @param int|null $index Child index.
	 * @return array Compiled payload.
	 */
	public function insert_element( array $spec, $parent_id, $index = null ) {
		$this->assert_exists( $parent_id );
		$type = isset( $spec['type'] ) ? (string) $spec['type'] : '';
		$this->assert_can_contain( $parent_id, $type );
		$compiled = Spec_Compiler::compile_element_tree( $spec, $parent_id );
		$this->absorb( $compiled['blocks'], $compiled['styles'], $compiled['keys'] );
		$this->insert_child( $parent_id, $compiled['root_id'], $index );
		return $compiled;
	}

	/**
	 * Update text, tag, link, or image fields on an existing element.
	 *
	 * @param string $id Element id.
	 * @param array  $changes Changes.
	 * @return void
	 */
	public function update_element( $id, array $changes ) {
		$this->assert_exists( $id );
		if ( 'root' === $id ) {
			throw new \InvalidArgumentException( 'The root element cannot be edited.' );
		}

		$block = &$this->blocks[ $id ];
		$type  = $block['name'];

		if ( array_key_exists( 'text', $changes ) ) {
			$text = Spec_Compiler::plain_text( $changes['text'] );
			if ( isset( $block['properties']['contents'] ) ) {
				$block['properties']['contents'] = array( $text );
			}
			if ( 'button' === $type && ! empty( $block['children'][0] ) ) {
				$child_id = $block['children'][0];
				if ( isset( $this->blocks[ $child_id ]['properties']['contents'] ) ) {
					$this->blocks[ $child_id ]['properties']['contents'] = array( $text );
				}
			}
		}

		if ( ! empty( $changes['tag'] ) ) {
			$block['properties']['tag'] = Spec_Compiler::tag_for( $type, $changes['tag'] );
		}

		if ( array_key_exists( 'href', $changes ) ) {
			$this->set_href( $block, Css::url( $changes['href'] ), 'href' );
		}

		if ( ! empty( $changes['section_id'] ) ) {
			$section_id = (string) $changes['section_id'];
			$this->assert_exists( $section_id );
			$this->set_href( $block, $section_id, 'section' );
		}

		if ( ! empty( $changes['target'] ) ) {
			$target = '_blank' === $changes['target'] ? '_blank' : '';
			if ( ! isset( $block['properties']['attributes'] ) || ! is_array( $block['properties']['attributes'] ) ) {
				$block['properties']['attributes'] = array();
			}
			$block['properties']['attributes']['target'] = $target;
			if ( '_blank' === $target ) {
				$block['properties']['attributes']['rel'] = 'noopener noreferrer';
			}
		}

		if ( array_key_exists( 'src', $changes ) ) {
			if ( 'image' !== $type ) {
				throw new \InvalidArgumentException( 'src can only be set on an image.' );
			}
			$block['properties']['attributes']['src'] = Css::url( $changes['src'] );
		}

		if ( array_key_exists( 'alt', $changes ) ) {
			if ( 'image' !== $type ) {
				throw new \InvalidArgumentException( 'alt can only be set on an image.' );
			}
			$block['properties']['attributes']['alt'] = Spec_Compiler::plain_text( $changes['alt'] );
		}

		if ( isset( $changes['attachment_id'] ) ) {
			if ( 'image' !== $type ) {
				throw new \InvalidArgumentException( 'attachment_id can only be set on an image.' );
			}
			$block['properties']['wp_attachment_id'] = max( 0, (int) $changes['attachment_id'] );
		}

		if ( isset( $changes['class_ids'] ) && is_array( $changes['class_ids'] ) ) {
			$block['properties']['classesIds'] = array_values( $changes['class_ids'] );
		}

		if ( ! empty( $changes['title'] ) ) {
			$block['title'] = Spec_Compiler::plain_text( $changes['title'] );
		}
	}

	/**
	 * Replace the listed CSS variants on an element's own style block.
	 *
	 * @param string $id Element id.
	 * @param array  $styles Variant map.
	 * @return string Style block id.
	 */
	public function update_styles( $id, array $styles ) {
		$this->assert_exists( $id );
		if ( empty( $styles ) ) {
			throw new \InvalidArgumentException( 'Provide at least one style variant.' );
		}

		$style_id = $this->ensure_local_style( $id );
		foreach ( $styles as $key => $css ) {
			$this->styles[ $style_id ]['variant'][ Css::variant_key( $key ) ] = Css::sanitize( $css );
		}
		return $style_id;
	}

	/**
	 * Remove an element, its descendants, and style blocks that nothing else uses.
	 *
	 * @param string $id Element id.
	 * @param bool   $confirm Explicit confirmation.
	 * @return string[] Removed element ids.
	 */
	public function remove_element( $id, $confirm ) {
		if ( true !== $confirm ) {
			throw new \InvalidArgumentException( 'Removing an element requires confirm set to true. Read the page first.' );
		}
		$this->assert_exists( $id );
		if ( 'root' === $id || 'body' === $id ) {
			throw new \InvalidArgumentException( 'The root and body elements cannot be removed.' );
		}

		$removed = $this->collect_descendants( $id );
		$removed[] = $id;

		$parent_id = isset( $this->blocks[ $id ]['parentId'] ) ? $this->blocks[ $id ]['parentId'] : '';
		if ( isset( $this->blocks[ $parent_id ]['children'] ) && is_array( $this->blocks[ $parent_id ]['children'] ) ) {
			$this->blocks[ $parent_id ]['children'] = array_values(
				array_filter(
					$this->blocks[ $parent_id ]['children'],
					function ( $child_id ) use ( $id ) {
						return $child_id !== $id;
					}
				)
			);
		}

		$style_ids = array();
		foreach ( $removed as $element_id ) {
			if ( ! empty( $this->blocks[ $element_id ]['styleIds'] ) ) {
				foreach ( $this->blocks[ $element_id ]['styleIds'] as $style_id ) {
					$style_ids[] = $style_id;
				}
			}
			unset( $this->blocks[ $element_id ] );
		}

		foreach ( array_unique( $style_ids ) as $style_id ) {
			if ( ! $this->style_is_used( $style_id ) ) {
				unset( $this->styles[ $style_id ] );
			}
		}

		return $removed;
	}

	/**
	 * Compact tree for agents.
	 *
	 * @param string $id Starting element.
	 * @return array
	 */
	public function tree( $id = 'root' ) {
		if ( ! isset( $this->blocks[ $id ] ) ) {
			return array();
		}
		$block = $this->blocks[ $id ];
		$node  = array(
			'id'    => $id,
			'name'  => isset( $block['name'] ) ? $block['name'] : '',
			'title' => isset( $block['title'] ) ? $block['title'] : '',
		);
		if ( isset( $block['properties']['tag'] ) ) {
			$node['tag'] = $block['properties']['tag'];
		}
		if ( isset( $block['properties']['contents'][0] ) && is_string( $block['properties']['contents'][0] ) ) {
			$node['text'] = $block['properties']['contents'][0];
		}
		if ( isset( $block['properties']['attributes']['href'] ) && '' !== $block['properties']['attributes']['href'] ) {
			$node['href'] = $block['properties']['attributes']['href'];
		}
		if ( isset( $block['properties']['attributes']['src'] ) && '' !== $block['properties']['attributes']['src'] ) {
			$node['src'] = $block['properties']['attributes']['src'];
		}
		if ( ! empty( $block['styleIds'] ) ) {
			$node['style_ids'] = array_values( $block['styleIds'] );
		}
		if ( ! empty( $block['properties']['classesIds'] ) ) {
			$node['class_ids'] = array_values( $block['properties']['classesIds'] );
		}
		if ( ! empty( $block['children'] ) && is_array( $block['children'] ) ) {
			$node['children'] = array();
			foreach ( $block['children'] as $child_id ) {
				if ( isset( $this->blocks[ $child_id ] ) ) {
					$node['children'][] = $this->tree( $child_id );
				}
			}
		}
		return $node;
	}

	/**
	 * Style blocks referenced by elements on this page.
	 *
	 * @return string[]
	 */
	public function local_style_ids() {
		$ids = array();
		foreach ( $this->blocks as $block ) {
			if ( empty( $block['styleIds'] ) || ! is_array( $block['styleIds'] ) ) {
				continue;
			}
			foreach ( $block['styleIds'] as $style_id ) {
				if ( isset( $this->styles[ $style_id ] ) ) {
					$ids[] = $style_id;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Global class ids referenced from properties.classesIds.
	 *
	 * @return string[]
	 */
	public function class_ids() {
		$ids = array();
		foreach ( $this->blocks as $block ) {
			if ( empty( $block['properties']['classesIds'] ) || ! is_array( $block['properties']['classesIds'] ) ) {
				continue;
			}
			foreach ( $block['properties']['classesIds'] as $class_id ) {
				$ids[] = (string) $class_id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Style summary safe to return to an agent.
	 *
	 * @return array
	 */
	public function style_summary() {
		$summary = array();
		foreach ( $this->styles as $id => $style ) {
			$summary[] = array(
				'id'      => $id,
				'name'    => isset( $style['name'] ) ? $style['name'] : '',
				'variant' => isset( $style['variant'] ) ? $style['variant'] : array(),
			);
		}
		return $summary;
	}

	/**
	 * Merge a compile_sections() result onto a parent.
	 *
	 * @param array    $compiled Compiler result.
	 * @param string   $parent_id Parent.
	 * @param int|null $index Index.
	 * @param bool     $replace_children Replace the parent's children instead of inserting.
	 * @return void
	 */
	private function merge_compiled( array $compiled, $parent_id, $index, $replace_children ) {
		$this->absorb( $compiled['blocks'], $compiled['styles'], $compiled['keys'] );
		if ( $replace_children ) {
			$this->blocks[ $parent_id ]['children'] = array_values( $compiled['body_children'] );
			return;
		}
		foreach ( $compiled['body_children'] as $offset => $child_id ) {
			$at = null === $index ? null : ( (int) $index + (int) $offset );
			$this->insert_child( $parent_id, $child_id, $at );
		}
	}

	/**
	 * Copy compiled blocks and styles into this document.
	 *
	 * @param array $blocks Blocks.
	 * @param array $styles Styles.
	 * @param array $keys Keys.
	 * @return void
	 */
	private function absorb( array $blocks, array $styles, array $keys ) {
		foreach ( $blocks as $id => $block ) {
			$this->blocks[ $id ] = $block;
		}
		foreach ( $styles as $id => $style ) {
			$this->styles[ $id ] = $style;
		}
		foreach ( $keys as $key => $id ) {
			$this->keys[ $key ] = $id;
		}
	}

	/**
	 * Insert a child id at an index.
	 *
	 * @param string   $parent_id Parent.
	 * @param string   $child_id Child.
	 * @param int|null $index Index.
	 * @return void
	 */
	private function insert_child( $parent_id, $child_id, $index ) {
		if ( ! isset( $this->blocks[ $parent_id ]['children'] ) || ! is_array( $this->blocks[ $parent_id ]['children'] ) ) {
			$this->blocks[ $parent_id ]['children'] = array();
		}
		$children = $this->blocks[ $parent_id ]['children'];
		if ( null === $index || (int) $index >= count( $children ) ) {
			$children[] = $child_id;
		} else {
			$index = max( 0, (int) $index );
			array_splice( $children, $index, 0, array( $child_id ) );
		}
		$this->blocks[ $parent_id ]['children'] = array_values( $children );
	}

	/**
	 * Make sure an element has a local style block.
	 *
	 * @param string $id Element id.
	 * @return string Style id.
	 */
	private function ensure_local_style( $id ) {
		$style_ids = isset( $this->blocks[ $id ]['styleIds'] ) ? $this->blocks[ $id ]['styleIds'] : array();
		foreach ( $style_ids as $style_id ) {
			if ( isset( $this->styles[ $style_id ] ) ) {
				if ( ! isset( $this->styles[ $style_id ]['variant'] ) || ! is_array( $this->styles[ $style_id ]['variant'] ) ) {
					$this->styles[ $style_id ]['variant'] = array();
				}
				return $style_id;
			}
		}

		$style_id = Ids::make( $this->styles );
		$this->styles[ $style_id ] = array(
			'id'        => $style_id,
			'type'      => 'class',
			'name'      => $style_id,
			'isDefault' => false,
			'variant'   => array(),
		);
		if ( ! isset( $this->blocks[ $id ]['styleIds'] ) || ! is_array( $this->blocks[ $id ]['styleIds'] ) ) {
			$this->blocks[ $id ]['styleIds'] = array();
		}
		$this->blocks[ $id ]['styleIds'][] = $style_id;
		return $style_id;
	}

	/**
	 * Set a link href on button or link-block elements.
	 *
	 * @param array  $block Element, by reference.
	 * @param string $href Href or section element id.
	 * @param string $type Kirki link type.
	 * @return void
	 */
	private function set_href( array &$block, $href, $type ) {
		if ( ! in_array( $block['name'], array( 'button', 'link-block' ), true ) ) {
			throw new \InvalidArgumentException( 'Links can be set on a button or a link-block.' );
		}
		if ( ! isset( $block['properties']['attributes'] ) || ! is_array( $block['properties']['attributes'] ) ) {
			$block['properties']['attributes'] = array();
		}
		$block['properties']['type']                = $type;
		$block['properties']['attributes']['href'] = $href;
	}

	/**
	 * @param string $id Element id.
	 * @return void
	 */
	private function assert_exists( $id ) {
		if ( ! isset( $this->blocks[ $id ] ) ) {
			throw new \InvalidArgumentException( 'Element "' . $id . '" was not found. Read the page and use an id from the tree.' );
		}
	}

	/**
	 * Containers that may receive a given child type.
	 *
	 * @param string $parent_id Parent.
	 * @param string $child_type Child type.
	 * @return void
	 */
	private function assert_can_contain( $parent_id, $child_type ) {
		$parent = $this->blocks[ $parent_id ]['name'];
		$ok     = array(
			'body'       => array( 'section', 'div', 'heading', 'paragraph', 'text', 'button', 'image', 'link-block' ),
			'section'    => array( 'section', 'div', 'heading', 'paragraph', 'text', 'button', 'image', 'link-block' ),
			'div'        => array( 'section', 'div', 'heading', 'paragraph', 'text', 'button', 'image', 'link-block' ),
			'link-block' => array( 'div', 'heading', 'paragraph', 'text', 'button', 'image' ),
			'button'     => array( 'text', 'heading' ),
		);
		if ( ! isset( $ok[ $parent ] ) || ! in_array( $child_type, $ok[ $parent ], true ) ) {
			throw new \InvalidArgumentException( 'A ' . $parent . ' cannot contain a ' . $child_type . '.' );
		}
	}

	/**
	 * Descendant ids, not including $id.
	 *
	 * @param string $id Element id.
	 * @return string[]
	 */
	private function collect_descendants( $id ) {
		$found = array();
		$children = isset( $this->blocks[ $id ]['children'] ) ? $this->blocks[ $id ]['children'] : array();
		if ( ! is_array( $children ) ) {
			return $found;
		}
		foreach ( $children as $child_id ) {
			if ( ! isset( $this->blocks[ $child_id ] ) ) {
				continue;
			}
			$found[] = $child_id;
			$found   = array_merge( $found, $this->collect_descendants( $child_id ) );
		}
		return $found;
	}

	/**
	 * Whether any remaining element references a style block.
	 *
	 * @param string $style_id Style id.
	 * @return bool
	 */
	private function style_is_used( $style_id ) {
		foreach ( $this->blocks as $block ) {
			if ( ! empty( $block['styleIds'] ) && in_array( $style_id, $block['styleIds'], true ) ) {
				return true;
			}
		}
		return false;
	}
}
