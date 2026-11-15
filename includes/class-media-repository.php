<?php
/**
 * Image uploads through the WordPress core media endpoint.
 *
 * Kirki 6.4.2 exposes GET /wp-json/kirki/v1/media and uploads files through
 * admin-ajax.php?action=kirki_post_apis&endpoint=upload-media. That ajax action
 * calls HelperFunctions::verify_nonce( 'wp_rest' ). An application password
 * does not send that nonce, so agents cannot use it. POST /wp-json/wp/v2/media
 * accepts the same application password as the MCP endpoint. This repository
 * performs that core request as the current user.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media uploads.
 */
class Media_Repository {

	const MAX_BYTES = 5242880;

	/**
	 * Store a base64 image with the core media controller.
	 *
	 * @param string $filename File name, including an image extension.
	 * @param string $data_base64 Base64 file contents, without a data: prefix.
	 * @param string $alt Alt text.
	 * @return array{id:int,url:string,alt:string,endpoint:string}
	 */
	public static function upload_base64( $filename, $data_base64, $alt = '' ) {
		if ( true !== current_user_can( 'upload_files' ) ) {
			throw new \InvalidArgumentException( 'Uploading media requires the upload_files capability.' );
		}

		$filename = sanitize_file_name( (string) $filename );
		$checked  = wp_check_filetype( $filename );
		if ( empty( $checked['type'] ) || 0 !== strpos( $checked['type'], 'image/' ) ) {
			throw new \InvalidArgumentException( 'Only image files can be uploaded. Use a png, jpg, gif, or webp file name.' );
		}

		$encoded = preg_replace( '/\s+/', '', (string) $data_base64 );
		if ( strlen( $encoded ) > (int) ceil( self::MAX_BYTES * 4 / 3 ) + 4 ) {
			throw new \InvalidArgumentException( 'Images must be 5 MB or smaller.' );
		}
		$binary = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Image bytes from the agent, then checked with getimagesizefromstring.
		if ( ! is_string( $binary ) || '' === $binary ) {
			throw new \InvalidArgumentException( 'data_base64 is not valid base64.' );
		}
		if ( strlen( $binary ) > self::MAX_BYTES ) {
			throw new \InvalidArgumentException( 'Images must be 5 MB or smaller.' );
		}
		$image_info = function_exists( 'getimagesizefromstring' ) ? getimagesizefromstring( $binary ) : false;
		if ( ! is_array( $image_info ) || empty( $image_info['mime'] ) || $image_info['mime'] !== $checked['type'] ) {
			throw new \InvalidArgumentException( 'The file contents do not match the image extension.' );
		}

		$alt = sanitize_text_field( $alt );

		$request = new \WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', $checked['type'] );
		$request->set_header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
		$request->set_body( $binary );
		if ( '' !== $alt ) {
			$request->set_param( 'alt_text', $alt );
		}

		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			$error = $response->as_error();
			throw new \RuntimeException( $error->get_error_message() );
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) || empty( $data['id'] ) ) {
			throw new \RuntimeException( 'The media endpoint did not return an attachment.' );
		}

		$attachment_id = (int) $data['id'];
		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		$url = '';
		if ( ! empty( $data['source_url'] ) && is_string( $data['source_url'] ) ) {
			$url = $data['source_url'];
		} else {
			$found = wp_get_attachment_url( $attachment_id );
			$url   = is_string( $found ) ? $found : '';
		}

		return array(
			'id'       => $attachment_id,
			'url'      => $url,
			'alt'      => $alt,
			'endpoint' => rest_url( 'wp/v2/media' ),
		);
	}
}
