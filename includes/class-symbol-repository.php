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
	 * Desktop CSS written on every symbol root this plugin builds.
	 */
	const ROOT_STYLE = 'display:flex;flex-direction:column;width:100%;box-sizing:border-box;';

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
			$data = Plugin::read_raw_post_meta( $post->ID, 'kirki' );
			if ( ! is_array( $data ) ) {
				$data = array();
			}
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
		try {
			$built = self::commit_shape( $built, $post_id );
		} catch ( \Throwable $error ) {
			wp_delete_post( $post_id, true );
			throw $error;
		}
		Json_Data::update_meta( $post_id, 'kirki', $built );
		Json_Data::update_meta( $post_id, 'kirki_mcp_symbol', '1' );
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
		$data             = self::commit_shape( $data, $post->ID );

		Json_Data::update_meta( $post->ID, 'kirki', $data );
		Json_Data::update_meta( $post->ID, 'kirki_editor_mode', 'kirki' );
		Json_Data::update_meta( $post->ID, 'kirki_mcp_symbol', '1' );
		wp_update_post(
			array(
				'ID'         => $post->ID,
				'post_title' => $data['name'],
			)
		);
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
		Json_Data::update_meta( $post_id, 'kirki', $data );
	}

	/**
	 * HTML id for a symbol root. Includes the symbol post id so a header and footer cannot share one.
	 *
	 * @param string $name Symbol name.
	 * @param int    $post_id Symbol post id.
	 * @return string
	 */
	public static function root_html_id( $name, $post_id ) {
		$slug = Spec_Compiler::anchor_slug( $name );
		if ( '' === $slug ) {
			$slug = 'symbol';
		}
		$post_id = (int) $post_id;
		if ( $post_id > 0 ) {
			return $slug . '-' . $post_id;
		}
		return $slug;
	}

	/**
	 * Ids this symbol will print: the root title plus any explicit anchor.
	 *
	 * @param array $data Symbol meta.
	 * @return string[]
	 */
	public static function rendered_ids( array $data ) {
		$ids  = array();
		$root = isset( $data['root'] ) ? (string) $data['root'] : 'root';
		if ( isset( $data['data'][ $root ]['title'] ) ) {
			$ids[] = Spec_Compiler::anchor_slug( $data['data'][ $root ]['title'] );
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			foreach ( $data['data'] as $element ) {
				if ( ! is_array( $element ) ) {
					continue;
				}
				if ( ! empty( $element['properties']['explicitAnchor'] ) ) {
					$ids[] = Spec_Compiler::anchor_slug( $element['properties']['explicitAnchor'] );
				}
				if ( ! empty( $element['properties']['customAttributes']['id'] ) ) {
					$ids[] = Spec_Compiler::anchor_slug( $element['properties']['customAttributes']['id'] );
				}
			}
		}
		$ids = array_filter( $ids );
		return array_values( array_unique( $ids ) );
	}

	/**
	 * First id present in both lists.
	 *
	 * @param string[] $mine Ids for the symbol being saved.
	 * @param string[] $theirs Ids already used by other symbols.
	 * @return string
	 */
	public static function first_id_collision( array $mine, array $theirs ) {
		$used = array_fill_keys( $theirs, true );
		foreach ( $mine as $id ) {
			if ( isset( $used[ $id ] ) ) {
				return $id;
			}
		}
		return '';
	}

	/**
	 * Whether this symbol was written by the plugin, or the caller asked for it.
	 *
	 * Kirki's own editor symbols are left alone unless an update names them.
	 *
	 * @param array $data Symbol meta.
	 * @param int   $post_id Symbol post id.
	 * @param bool  $explicit The caller named this symbol.
	 * @param bool  $marker_meta The kirki_mcp_symbol meta is set.
	 * @return bool
	 */
	public static function should_migrate_symbol( array $data, $post_id, $explicit = false, $marker_meta = false ) {
		unset( $post_id );
		if ( $explicit || $marker_meta || ! empty( $data['kirkiMcp'] ) ) {
			return true;
		}
		return self::has_plugin_root_style( $data );
	}

	/**
	 * The root style this plugin writes. A matching title from the 0.2.6 stamp is not enough:
	 * that pass also renamed symbols built in Kirki's editor.
	 *
	 * @param array $data Symbol meta.
	 * @return bool
	 */
	public static function has_plugin_root_style( array $data ) {
		$root   = isset( $data['root'] ) ? (string) $data['root'] : 'root';
		$block  = isset( $data['data'][ $root ] ) && is_array( $data['data'][ $root ] ) ? $data['data'][ $root ] : array();
		$ids    = isset( $block['styleIds'] ) && is_array( $block['styleIds'] ) ? $block['styleIds'] : array();
		$styles = isset( $data['styleBlocks'] ) && is_array( $data['styleBlocks'] ) ? $data['styleBlocks'] : array();
		foreach ( $ids as $style_id ) {
			$css = isset( $styles[ $style_id ]['variant']['md'] ) ? (string) $styles[ $style_id ]['variant']['md'] : '';
			if ( self::ROOT_STYLE === trim( $css ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Give one plugin-built symbol a unique root element id.
	 *
	 * A stored PHP object is not loaded. The caller catches that and continues.
	 *
	 * @param int   $post_id Symbol post id.
	 * @param mixed $data Raw meta.
	 * @param bool  $explicit Rewrite even when the plugin did not create it.
	 * @return array
	 */
	public static function migrate_one( $post_id, $data, $explicit = false ) {
		if ( is_object( $data ) ) {
			throw new \InvalidArgumentException( 'Symbol ' . (int) $post_id . ' is stored as a PHP object and was skipped.' );
		}
		if ( ! is_array( $data ) ) {
			return array();
		}
		if ( ! self::should_migrate_symbol( $data, $post_id, $explicit ) ) {
			return $data;
		}
		return self::rekey_root( $data, $post_id );
	}

	/**
	 * Rewrite plugin-built symbols once.
	 *
	 * The done flag is autoloaded and is set even when a symbol fails, so a
	 * bad record cannot take down the next request. Kirki editor symbols are
	 * skipped unless $explicit_ids names them.
	 *
	 * @param int[] $explicit_ids Symbol ids to rewrite even if the plugin did not create them.
	 * @return void
	 */
	public static function migrate_plugin_symbols( array $explicit_ids = array() ) {
		try {
			if ( function_exists( 'get_posts' ) ) {
				$posts = get_posts(
					array(
						'post_type'   => 'kirki_symbol',
						'post_status' => array( 'draft', 'publish', 'private' ),
						'numberposts' => -1,
						'fields'      => 'ids',
					)
				);
				foreach ( $posts as $post_id ) {
					$post_id = (int) $post_id;
					try {
						$raw    = Plugin::read_raw_post_meta( $post_id, 'kirki' );
						$latest = Plugin::read_raw_post_meta( $post_id, 'kirki' );
						$basis  = ( is_array( $latest ) && $latest !== $raw ) ? $latest : $raw;
						$marker = '1' === (string) get_post_meta( $post_id, 'kirki_mcp_symbol', true );
						$next   = self::migrate_one( $post_id, $basis, in_array( $post_id, $explicit_ids, true ) || $marker );
						if ( is_array( $basis ) && $next !== $basis ) {
							Plugin::write_raw_post_meta( $post_id, 'kirki', $next );
							Plugin::write_raw_post_meta( $post_id, 'kirki_mcp_symbol', '1' );
						}
					} catch ( \Throwable $error ) {
						error_log( 'Kirki MCP symbol migration skipped ' . $post_id . ': ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
				}
			}
		} catch ( \Throwable $error ) {
			error_log( 'Kirki MCP symbol migration failed: ' . $error->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( 'kirki_mcp_symbol_root_ids', '1' );
		}
	}

	/**
	 * Move the symbol root element to root-{post id}.
	 *
	 * Kirki caches section HTML ids by element id for the whole request.
	 * Every symbol used to use the element id "root", so the footer reused
	 * the header's cached id.
	 *
	 * @param array $data Symbol meta.
	 * @param int   $post_id Symbol post id.
	 * @return array
	 */
	public static function rekey_root( array $data, $post_id ) {
		$data = self::stamp_root_id( $data, $post_id );
		$old  = isset( $data['root'] ) ? (string) $data['root'] : 'root';
		$new  = self::root_element_id( $post_id );
		if ( ! isset( $data['data'][ $old ] ) || ! is_array( $data['data'][ $old ] ) ) {
			$data['root']     = $new;
			$data['kirkiMcp'] = 1;
			return $data;
		}
		$block       = $data['data'][ $old ];
		$block['id'] = $new;
		if ( $old !== $new ) {
			unset( $data['data'][ $old ] );
		}
		$data['data'][ $new ] = $block;
		foreach ( $data['data'] as $id => $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			if ( isset( $child['parentId'] ) && $child['parentId'] === $old ) {
				$data['data'][ $id ]['parentId'] = $new;
			}
			$href = isset( $child['properties']['attributes']['href'] ) ? $child['properties']['attributes']['href'] : '';
			$type = isset( $child['properties']['type'] ) ? $child['properties']['type'] : '';
			if ( 'section' === $type && ( $href === $old || 'root' === $href ) && 'root' !== $new ) {
				$data['data'][ $id ]['properties']['attributes']['href'] = $new;
			}
		}
		$data['root']     = $new;
		$data['kirkiMcp'] = 1;
		return $data;
	}

	/**
	 * Element id of a symbol root. Unique per symbol so Kirki's id cache cannot collide.
	 *
	 * @param int $post_id Symbol post id.
	 * @return string
	 */
	public static function root_element_id( $post_id ) {
		$post_id = (int) $post_id;
		return $post_id > 0 ? 'root-' . $post_id : 'root';
	}

	/**
	 * HTML ids Kirki would print for these symbols in one request.
	 *
	 * The cache key is the element id. Two roots that share the id "root"
	 * therefore share one HTML id, even when their titles differ.
	 *
	 * @param array $symbols Symbol meta records, in render order.
	 * @return string[]
	 */
	public static function page_section_ids( array $symbols ) {
		$tracker = array();
		$ids     = array();
		foreach ( $symbols as $symbol ) {
			if ( ! is_array( $symbol ) ) {
				continue;
			}
			$root_key   = isset( $symbol['root'] ) ? (string) $symbol['root'] : 'root';
			$block      = isset( $symbol['data'][ $root_key ] ) && is_array( $symbol['data'][ $root_key ] ) ? $symbol['data'][ $root_key ] : array();
			$element_id = isset( $block['id'] ) ? (string) $block['id'] : $root_key;
			if ( isset( $tracker[ $element_id ] ) ) {
				$ids[] = $tracker[ $element_id ];
				continue;
			}
			$title = isset( $block['title'] ) ? (string) $block['title'] : $element_id;
			$base  = strtolower( (string) preg_replace( '/\s+/', '-', $title ) );
			$href  = $base;
			$n     = 1;
			while ( in_array( $href, $tracker, true ) ) {
				$href = $base . '-' . $n;
				++$n;
			}
			$tracker[ $element_id ] = $href;
			$ids[]                  = $href;
		}
		return $ids;
	}

	/**
	 * Set the symbol root title to a per-symbol HTML id.
	 *
	 * @param array $data Symbol meta.
	 * @param int   $post_id Symbol post id.
	 * @return array
	 */
	public static function stamp_root_id( array $data, $post_id ) {
		$root = isset( $data['root'] ) ? (string) $data['root'] : 'root';
		$name = isset( $data['name'] ) ? (string) $data['name'] : '';
		if ( isset( $data['data'][ $root ] ) && is_array( $data['data'][ $root ] ) ) {
			$data['data'][ $root ]['title'] = self::root_html_id( $name, $post_id );
			if ( isset( $data['data'][ $root ]['properties']['explicitAnchor'] ) ) {
				unset( $data['data'][ $root ]['properties']['explicitAnchor'] );
			}
		}
		return $data;
	}

	/**
	 * Rekey the root, then reject the symbol before anything is written.
	 *
	 * @param array $data Symbol meta.
	 * @param int   $post_id Symbol post id.
	 * @return array
	 */
	private static function commit_shape( array $data, $post_id ) {
		$data     = self::rekey_root( $data, $post_id );
		$root     = isset( $data['root'] ) ? (string) $data['root'] : self::root_element_id( $post_id );
		$document = Document::from_symbol(
			isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array(),
			isset( $data['styleBlocks'] ) && is_array( $data['styleBlocks'] ) ? $data['styleBlocks'] : array(),
			$root
		);
		$document->validate( Global_Repository::class_id_list(), 'symbol', $root );
		$data['data'] = $document->blocks;
		self::assert_ids_unique( $post_id, $data );
		return $data;
	}

	/**
	 * Reject an anchor or root id another symbol already prints.
	 *
	 * @param int   $post_id This symbol.
	 * @param array $data Symbol meta.
	 * @return void
	 */
	private static function assert_ids_unique( $post_id, array $data ) {
		$mine = self::rendered_ids( $data );
		if ( ! $mine || ! function_exists( 'get_posts' ) ) {
			return;
		}
		$posts  = get_posts(
			array(
				'post_type'   => 'kirki_symbol',
				'post_status' => array( 'draft', 'publish', 'private' ),
				'numberposts' => -1,
				'exclude'     => array( (int) $post_id ),
			)
		);
		$theirs = array();
		foreach ( $posts as $post ) {
			$other = self::read_meta( $post->ID );
			if ( $other ) {
				$theirs = array_merge( $theirs, self::rendered_ids( $other ) );
			}
		}
		$theirs = array_merge( $theirs, self::page_anchor_ids() );
		$clash  = self::first_id_collision( $mine, $theirs );
		if ( '' !== $clash ) {
			throw new \InvalidArgumentException( 'The id "' . $clash . '" is already used by another symbol or page. Header and footer ids must be unique on the page.' );
		}
	}

	/**
	 * Section and anchor ids already printed by pages.
	 *
	 * @return string[]
	 */
	private static function page_anchor_ids() {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => array( 'publish', 'draft', 'private', 'pending' ),
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$ids   = array();
		foreach ( $posts as $page_id ) {
			$meta = get_post_meta( (int) $page_id, 'kirki', true );
			if ( ! is_array( $meta ) ) {
				continue;
			}
			$blocks = isset( $meta['blocks'] ) && is_array( $meta['blocks'] ) ? $meta['blocks'] : $meta;
			$ids    = array_merge( $ids, self::anchor_ids_in_blocks( $blocks ) );
		}
		return $ids;
	}

	/**
	 * Anchors and section ids inside one element map.
	 *
	 * @param array $blocks Elements.
	 * @return string[]
	 */
	public static function anchor_ids_in_blocks( array $blocks ) {
		$ids = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( ! empty( $block['properties']['explicitAnchor'] ) ) {
				$ids[] = Spec_Compiler::anchor_slug( $block['properties']['explicitAnchor'] );
			}
			if ( ! empty( $block['properties']['customAttributes']['id'] ) ) {
				$ids[] = Spec_Compiler::anchor_slug( $block['properties']['customAttributes']['id'] );
			}
			if ( isset( $block['name'] ) && 'section' === $block['name'] && ! empty( $block['title'] ) ) {
				$ids[] = Spec_Compiler::anchor_slug( $block['title'] );
			}
		}
		$ids = array_filter( $ids );
		return array_values( array_unique( $ids ) );
	}

	/**
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
		Json_Data::update_meta( $post->ID, 'kirki', $data );
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
				'md' => self::ROOT_STYLE,
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
				Json_Data::update_meta( $post->ID, 'kirki', $data );
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
		self::assert_symbol_post( $post );
		return $post;
	}

	/**
	 * Reject a symbol_id that is not a kirki_symbol post.
	 *
	 * @param mixed $post Post object, or null.
	 * @return void
	 */
	public static function assert_symbol_post( $post ) {
		$type = is_object( $post ) && isset( $post->post_type ) ? (string) $post->post_type : '';
		if ( 'kirki_symbol' !== $type ) {
			throw new \InvalidArgumentException( 'symbol_id must be a Kirki symbol.' );
		}
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
