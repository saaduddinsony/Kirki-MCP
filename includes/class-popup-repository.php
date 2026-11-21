<?php
/**
 * Kirki popup posts (post type kirki_popup).
 *
 * The free 6.4.2 renderer and PUT /kirki/v1/popups/{id} exist in the free
 * plugin. Kirki's FAQ still lists the popup builder as a Pro capability, so
 * create/update tells the agent when a Pro license is not detected.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Popup CRUD using the same document graph as pages.
 */
class Popup_Repository {

	const POST_TYPE = 'kirki_popup';

	/**
	 * @param int $page    Page number.
	 * @param int $per_page Per page.
	 * @return array
	 */
	public static function list_popups( $page = 1, $per_page = 20 ) {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'       => (int) $post->ID,
				'title'    => $post->post_title,
				'status'   => $post->post_status,
				'slug'     => $post->post_name,
				'modified' => $post->post_modified_gmt,
			);
		}
		return array(
			'popups'    => $items,
			'page'      => $page,
			'total'     => (int) $query->found_posts,
			'kirki_pro' => Compatibility::is_pro_user(),
			'note'      => 'Popup posts use the same Kirki element graph as pages. Kirki advertises the popup builder as a Pro feature; this ability writes the free plugin\'s kirki_popup data format.',
		);
	}

	/**
	 * @param string $title Title.
	 * @param array  $sections Compiler sections.
	 * @param string $status draft or publish.
	 * @return array
	 */
	public static function create( $title, array $sections, $status = 'draft' ) {
		$title  = sanitize_text_field( $title );
		$status = 'publish' === $status ? 'publish' : 'draft';
		if ( '' === $title ) {
			throw new \InvalidArgumentException( 'A popup title is required.' );
		}
		$document = Document::from_sections( $sections );
		$document->validate( Global_Repository::class_id_list() );
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $title,
				'post_status' => $status,
				'post_name'   => sanitize_title( $title ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( $id->get_error_message() );
		}
		self::write_document( (int) $id, $document );
		return self::present( (int) $id );
	}

	/**
	 * @param int        $popup_id Popup id.
	 * @param array|null $sections Replacement sections, or null to leave.
	 * @param string     $title Optional new title.
	 * @return array
	 */
	public static function update( $popup_id, $sections = null, $title = '' ) {
		$post   = self::get_post( $popup_id );
		$update = array( 'ID' => (int) $post->ID );
		if ( '' !== $title ) {
			$update['post_title'] = sanitize_text_field( $title );
		}
		if ( count( $update ) > 1 ) {
			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( $result->get_error_message() );
			}
		}
		if ( is_array( $sections ) ) {
			$document = Document::from_sections( $sections );
			$document->validate( Global_Repository::class_id_list() );
			self::write_document( (int) $post->ID, $document );
		}
		return self::present( (int) $post->ID );
	}

	/**
	 * @param int $popup_id Popup id.
	 * @return array
	 */
	public static function present( $popup_id ) {
		$post     = self::get_post( $popup_id );
		$meta     = get_post_meta( $post->ID, 'kirki', true );
		$styles   = get_post_meta( $post->ID, 'kirki_global_style_block_random', true );
		$document = Document::from_stored( is_array( $meta ) ? $meta : array(), is_array( $styles ) ? $styles : array() );
		return array(
			'id'        => (int) $post->ID,
			'title'     => $post->post_title,
			'status'    => $post->post_status,
			'slug'      => $post->post_name,
			'tree'      => $document->tree(),
			'kirki_pro' => Compatibility::is_pro_user(),
		);
	}

	/**
	 * @param int $popup_id Popup id.
	 * @return void
	 */
	public static function trash( $popup_id ) {
		$post = self::get_post( $popup_id );
		wp_trash_post( (int) $post->ID );
	}

	/**
	 * @param int $popup_id Popup id.
	 * @return \WP_Post
	 */
	private static function get_post( $popup_id ) {
		$post = get_post( (int) $popup_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			throw new \InvalidArgumentException( 'Popup not found.' );
		}
		return $post;
	}

	/**
	 * @param int      $popup_id Popup id.
	 * @param Document $document Compiled page document.
	 * @return void
	 */
	private static function write_document( $popup_id, Document $document ) {
		$document->sanitize_stored_titles();
		$document->assert_unique_anchors();
		$blocks = array( 'blocks' => $document->blocks );
		$styles = $document->styles;
		if ( class_exists( '\Kirki\App\Supports\Facades\Page' ) ) {
			\Kirki\App\Supports\Facades\Page::save_blocks( $popup_id, $blocks );
			\Kirki\App\Supports\Facades\Page::save_style_blocks( $popup_id, $styles );
			if ( method_exists( '\Kirki\App\Supports\Facades\Page', 'save_editor_mode' ) ) {
				\Kirki\App\Supports\Facades\Page::save_editor_mode( $popup_id );
			}
			return;
		}
		Json_Data::update_meta( $popup_id, 'kirki', $blocks );
		Json_Data::update_meta( $popup_id, 'kirki_global_style_block_random', $styles );
		Json_Data::update_meta( $popup_id, 'kirki_editor_mode', 'kirki' );
	}
}
