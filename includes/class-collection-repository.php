<?php
/**
 * Kirki Content Manager collections (visual builder, not Kirki eCommerce).
 *
 * Routes verified in free Kirki 6.4.2 routes/api.php:
 * GET|POST /wp-json/kirki/v1/content-manager/collections
 * GET|POST /wp-json/kirki/v1/content-manager/collections/items
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowlisted proxy to Kirki Content Manager REST routes.
 */
class Collection_Repository {

	const REST_NAMESPACE = 'kirki/v1';

	/**
	 * @param string $method HTTP method.
	 * @param string $path Path under /kirki/v1.
	 * @return bool
	 */
	public static function is_allowed_route( $method, $path ) {
		$method = strtoupper( (string) $method );
		$path   = '/' . ltrim( (string) $path, '/' );
		$path   = rtrim( $path, '/' );
		$rules  = array(
			array( 'GET', '#^/content-manager/collections(?:/[\d]+)?$#' ),
			array( 'POST', '#^/content-manager/collections$#' ),
			array( 'DELETE', '#^/content-manager/collections/[\d]+$#' ),
			array( 'POST', '#^/content-manager/collections/[\d]+/duplicate$#' ),
			array( 'GET', '#^/content-manager/collections/items(?:/[\d]+)?$#' ),
			array( 'POST', '#^/content-manager/collections/items$#' ),
		);
		foreach ( $rules as $row ) {
			if ( $method === $row[0] && 1 === preg_match( $row[1], $path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $method HTTP method.
	 * @param string $path Path under /kirki/v1.
	 * @param array  $params Params.
	 * @return array
	 */
	public static function request( $method, $path, array $params = array() ) {
		if ( ! Compatibility::supported() ) {
			throw new \RuntimeException( Compatibility::unsupported_reason() );
		}
		$method = strtoupper( (string) $method );
		$path   = '/' . ltrim( (string) $path, '/' );
		$path   = rtrim( $path, '/' );
		if ( ! self::is_allowed_route( $method, $path ) ) {
			throw new \InvalidArgumentException( 'That Content Manager route is not available through Kirki MCP.' );
		}
		if ( ! class_exists( '\WP_REST_Request' ) || ! function_exists( 'rest_do_request' ) ) {
			throw new \RuntimeException( 'WordPress REST is not available.' );
		}
		$request = new \WP_REST_Request( $method, '/' . self::REST_NAMESPACE . $path );
		if ( 'GET' === $method || 'DELETE' === $method ) {
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
		} else {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $params ) );
		}
		$response = rest_do_request( $request );
		if ( $response instanceof \WP_Error ) {
			throw new \RuntimeException( $response->get_error_message() );
		}
		$data   = $response->get_data();
		$status = (int) $response->get_status();
		if ( $status >= 400 ) {
			$message = 'Kirki Content Manager returned HTTP ' . $status . '.';
			if ( is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) ) {
				$message = $data['message'];
			}
			throw new \RuntimeException( $message );
		}
		return is_array( $data ) ? $data : array( 'data' => $data );
	}
}
