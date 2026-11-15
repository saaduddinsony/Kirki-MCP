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
		$reason = Compatibility::unsupported_reason();
		if ( '' !== $reason ) {
			throw new \RuntimeException( $reason );
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

		self::ensure_variable_mode( $page_id );
		return $page_id;
	}

	/**
	 * Load the graph the editor would show.
	 *
	 * @param int  $page_id Page id.
	 * @param bool $convert Allow turning a non-Kirki page into a Kirki page.
	 * @return array{post:\WP_Post,document:Document,stage:int|null}
	 */
	public static function load( $page_id, $convert = false ) {
		self::assert_ready();
		$post = self::page( $page_id );
		$mode = get_post_meta( $post->ID, 'kirki_editor_mode', true );
		if ( 'kirki' !== $mode && ! $convert ) {
			throw new \InvalidArgumentException( 'Page ' . (int) $post->ID . ' is not a Kirki page. Pass convert true to switch it. The existing post content stays in the database, and Kirki will render the page instead.' );
		}
		$stage  = self::unpublished_stage( $post->ID );
		$meta   = self::read_meta( $post->ID, 'kirki', $stage );
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
	 * @param bool     $allow_auto_stage Open a stage for an already published page.
	 * @return void
	 */
	public static function save( $page_id, Document $document, $stage, $allow_auto_stage = true ) {
		self::assert_ready();
		$page_id = (int) $page_id;
		$post    = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			throw new \InvalidArgumentException( 'Page ' . $page_id . ' was not found.' );
		}

		$document->sanitize_stored_titles();
		$document->assert_unique_anchors();
		$document->validate( Global_Repository::class_id_list() );
		$stage_arg = $stage ? (int) $stage : null;
		if ( null === $stage_arg && $allow_auto_stage && 'publish' === $post->post_status ) {
			$stage_arg = self::open_unpublished_stage( $page_id );
		}

		$blocks = array( 'blocks' => $document->blocks );
		$styles = $document->styles;

		if ( class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			$stage_arg = $stage_arg ? (int) $stage_arg : false;
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

		self::ensure_variable_mode( $page_id );
		self::ensure_canvas_template( $page_id );

		if ( $stage_arg && class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			\Kirki\App\Supports\Facades\Page::set_last_edited_datetime_of_stage_version( $page_id );
		} elseif ( class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			// A draft lives in post meta. Create the published version record once, after the meta is written.
			\Kirki\App\Supports\Facades\Page::get_all_staged_versions( $page_id, true );
		}
		self::use_current_global_classes( $page_id );
	}

	/**
	 * Pages created by Kirki's first-version helper are marked as legacy and
	 * then ignore the class manager. Clear that flag so global classes load.
	 *
	 * @param int $page_id Page id.
	 * @return void
	 */
	private static function use_current_global_classes( $page_id ) {
		if ( ! class_exists( '\Kirki\App\Models\PageStage' ) ) {
			return;
		}
		\Kirki\App\Models\PageStage::query()
			->where( 'post_id', (int) $page_id )
			->update( array( 'no_legacy_global_style' => 1 ) );
	}

	/**
	 * Open an unpublished stage for a page that is already public.
	 *
	 * Kirki's get_stage_version_for_save copies the published meta into the new
	 * version, so the stage starts as a complete snapshot. The sentinel value
	 * never matches stored meta, which forces a new version even when only
	 * styles changed.
	 *
	 * @param int $page_id Page id.
	 * @return int
	 */
	private static function open_unpublished_stage( $page_id ) {
		$existing = self::unpublished_stage( $page_id );
		if ( $existing ) {
			return $existing;
		}
		$version = \Kirki\App\Supports\Facades\Page::get_stage_version_for_save(
			(int) $page_id,
			'kirki',
			array( 'kirki_mcp_stage' => Ids::make() )
		);
		if ( ! $version ) {
			throw new \RuntimeException( 'Kirki did not open an unpublished stage for page ' . (int) $page_id . '.' );
		}
		return (int) $version;
	}

	/**
	 * Update title or slug.
	 *
	 * @param int    $page_id Page id.
	 * @param string $title Optional title.
	 * @param string $slug Optional slug.
	 * @return void
	 */
	/**
	 * SEO title and meta description, stored the way Kirki's head tags read them.
	 *
	 * @param int    $page_id Page id.
	 * @param string $meta_description Meta description.
	 * @param string $seo_title Optional SEO title.
	 * @return array
	 */
	public static function update_seo( $page_id, $meta_description, $seo_title = '' ) {
		$post = self::page( $page_id );
		$seo  = get_post_meta( $post->ID, 'kirki_page_seo_settings', true );
		if ( ! is_array( $seo ) ) {
			$seo = array();
		}
		if ( ! isset( $seo['seoSettings'] ) || ! is_array( $seo['seoSettings'] ) ) {
			$seo['seoSettings'] = array();
		}
		$description = sanitize_text_field( Css::plain_meta( $meta_description ) );
		if ( '' === $description ) {
			throw new \InvalidArgumentException( 'A meta description is required.' );
		}
		$seo['seoSettings']['seoMetaDesc'] = array( 'value' => $description );
		if ( '' !== $seo_title ) {
			$seo['seoSettings']['seoTitleTag'] = array( 'value' => sanitize_text_field( Css::plain_meta( $seo_title ) ) );
		}
		if ( ! isset( $seo['openGraph'] ) || ! is_array( $seo['openGraph'] ) ) {
			$seo['openGraph'] = array();
		}
		update_post_meta( $post->ID, 'kirki_page_seo_settings', $seo );
		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_excerpt' => $description,
			)
		);
		return array(
			'page_id'          => (int) $post->ID,
			'meta_description' => $description,
			'seo_title'        => isset( $seo['seoSettings']['seoTitleTag']['value'] ) ? $seo['seoSettings']['seoTitleTag']['value'] : '',
		);
	}

	/**
	 * Page head or body custom code, in the shape Kirki prints.
	 *
	 * @param int    $page_id Page id.
	 * @param string $head Head HTML, usually a style block.
	 * @param string $body Markup printed at the start of the body.
	 * @return array
	 */
	public static function update_custom_code( $page_id, $head, $body ) {
		if ( ! Access::can_edit_custom_code() ) {
			throw new \InvalidArgumentException( 'Custom code requires Kirki builder access and the unfiltered_html capability.' );
		}
		$post = self::page( $page_id );
		$head = Css::head_style_blocks( $head );
		$body = self::body_code( $body );
		$code = get_post_meta( $post->ID, 'kirki_page_custom_code', true );
		if ( ! is_array( $code ) ) {
			$code = array();
		}
		if ( '' !== $head ) {
			$code['head'] = array( 'value' => $head );
		}
		if ( '' !== $body ) {
			$code['body'] = array( 'value' => $body );
		}
		update_post_meta( $post->ID, 'kirki_page_custom_code', $code );
		return array(
			'page_id' => (int) $post->ID,
			'head'    => isset( $code['head']['value'] ) ? $code['head']['value'] : '',
			'body'    => isset( $code['body']['value'] ) ? $code['body']['value'] : '',
		);
	}

	/**
	 * Body custom code, filtered the same way as post content.
	 *
	 * @param string $code Raw markup.
	 * @return string
	 */
	private static function body_code( $code ) {
		$code = trim( (string) $code );
		if ( strlen( $code ) > 20000 ) {
			throw new \InvalidArgumentException( 'Custom code is limited to 20,000 characters.' );
		}
		if ( '' === $code ) {
			return '';
		}
		return wp_kses_post( $code );
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
		if ( $set_front && true !== current_user_can( 'manage_options' ) ) {
			throw new \InvalidArgumentException( 'Setting the front page requires a user who can manage options. The page was not published.' );
		}
		$post = self::page( $page_id );
		if ( class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			\Kirki\App\Supports\Facades\Page::stages()->with_version_lock(
				(int) $post->ID,
				function () use ( $post ) {
					$stage = self::unpublished_stage( $post->ID );
					if ( $stage ) {
						self::copy_stage_onto_published_meta( $post->ID, $stage );
					}
					// Kirki's own publish fills only empty published keys, then snapshots
					// post meta back over the stage. Copying the stage first makes that
					// snapshot the edited content instead of the previous published page.
					\Kirki\App\Supports\Facades\Page::publish_stage_version( $post->ID, false );
				}
			);
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
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $post->ID );
		}
	}

	/**
	 * Overwrite published post meta with one stage's content keys.
	 *
	 * @param int $page_id Page id.
	 * @param int $stage Stage version.
	 * @return void
	 */
	private static function copy_stage_onto_published_meta( $page_id, $stage ) {
		$keys   = \Kirki\App\Constants\PageMetaKeys::get_stage_content_keys();
		$values = \Kirki\App\Supports\Facades\Page::stages()->get_values( (int) $page_id, (int) $stage, $keys );
		if ( ! is_array( $values ) ) {
			return;
		}
		foreach ( $values as $key => $value ) {
			\Kirki\App\Supports\Facades\Page::write_page_meta( (int) $page_id, (string) $key, $value, false );
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
		self::assert_ready();
		$post = self::page( $page_id );
		$page = \Kirki\App\Models\Page::find( $post->ID );
		if ( ! $page ) {
			throw new \InvalidArgumentException( 'Page ' . (int) $post->ID . ' was not found.' );
		}
		$copy    = ( new \Kirki\App\Services\PageService() )->duplicate_page( $page );
		$copy_id = is_object( $copy ) ? (int) $copy->ID : 0;
		if ( $copy_id <= 0 ) {
			throw new \RuntimeException( 'Kirki did not duplicate the page.' );
		}
		wp_update_post(
			array(
				'ID'         => $copy_id,
				'post_title' => get_post_field( 'post_title', $copy_id ),
			)
		);
		foreach ( array( 'kirki_page_seo_settings', 'kirki_page_custom_code', 'kirki_disabled_page_symbols', 'kirki_variable_mode' ) as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( '' !== $value && array() !== $value && null !== $value ) {
				update_post_meta( $copy_id, $key, $value );
			}
		}
		self::clean_titles_in_place( $copy_id );
		return $copy_id;
	}

	/**
	 * Slug section titles already stored on a page, in published meta and in an open stage.
	 *
	 * @param int $page_id Page id.
	 * @return void
	 */
	public static function clean_titles_in_place( $page_id ) {
		self::clean_meta_titles( $page_id );
		$stage = self::unpublished_stage( $page_id );
		if ( $stage && class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			$blocks = \Kirki\App\Supports\Facades\Page::read_page_meta( (int) $page_id, 'kirki', (int) $stage, array() );
			$styles = \Kirki\App\Supports\Facades\Page::read_page_meta( (int) $page_id, 'kirki_global_style_block_random', (int) $stage, array() );
			$clean  = self::cleaned_block_meta( $blocks, $styles );
			if ( null !== $clean ) {
				\Kirki\App\Supports\Facades\Page::write_page_meta( (int) $page_id, 'kirki', $clean, (int) $stage );
			}
		}
	}

	/**
	 * Pages and symbols whose stored titles are not safe HTML ids.
	 *
	 * @return array
	 */
	public static function audit_unsafe_titles() {
		self::assert_ready();
		$pages = array();
		$query = new \WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one audit pass, at most 100 pages.
					array(
						'key'   => 'kirki_editor_mode',
						'value' => 'kirki',
					),
				),
			)
		);
		foreach ( $query->posts as $page_id ) {
			if ( ! current_user_can( 'edit_post', $page_id ) ) {
				continue;
			}
			try {
				$loaded = self::load( $page_id );
			} catch ( \Throwable $error ) {
				$pages[] = array(
					'id'       => (int) $page_id,
					'title'    => get_the_title( $page_id ),
					'error'    => $error->getMessage(),
					'elements' => array(),
				);
				continue;
			}
			$unsafe = $loaded['document']->unsafe_titles();
			if ( ! $unsafe ) {
				continue;
			}
			$pages[] = array(
				'id'       => (int) $page_id,
				'title'    => get_the_title( $page_id ),
				'elements' => $unsafe,
			);
		}
		return $pages;
	}

	/**
	 * @param int $page_id Page id.
	 * @return void
	 */
	private static function clean_meta_titles( $page_id ) {
		$blocks = get_post_meta( $page_id, 'kirki', true );
		$styles = get_post_meta( $page_id, 'kirki_global_style_block_random', true );
		$clean  = self::cleaned_block_meta( $blocks, $styles );
		if ( null !== $clean ) {
			update_post_meta( $page_id, 'kirki', $clean );
		}
	}

	/**
	 * @param mixed $blocks Stored kirki meta.
	 * @param mixed $styles Stored styles.
	 * @return array|null Rewritten meta, or null when there is nothing to clean.
	 */
	private static function cleaned_block_meta( $blocks, $styles ) {
		if ( ! is_array( $blocks ) ) {
			return null;
		}
		try {
			$document = Document::from_stored( $blocks, is_array( $styles ) ? $styles : array() );
		} catch ( \Throwable $error ) {
			unset( $error );
			return null;
		}
		if ( 0 === $document->sanitize_stored_titles() ) {
			return null;
		}
		if ( isset( $blocks['blocks'] ) && is_array( $blocks['blocks'] ) ) {
			$blocks['blocks'] = $document->blocks;
			return $blocks;
		}
		return $document->blocks;
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
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded page list, at most 50 rows.
				array(
					'key'   => 'kirki_editor_mode',
					'value' => 'kirki',
				),
			);
		}
		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
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
	 * @param int  $page_id Page id.
	 * @param bool $include_raw Include the element map.
	 * @return array
	 */
	public static function present( $page_id, $include_raw = false ) {
		$loaded             = self::load( $page_id );
		$post               = $loaded['post'];
		$data               = self::summary( $post );
		$data['stage']      = $loaded['stage'];
		$data['writing_to'] = $loaded['stage'] ? 'unpublished_stage' : 'published_meta';
		$data['tree']       = $loaded['document']->tree();
		$data['styles']     = $loaded['document']->style_summary();
		$data['keys']       = $loaded['document']->keys;
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
			'id'          => (int) $post->ID,
			'title'       => $post->post_title,
			'slug'        => $post->post_name,
			'status'      => $post->post_status,
			'kirki'       => $kirki,
			'modified'    => $post->post_modified_gmt,
			'permalink'   => get_permalink( $post ),
			'preview_url' => get_preview_post_link( $post ),
			'editor_url'  => self::editor_url( $post->ID ),
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
		$map  = Global_Repository::name_map();
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
	/**
	 * Set the variable mode only when the page does not already have one.
	 *
	 * @param int $page_id Page id.
	 * @return void
	 */
	private static function ensure_variable_mode( $page_id ) {
		$current = get_post_meta( $page_id, 'kirki_variable_mode', true );
		if ( is_string( $current ) && '' !== $current ) {
			return;
		}
		update_post_meta( $page_id, 'kirki_variable_mode', 'default' );
	}

	/**
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
