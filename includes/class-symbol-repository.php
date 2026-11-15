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
	 * Slug a stored symbol name and its section titles.
	 *
	 * @param array  $data Symbol meta.
	 * @param string $post_title Post title used when the meta name is empty.
	 * @return array
	 */
	public static function prepare_record( array $data, $post_title = '' ) {
		$name = '';
		if ( ! empty( $data['name'] ) ) {
			$name = Spec_Compiler::safe_label( $data['name'] );
		}
		if ( '' === $name && '' !== (string) $post_title ) {
			$name = Spec_Compiler::safe_label( $post_title );
		}
		if ( '' === $name ) {
			$name = 'Symbol';
		}
		$data['name'] = $name;
		$root         = isset( $data['root'] ) ? (string) $data['root'] : 'root';
		if ( isset( $data['data'] ) && is_array( $data['data'] ) && isset( $data['data'][ $root ] ) ) {
			try {
				$document = Document::from_symbol(
					$data['data'],
					isset( $data['styleBlocks'] ) && is_array( $data['styleBlocks'] ) ? $data['styleBlocks'] : array(),
					$root
				);
				$document->sanitize_stored_titles();
				if ( isset( $document->blocks[ $root ] ) ) {
					$document->blocks[ $root ]['title'] = $name;
				}
				$data['data'] = $document->blocks;
			} catch ( \Throwable $error ) {
				unset( $error );
				$data['data'][ $root ]['title'] = $name;
			}
		}
		return $data;
	}

	/**
	 * Symbols whose names or section titles are not safe HTML ids.
	 *
	 * @return array
	 */
	public static function audit_unsafe_titles() {
		Page_Repository::assert_ready();
		$posts = get_posts(
			array(
				'post_type'   => 'kirki_symbol',
				'post_status' => array( 'draft', 'publish', 'private' ),
				'numberposts' => 100,
			)
		);
		$items = array();
		foreach ( $posts as $post ) {
			$data  = self::read_meta( $post->ID );
			$name  = ! empty( $data['name'] ) ? (string) $data['name'] : (string) $post->post_title;
			$safe  = Spec_Compiler::safe_label( $name );
			$root  = isset( $data['root'] ) ? (string) $data['root'] : 'root';
			$extra = array();
			if ( isset( $data['data'] ) && is_array( $data['data'] ) && isset( $data['data'][ $root ] ) ) {
				try {
					$document = Document::from_symbol(
						$data['data'],
						isset( $data['styleBlocks'] ) && is_array( $data['styleBlocks'] ) ? $data['styleBlocks'] : array(),
						$root
					);
					$extra    = $document->unsafe_titles();
				} catch ( \Throwable $error ) {
					unset( $error );
				}
			}
			if ( $name === $safe && ! $extra ) {
				continue;
			}
			$items[] = array(
				'id'        => (int) $post->ID,
				'name'      => $name,
				'safe_name' => '' === $safe ? 'Symbol' : $safe,
				'elements'  => $extra,
			);
		}
		return $items;
	}

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
		$post     = self::symbol( $symbol_id );
		$data     = self::read_meta( $post->ID );
		$root     = isset( $data['root'] ) ? (string) $data['root'] : 'root';
		$document = Document::from_symbol(
			isset( $data['data'] ) ? $data['data'] : array(),
			isset( $data['styleBlocks'] ) ? $data['styleBlocks'] : array(),
			$root
		);
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
		$name              = self::name( $name );
		$set_as            = self::role( $role );
		$built             = self::validated_symbol( 0, $name, $set_as, $sections );
		$built['setAs']    = '';
		$built['category'] = 'other';
		$built             = self::prepare_record( $built, $name );
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
		if ( '' !== $set_as ) {
			self::assign_role( $set_as, $post_id, true );
		}

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

		$data = self::prepare_record( $data, $post->post_title );
		if ( null !== $name && '' !== $name ) {
			$data['name'] = self::name( $name );
		}
		wp_update_post(
			array(
				'ID'         => $post->ID,
				'post_title' => $data['name'],
			)
		);

		$next_role = isset( $data['setAs'] ) ? $data['setAs'] : '';
		if ( null !== $role ) {
			$next_role = self::role( $role );
		}
		if ( null !== $sections ) {
			$built               = self::validated_symbol( $post->ID, $data['name'], $next_role, $sections );
			$data['data']        = $built['data'];
			$data['styleBlocks'] = $built['styleBlocks'];
			$data['root']        = 'root';
			$data                = self::prepare_record( $data, $data['name'] );
		}
		$data['setAs']    = '';
		$data['category'] = 'other';

		update_post_meta( $post->ID, 'kirki', $data );
		update_post_meta( $post->ID, 'kirki_editor_mode', 'kirki' );
		if ( '' !== $next_role ) {
			self::assign_role( $next_role, $post->ID, false );
		}
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
	 * Build a symbol and reject it before any header or footer role changes.
	 *
	 * @param int    $post_id Post id, or 0 before Kirki inserts the post.
	 * @param string $name Name.
	 * @param string $set_as Role.
	 * @param array  $sections Sections.
	 * @return array
	 */
	private static function validated_symbol( $post_id, $name, $set_as, array $sections ) {
		$built    = Json_Data::only_array( self::build( $post_id, $name, $set_as, $sections ), 'Symbol' );
		$document = Document::from_symbol( $built['data'], $built['styleBlocks'], 'root' );
		$document->validate( Global_Repository::class_id_list(), 'symbol', 'root' );
		return $built;
	}

	/**
	 * Give one symbol a role, then clear that role on the others.
	 *
	 * The new symbol is already saved. If clearing the previous holder fails,
	 * this symbol's role is removed so the site is not left with two headers.
	 *
	 * @param string $role header or footer.
	 * @param int    $post_id Symbol that should keep the role.
	 * @param bool   $delete_on_failure Delete $post_id when the role cannot be moved.
	 * @return void
	 */
	private static function assign_role( $role, $post_id, $delete_on_failure ) {
		$previous = self::role_holders( $role, $post_id );
		try {
			self::clear_role( $role, $post_id );
			self::write_role( $post_id, $role );
		} catch ( \Throwable $error ) {
			foreach ( $previous as $holder_id ) {
				self::write_role( $holder_id, $role );
			}
			self::write_role( $post_id, '' );
			if ( $delete_on_failure ) {
				wp_delete_post( $post_id, true );
			}
			throw new \RuntimeException( 'The previous ' . $role . ' could not be unassigned, so this symbol was left unassigned.' );
		}
	}

	/**
	 * Symbols that currently hold a role, other than $except.
	 *
	 * @param string $role header or footer.
	 * @param int    $except Post id to ignore.
	 * @return int[]
	 */
	private static function role_holders( $role, $except ) {
		$posts = get_posts(
			array(
				'post_type'   => 'kirki_symbol',
				'post_status' => array( 'draft', 'publish', 'private' ),
				'numberposts' => -1,
			)
		);
		$ids   = array();
		foreach ( $posts as $post ) {
			if ( (int) $post->ID === (int) $except ) {
				continue;
			}
			$data = self::read_meta( $post->ID );
			if ( isset( $data['setAs'] ) && $data['setAs'] === $role ) {
				$ids[] = (int) $post->ID;
			}
		}
		return $ids;
	}

	/**
	 * Set or clear one symbol's role.
	 *
	 * @param int    $post_id Symbol id.
	 * @param string $role header, footer, or empty.
	 * @return void
	 */
	private static function write_role( $post_id, $role ) {
		$data = self::read_meta( $post_id );
		if ( ! $data ) {
			return;
		}
		$data['setAs']    = $role;
		$data['category'] = '' !== $role ? $role : 'other';
		update_post_meta( $post_id, 'kirki', $data );
	}

	/**
	 * Update one symbol's stored class snapshot when that class changes.
	 *
	 * @param array  $data Symbol meta.
	 * @param string $class_id Style-block id.
	 * @param array  $block Updated class block.
	 * @return array
	 */
	public static function refresh_class_copy( array $data, $class_id, array $block ) {
		$uses = false;
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			foreach ( $data['data'] as $element ) {
				if ( ! is_array( $element ) ) {
					continue;
				}
				$ids = isset( $element['properties']['classesIds'] ) ? $element['properties']['classesIds'] : array();
				if ( is_array( $ids ) && in_array( $class_id, $ids, true ) ) {
					$uses = true;
					break;
				}
			}
		}
		$has_copy = isset( $data['styleBlocks'][ $class_id ] );
		if ( ! $uses && ! $has_copy ) {
			return $data;
		}
		if ( ! isset( $data['styleBlocks'] ) || ! is_array( $data['styleBlocks'] ) ) {
			$data['styleBlocks'] = array();
		}
		$data['styleBlocks'][ $class_id ] = $block;
		return $data;
	}

	/**
	 * Copy referenced global classes into the symbol so Kirki can render them.
	 *
	 * Symbol rendering only sees style blocks stored on the symbol.
	 *
	 * @param array      $blocks Symbol elements.
	 * @param array      $styles Local style blocks.
	 * @param array|null $class_styles Global class style blocks. Loaded from Kirki when omitted.
	 * @return array
	 */
	public static function include_global_classes( array $blocks, array $styles, $class_styles = null ) {
		if ( null === $class_styles ) {
			if ( ! class_exists( '\Kirki\App\Supports\Facades\GlobalData' ) ) {
				return $styles;
			}
			$class_styles = \Kirki\App\Supports\Facades\GlobalData::get_style_blocks();
		}
		if ( ! is_array( $class_styles ) ) {
			return $styles;
		}
		foreach ( $blocks as $block ) {
			if ( empty( $block['properties']['classesIds'] ) || ! is_array( $block['properties']['classesIds'] ) ) {
				continue;
			}
			foreach ( $block['properties']['classesIds'] as $class_id ) {
				$class_id = (string) $class_id;
				if ( isset( $class_styles[ $class_id ] ) && is_array( $class_styles[ $class_id ] ) ) {
					$styles[ $class_id ] = $class_styles[ $class_id ];
				}
			}
		}
		return $styles;
	}

	/**
	 * Strip default local CSS on a symbol and make sure its classes are stored with it.
	 *
	 * @param int  $symbol_id Symbol id.
	 * @param bool $clear_local Drop every local declaration on classed elements.
	 * @return int
	 */
	public static function reapply_classes( $symbol_id, $clear_local = false ) {
		Page_Repository::assert_ready();
		$post                = self::symbol( $symbol_id );
		$data                = self::read_meta( $post->ID );
		$root                = isset( $data['root'] ) ? (string) $data['root'] : 'root';
		$document            = Document::from_symbol(
			isset( $data['data'] ) ? $data['data'] : array(),
			isset( $data['styleBlocks'] ) ? $data['styleBlocks'] : array(),
			$root
		);
		$count               = $document->release_class_defaults( $clear_local );
		$data['data']        = $document->blocks;
		$data['styleBlocks'] = self::include_global_classes( $document->blocks, $document->styles );
		update_post_meta( $post->ID, 'kirki', $data );
		return $count;
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
		$styles   = self::include_global_classes( $blocks, $compiled['styles'] );

		foreach ( $compiled['body_children'] as $child_id ) {
			if ( isset( $blocks[ $child_id ] ) ) {
				$blocks[ $child_id ]['parentId'] = 'root';
			}
		}

		$style_id            = Ids::make( $styles );
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
		$name = Spec_Compiler::safe_label( $name );
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'A symbol name is required, using only letters, numbers, spaces, dots, underscores, and hyphens.' );
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
