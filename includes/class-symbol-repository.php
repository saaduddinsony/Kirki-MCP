<?php
/**
 * Kirki symbols, including the header and footer.
 *
 * A symbol is a kirki_symbol post. Its `kirki` meta is the symbol document:
 * root, data, styleBlocks, name, category, and setAs. Header and footer are
 * symbols whose setAs is "header" or "footer". Kirki renders that symbol on
 * every page. Only one symbol can hold each role.
 *
 * Kirki 6.4.2 has no kirki/v1 route that creates or updates a symbol. Those
 * operations are admin-ajax actions (save-user-saved-symbol-data and
 * update-user-saved-symbol-data) and the ajax handler requires a wp_rest nonce.
 * Application-password clients do not have that nonce. New symbols are created
 * through Kirki's SymbolManager. Updates are written here as JSON arrays,
 * because SymbolManager always inserts a new post.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Symbol storage.
 */
class Symbol_Repository {

	/**
	 * Compact list.
	 *
	 * @return array
	 */
	public static function summaries() {
		$posts = get_posts(
			array(
				'post_type'   => 'kirki_symbol',
				'post_status' => array( 'draft', 'publish', 'private' ),
				'numberposts' => 100,
				'orderby'     => 'modified',
				'order'       => 'DESC',
			)
		);
		$items = array();
		foreach ( $posts as $post ) {
			$data    = self::read_meta( $post->ID );
			$items[] = array(
				'id'       => (int) $post->ID,
				'name'     => ! empty( $data['name'] ) ? $data['name'] : $post->post_title,
				'set_as'   => isset( $data['setAs'] ) ? $data['setAs'] : '',
				'category' => isset( $data['category'] ) ? $data['category'] : 'other',
			);
		}
		return $items;
	}

	/**
	 * One symbol, including its element tree.
	 *
	 * @param int $symbol_id Symbol post id.
	 * @return array
	 */
	public static function get( $symbol_id ) {
		$post = self::symbol( $symbol_id );
		$data = self::read_meta( $post->ID );
		$document = Document::from_stored(
			isset( $data['data'] ) ? $data['data'] : array(),
			isset( $data['styleBlocks'] ) ? $data['styleBlocks'] : array()
		);
		$root = isset( $data['root'] ) ? $data['root'] : 'root';
		return array(
			'id'       => (int) $post->ID,
			'name'     => isset( $data['name'] ) ? $data['name'] : $post->post_title,
			'set_as'   => isset( $data['setAs'] ) ? $data['setAs'] : '',
			'category' => isset( $data['category'] ) ? $data['category'] : 'other',
			'tree'     => isset( $data['data'][ $root ] ) ? $document->tree( $root ) : $document->tree(),
		);
	}

	/**
	 * Create a symbol from section specs.
	 *
	 * @param string $name Display name.
	 * @param string $role header, footer, or component.
	 * @param array  $sections Sections.
	 * @return array
	 */
	public static function create( $name, $role, array $sections ) {
		Page_Repository::assert_ready();
		$name   = self::name( $name );
		$set_as = self::role( $role );
		if ( '' !== $set_as ) {
			self::clear_role( $set_as, 0 );
		}

		$built = Json_Data::only_array( self::build( 0, $name, $set_as, $sections ), 'Symbol' );
		if ( ! class_exists( '\Kirki\App\Managers\SymbolManager' ) || ! class_exists( '\Kirki\App\DTO\Symbol\SymbolDTO' ) ) {
			throw new \RuntimeException( 'Kirki SymbolManager is not available.' );
		}

		$saved = ( new \Kirki\App\Managers\SymbolManager() )->save(
			\Kirki\App\DTO\Symbol\SymbolDTO::from_array(
				array(
					'symbolData' => $built,
				)
			)
		);
		if ( ! is_object( $saved ) || empty( $saved->id ) ) {
			throw new \RuntimeException( 'Kirki could not create the symbol.' );
		}

		$post_id = (int) $saved->id;
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_title'  => $name,
				'post_status' => 'draft',
			)
		);

		return self::get( $post_id );
	}

	/**
	 * Update role, name, or replace the symbol contents.
	 *
	 * @param int         $symbol_id Symbol id.
	 * @param string|null $name New name, or null to keep it.
	 * @param string|null $role New role, or null to keep it.
	 * @param array|null  $sections Replacement sections, or null to keep content.
	 * @return array
	 */
	public static function update( $symbol_id, $name, $role, $sections ) {
		Page_Repository::assert_ready();
		$post = self::symbol( $symbol_id );
		$data = self::read_meta( $post->ID );
		if ( empty( $data ) ) {
			throw new \InvalidArgumentException( 'That symbol has no Kirki data.' );
		}

		if ( null !== $name && '' !== $name ) {
			$name = self::name( $name );
			$data['name'] = $name;
			wp_update_post(
				array(
					'ID'         => $post->ID,
					'post_title' => $name,
				)
			);
		}

		if ( null !== $role ) {
			$set_as = self::role( $role );
			if ( '' !== $set_as ) {
				self::clear_role( $set_as, $post->ID );
			}
			$data['setAs']    = $set_as;
			$data['category'] = '' !== $set_as ? $set_as : 'other';
		}

		if ( null !== $sections ) {
			$built = self::build( $post->ID, isset( $data['name'] ) ? $data['name'] : $post->post_title, isset( $data['setAs'] ) ? $data['setAs'] : '', $sections );
			$data['data']        = $built['data'];
			$data['styleBlocks'] = $built['styleBlocks'];
			$data['root']        = 'root';
		}

		update_post_meta( $post->ID, 'kirki', $data );
		update_post_meta( $post->ID, 'kirki_editor_mode', 'kirki' );
		return self::get( $post->ID );
	}

	/**
	 * List Kirki templates. Creating templates from a ZIP is not part of this version.
	 *
	 * @return array
	 */
	public static function list_templates() {
		$posts = get_posts(
			array(
				'post_type'   => 'kirki_template',
				'post_status' => array( 'publish', 'draft', 'private' ),
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$items = array();
		foreach ( $posts as $post ) {
			$items[] = array(
				'id'     => (int) $post->ID,
				'title'  => $post->post_title,
				'status' => $post->post_status,
			);
		}
		return $items;
	}

	/**
	 * Write a new symbol document.
	 *
	 * @param int    $post_id Post id.
	 * @param string $name Name.
	 * @param string $role Role.
	 * @param array  $sections Sections.
	 * @return void
	 */
	private static function write( $post_id, $name, $role, array $sections ) {
		$set_as = self::role( $role );
		if ( '' !== $set_as ) {
			self::clear_role( $set_as, $post_id );
		}
		$built = self::build( $post_id, $name, $set_as, $sections );
		update_post_meta( $post_id, 'kirki', $built );
		update_post_meta( $post_id, 'kirki_editor_mode', 'kirki' );
	}

	/**
	 * Symbol meta value.
	 *
	 * @param int    $post_id Post id.
	 * @param string $name Name.
	 * @param string $set_as header, footer, or empty.
	 * @param array  $sections Sections.
	 * @return array
	 */
	private static function build( $post_id, $name, $set_as, array $sections ) {
		Page_Repository::resolve_classes( $sections );
		$compiled = Spec_Compiler::compile_sections( $sections );
		$blocks   = $compiled['blocks'];
		$styles   = $compiled['styles'];

		foreach ( $compiled['body_children'] as $child_id ) {
			if ( isset( $blocks[ $child_id ] ) ) {
				$blocks[ $child_id ]['parentId'] = 'root';
			}
		}

		$style_id           = Ids::make( $styles );
		$styles[ $style_id ] = array(
			'id'        => $style_id,
			'type'      => 'class',
			'name'      => $style_id,
			'isDefault' => false,
			'variant'   => array(
				'md' => 'display:flex;flex-direction:column;width:100%;box-sizing:border-box;',
			),
		);

		$tag = 'div';
		if ( 'header' === $set_as ) {
			$tag = 'header';
		} elseif ( 'footer' === $set_as ) {
			$tag = 'footer';
		}

		$blocks['root'] = array(
			'visibility'       => true,
			'collapse'         => false,
			'name'             => 'section',
			'title'            => $name,
			'properties'       => array(
				'tag'      => $tag,
				'symbolId' => (int) $post_id,
			),
			'styleIds'         => array( $style_id ),
			'className'        => '',
			'source'           => 'kirki',
			'id'               => 'root',
			'parentId'         => '',
			'children'         => array_values( $compiled['body_children'] ),
			'template_mounted' => true,
		);

		return array(
			'root'        => 'root',
			'name'        => $name,
			'category'    => '' !== $set_as ? $set_as : 'other',
			'setAs'       => $set_as,
			'data'        => $blocks,
			'styleBlocks' => $styles,
		);
	}

	/**
	 * Clear setAs on every other symbol that currently holds this role.
	 *
	 * @param string $role header or footer.
	 * @param int    $except Post id to keep.
	 * @return void
	 */
	private static function clear_role( $role, $except ) {
		$posts = get_posts(
			array(
				'post_type'   => 'kirki_symbol',
				'post_status' => array( 'draft', 'publish', 'private' ),
				'numberposts' => -1,
			)
		);
		foreach ( $posts as $post ) {
			if ( (int) $post->ID === (int) $except ) {
				continue;
			}
			$data = self::read_meta( $post->ID );
			if ( isset( $data['setAs'] ) && $data['setAs'] === $role ) {
				$data['setAs'] = '';
				update_post_meta( $post->ID, 'kirki', $data );
			}
		}
	}

	/**
	 * Read symbol meta and refuse anything that is not JSON-shaped.
	 *
	 * @param int $post_id Symbol post id.
	 * @return array
	 */
	private static function read_meta( $post_id ) {
		$data = get_post_meta( $post_id, 'kirki', true );
		if ( ! is_array( $data ) ) {
			if ( is_object( $data ) ) {
				throw new \InvalidArgumentException( 'Symbol ' . (int) $post_id . ' is stored as a PHP object and was not loaded.' );
			}
			return array();
		}
		return Json_Data::only_array( $data, 'Symbol ' . (int) $post_id );
	}

	/**
	 * @param int $symbol_id Symbol id.
	 * @return \WP_Post
	 */
	private static function symbol( $symbol_id ) {
		$post = get_post( (int) $symbol_id );
		if ( ! $post || 'kirki_symbol' !== $post->post_type ) {
			throw new \InvalidArgumentException( 'Symbol ' . (int) $symbol_id . ' was not found.' );
		}
		return $post;
	}

	/**
	 * @param string $name Name.
	 * @return string
	 */
	private static function name( $name ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'A symbol name is required.' );
		}
		return $name;
	}

	/**
	 * @param string $role Role.
	 * @return string header, footer, or empty for a component.
	 */
	private static function role( $role ) {
		$role = strtolower( trim( (string) $role ) );
		if ( in_array( $role, array( 'header', 'footer' ), true ) ) {
			return $role;
		}
		if ( in_array( $role, array( 'component', 'other', '' ), true ) ) {
			return '';
		}
		throw new \InvalidArgumentException( 'Symbol role must be header, footer, or component.' );
	}
}
