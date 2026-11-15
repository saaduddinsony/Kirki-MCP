<?php
/**
 * Reads and writes Kirki page meta without modifying the Kirki plugin.
 *
 * Published content lives in post meta. When the editor has an unpublished
 * stage, reads and writes use that stage so an agent does not clobber a
 * published page the editor has not saved yet. publish-page promotes the stage.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page storage.
 */
class Page_Repository {

	/**
	 * Confirm Kirki 6 is the active builder.
	 *
	 * @return void
	 */
	public static function assert_ready() {
		if ( ! defined( 'KIRKI_VERSION' ) ) {
			throw new \RuntimeException( 'The Kirki plugin is not active.' );
		}
		if ( version_compare( KIRKI_VERSION, '6.0.0', '<' ) || version_compare( KIRKI_VERSION, '7.0.0', '>=' ) ) {
			throw new \RuntimeException( 'Kirki MCP supports Kirki 6.x. This site is running Kirki ' . KIRKI_VERSION . '.' );
		}
	}

	/**
	 * Create a Kirki canvas page.
	 *
	 * @param string $title Page title.
	 * @param string $slug Optional slug.
	 * @param string $status draft or publish.
	 * @return int
	 */
	public static function create( $title, $slug = '', $status = 'draft' ) {
		self::assert_ready();
		$title = sanitize_text_field( $title );
		if ( '' === $title ) {
			throw new \InvalidArgumentException( 'A page title is required.' );
		}
		if ( strlen( $title ) > 200 ) {
			throw new \InvalidArgumentException( 'Page titles are limited to 200 characters.' );
		}

		$payload = \Kirki\App\DTO\Page\PagePayloadDTO::from_array(
			array(
				'post_title' => $title,
				'post_type'  => 'kirki_page',
			)
		);
		$page    = ( new \Kirki\App\Services\PageService() )->save( $payload );
		$page_id = is_object( $page ) ? (int) $page->ID : 0;
		if ( $page_id <= 0 ) {
			throw new \RuntimeException( 'Kirki did not return a page id.' );
		}

		$update = array( 'ID' => $page_id );
		if ( '' !== $slug ) {
			$update['post_name'] = sanitize_title( $slug );
		}
		if ( 'publish' === $status ) {
			$update['post_status'] = 'publish';
		}
		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( $result->get_error_message() );
			}
		}

		update_post_meta( $page_id, 'kirki_variable_mode', 'default' );
		return $page_id;
	}

	/**
	 * Load the graph the editor would show.
	 *
	 * @param int $page_id Page id.
	 * @return array{post:\WP_Post,document:Document,stage:int|null}
	 */
	public static function load( $page_id ) {
		self::assert_ready();
		$post = self::page( $page_id );
		$stage = self::unpublished_stage( $post->ID );
		$meta  = self::read_meta( $post->ID, 'kirki', $stage );
		$styles = self::read_meta( $post->ID, 'kirki_global_style_block_random', $stage );
		return array(
			'post'     => $post,
			'document' => Document::from_stored( $meta, $styles ),
			'stage'    => $stage,
		);
	}

	/**
	 * Persist a document to the same place load() read from.
	 *
	 * This is the only page-content writer. It does not call Kirki's template
	 * saver. That saver builds an editor payload with staging left off, so a
	 * template import would overwrite published meta even when the editor has
	 * an unpublished stage. When a stage exists, this method writes there.
	 * publish-page promotes it.
	 *
	 * @param int      $page_id Page id.
	 * @param Document $document Document.
	 * @param int|null $stage Unpublished stage, or null for post meta.
	 * @return void
	 */
	public static function save( $page_id, Document $document, $stage ) {
		self::assert_ready();
		$page_id = (int) $page_id;
		$blocks  = array( 'blocks' => $document->blocks );
		$styles  = $document->styles;

		if ( class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			$stage_arg = $stage ? (int) $stage : false;
			\Kirki\App\Supports\Facades\Page::save_blocks( $page_id, $blocks, $stage_arg );
			\Kirki\App\Supports\Facades\Page::save_style_blocks( $page_id, $styles, $stage_arg );
			\Kirki\App\Supports\Facades\Page::save_used_style_block_ids( $page_id, $document->local_style_ids(), $stage_arg );
			\Kirki\App\Supports\Facades\Page::save_used_global_style_block_ids( $page_id, $document->class_ids(), $stage_arg );
			\Kirki\App\Supports\Facades\Page::save_editor_mode( $page_id );
		} else {
			update_post_meta( $page_id, 'kirki', $blocks );
			update_post_meta( $page_id, 'kirki_global_style_block_random', $styles );
			update_post_meta( $page_id, 'kirki_used_style_block_ids_random', $document->local_style_ids() );
			update_post_meta( $page_id, 'kirki_used_style_block_ids', $document->class_ids() );
			update_post_meta( $page_id, 'kirki_editor_mode', 'kirki' );
		}

		update_post_meta( $page_id, 'kirki_variable_mode', 'default' );
		self::ensure_canvas_template( $page_id );

		if ( ! $stage && class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			// Creates the published stage and snapshots the meta just written, when none exists yet.
			\Kirki\App\Supports\Facades\Page::get_all_staged_versions( $page_id, true );
		}
	}

	/**
	 * Update title or slug.
	 *
	 * @param int    $page_id Page id.
	 * @param string $title Optional title.
	 * @param string $slug Optional slug.
	 * @return void
	 */
	public static function update_settings( $page_id, $title, $slug ) {
		$post   = self::page( $page_id );
		$update = array( 'ID' => $post->ID );
		if ( '' !== $title ) {
			$title = sanitize_text_field( $title );
			if ( '' === $title ) {
				throw new \InvalidArgumentException( 'The title is empty.' );
			}
			$update['post_title'] = $title;
		}
		if ( '' !== $slug ) {
			$update['post_name'] = sanitize_title( $slug );
		}
		if ( count( $update ) === 1 ) {
			return;
		}
		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( $result->get_error_message() );
		}
	}

	/**
	 * Publish the WordPress page and any unpublished Kirki stage.
	 *
	 * @param int  $page_id Page id.
	 * @param bool $set_front Also make it the site front page.
	 * @return void
	 */
	public static function publish( $page_id, $set_front = false ) {
		self::assert_ready();
		$post  = self::page( $page_id );
		$stage = self::unpublished_stage( $post->ID );
		if ( $stage && class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			\Kirki\App\Supports\Facades\Page::publish_stage_version( $post->ID, false );
		}
		$result = wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( $result->get_error_message() );
		}
		if ( $set_front ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				throw new \InvalidArgumentException( 'Setting the front page requires a user who can manage options.' );
			}
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $post->ID );
		}
	}

	/**
	 * Move a page to the trash.
	 *
	 * @param int  $page_id Page id.
	 * @param bool $confirm Explicit confirmation.
	 * @return void
	 */
	public static function trash( $page_id, $confirm ) {
		if ( true !== $confirm ) {
			throw new \InvalidArgumentException( 'Trashing a page requires confirm set to true.' );
		}
		$post = self::page( $page_id );
		$gone = wp_trash_post( $post->ID );
		if ( ! $gone ) {
			throw new \RuntimeException( 'WordPress did not trash the page.' );
		}
	}

	/**
	 * Duplicate a Kirki page as a new draft.
	 *
	 * @param int $page_id Source page.
	 * @return int New page id.
	 */
	public static function duplicate( $page_id ) {
		$loaded = self::load( $page_id );
		$copy   = self::create( $loaded['post']->post_title . ' copy', '', 'draft' );
		self::save( $copy, $loaded['document'], null );
		return $copy;
	}

	/**
	 * List pages.
	 *
	 * @param int  $page Page number.
	 * @param int  $per_page Page size.
	 * @param bool $kirki_only Only pages edited with Kirki.
	 * @return array
	 */
	public static function list_pages( $page, $per_page, $kirki_only ) {
		$args = array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		if ( $kirki_only ) {
			$args['meta_key']   = 'kirki_editor_mode';
			$args['meta_value'] = 'kirki';
		}
		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = self::summary( $post );
		}
		return array(
			'pages'    => $items,
			'total'    => (int) $query->found_posts,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Public summary of a stored page.
	 *
	 * @param int         $page_id Page id.
	 * @param bool        $include_raw Include the element map.
	 * @return array
	 */
	public static function present( $page_id, $include_raw = false ) {
		$loaded = self::load( $page_id );
		$post   = $loaded['post'];
		$data   = self::summary( $post );
		$data['stage']   = $loaded['stage'];
		$data['writing_to'] = $loaded['stage'] ? 'unpublished_stage' : 'published_meta';
		$data['tree']    = $loaded['document']->tree();
		$data['styles']  = $loaded['document']->style_summary();
		$data['keys']    = $loaded['document']->keys;
		if ( $include_raw ) {
			$data['blocks'] = $loaded['document']->blocks;
		}
		return $data;
	}

	/**
	 * Short page record.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	public static function summary( $post ) {
		$kirki = 'kirki' === get_post_meta( $post->ID, 'kirki_editor_mode', true );
		return array(
			'id'         => (int) $post->ID,
			'title'      => $post->post_title,
			'slug'       => $post->post_name,
			'status'     => $post->post_status,
			'kirki'      => $kirki,
			'modified'   => $post->post_modified_gmt,
			'permalink'  => get_permalink( $post ),
			'preview_url'=> get_preview_post_link( $post ),
			'editor_url' => self::editor_url( $post->ID ),
		);
	}

	/**
	 * Kirki editor URL for a page.
	 *
	 * @param int $page_id Page id.
	 * @return string
	 */
	public static function editor_url( $page_id ) {
		$permalink = get_permalink( $page_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			$permalink = home_url( '/?page_id=' . (int) $page_id );
		}
		return add_query_arg( 'action', 'kirki', $permalink );
	}

	/**
	 * Resolve global class names inside a spec to style-block ids.
	 *
	 * @param array $sections Specs, by reference.
	 * @return void
	 */
	public static function resolve_classes( array &$sections ) {
		$map = Global_Repository::name_map();
		$walk = function ( &$elements ) use ( &$walk, $map ) {
			foreach ( $elements as &$spec ) {
				if ( ! is_array( $spec ) ) {
					continue;
				}
				if ( ! empty( $spec['classes'] ) && is_array( $spec['classes'] ) ) {
					$ids = array();
					foreach ( $spec['classes'] as $name ) {
						$key = strtolower( trim( (string) $name ) );
						if ( ! isset( $map[ $key ] ) ) {
							throw new \InvalidArgumentException( 'Unknown global class "' . $key . '". Create it with upsert-global-class first.' );
						}
						$ids[] = $map[ $key ];
					}
					$spec['class_ids'] = $ids;
				}
				if ( ! empty( $spec['elements'] ) && is_array( $spec['elements'] ) ) {
					$walk( $spec['elements'] );
				}
			}
		};
		$walk( $sections );
	}

	/**
	 * A page post, or an error.
	 *
	 * @param int $page_id Page id.
	 * @return \WP_Post
	 */
	private static function page( $page_id ) {
		$post = get_post( (int) $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			throw new \InvalidArgumentException( 'Page ' . (int) $page_id . ' was not found.' );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			throw new \InvalidArgumentException( 'You cannot edit that page.' );
		}
		return $post;
	}

	/**
	 * Latest unpublished Kirki stage, if the editor has one.
	 *
	 * @param int $page_id Page id.
	 * @return int|null
	 */
	private static function unpublished_stage( $page_id ) {
		if ( ! class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			return null;
		}
		$version = \Kirki\App\Supports\Facades\Page::get_most_recent_unpublished_stage_version( (int) $page_id );
		return $version ? (int) $version : null;
	}

	/**
	 * Read one meta key, from a stage when the editor has unpublished work.
	 *
	 * @param int      $page_id Page id.
	 * @param string   $key Meta key.
	 * @param int|null $stage Stage id.
	 * @return mixed
	 */
	private static function read_meta( $page_id, $key, $stage ) {
		if ( $stage && class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			$stored = \Kirki\App\Supports\Facades\Page::read_page_meta( (int) $page_id, $key, (int) $stage, array() );
		} else {
			$stored = get_post_meta( $page_id, $key, true );
		}
		if ( ! is_array( $stored ) ) {
			if ( is_object( $stored ) ) {
				throw new \InvalidArgumentException( 'Page ' . (int) $page_id . ' contains a PHP object and was not loaded.' );
			}
			return array();
		}
		return Json_Data::only_array( $stored, 'Page ' . (int) $page_id );
	}

	/**
	 * Point the page at Kirki's full-canvas template when it has none.
	 *
	 * @param int $page_id Page id.
	 * @return void
	 */
	private static function ensure_canvas_template( $page_id ) {
		$current = get_post_meta( $page_id, '_wp_page_template', true );
		if ( is_string( $current ) && '' !== $current ) {
			return;
		}
		if ( class_exists( '\Kirki\App\Supports\Canvas' ) ) {
			update_post_meta( $page_id, '_wp_page_template', \Kirki\App\Supports\Canvas::get_full_canvas_template_path() );
		}
	}
}
