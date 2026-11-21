<?php
/**
 * Kirki eCommerce admin API, reached only when that plugin is active.
 *
 * Kirki eCommerce 1.0.2 (wordpress.org/plugins/kirki-ecommerce, Themeum, GPL)
 * is a standalone store plugin. It does not require the Kirki visual builder.
 * Merchant routes live under /wp-json/kirki/ecommerce/v1/ and, in the plugin
 * source, are gated by AuthMiddleware + AdminMiddleware (manage_options).
 *
 * This class never talks to payment webhooks or gateway-install endpoints.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allowlisted proxy to the Kirki eCommerce REST API.
 */
class Ecommerce_Repository {

	const REST_NAMESPACE = 'kirki/ecommerce/v1';

	/**
	 * Whether the Kirki eCommerce plugin is loaded.
	 *
	 * @return bool
	 */
	public static function installed() {
		return defined( 'KIRKI_ECOMMERCE_VERSION' )
			|| defined( 'KIRKI_ECOMMERCE_PLUGIN_FILE' )
			|| class_exists( '\Kirki\Ecommerce\App\KirkiEcommerce' );
	}

	/**
	 * Public status for get-site-context. Never includes secrets.
	 *
	 * @return array
	 */
	public static function status() {
		return array(
			'installed'              => self::installed(),
			'version'                => defined( 'KIRKI_ECOMMERCE_VERSION' ) ? (string) KIRKI_ECOMMERCE_VERSION : '',
			'plugin_slug'            => 'kirki-ecommerce',
			'rest_namespace'         => self::REST_NAMESPACE,
			'requires_kirki_builder' => false,
			'listing'                => 'https://wordpress.org/plugins/kirki-ecommerce/',
		);
	}

	/**
	 * Whether a method + path is safe for an agent to call.
	 *
	 * @param string $method HTTP method.
	 * @param string $path Path beginning with / under the eCommerce namespace.
	 * @return bool
	 */
	public static function is_allowed_route( $method, $path ) {
		$method = strtoupper( (string) $method );
		$path   = self::normalize_path( $path );
		foreach ( self::allowlist() as $row ) {
			if ( $method === $row[0] && 1 === preg_match( $row[1], $path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Allowed admin routes, verified against kirki-ecommerce 1.0.2 routes/api.php.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function allowlist() {
		$id    = '[\d]+';
		$codes = '[A-Za-z0-9_-]+';
		return array(
			array( 'GET', '#^/products(?:/' . $id . ')?$#' ),
			array( 'POST', '#^/products$#' ),
			array( 'PUT', '#^/products/' . $id . '$#' ),
			array( 'DELETE', '#^/products/' . $id . '$#' ),
			array( 'POST', '#^/products/' . $id . '/duplicate$#' ),
			array( 'GET', '#^/product-variants$#' ),
			array( 'GET', '#^/variants(?:/' . $id . ')?$#' ),
			array( 'PUT', '#^/variants/' . $id . '$#' ),
			array( 'PUT', '#^/variants/bulk$#' ),
			array( 'GET', '#^/categories(?:/' . $id . ')?$#' ),
			array( 'POST', '#^/categories$#' ),
			array( 'PUT', '#^/categories/' . $id . '$#' ),
			array( 'DELETE', '#^/categories/' . $id . '$#' ),
			array( 'GET', '#^/brands(?:/' . $id . ')?$#' ),
			array( 'POST', '#^/brands$#' ),
			array( 'PUT', '#^/brands/' . $id . '$#' ),
			array( 'DELETE', '#^/brands/' . $id . '$#' ),
			array( 'GET', '#^/tags(?:/' . $id . ')?$#' ),
			array( 'POST', '#^/tags$#' ),
			array( 'PUT', '#^/tags/' . $id . '$#' ),
			array( 'DELETE', '#^/tags/' . $id . '$#' ),
			array( 'GET', '#^/collections(?:/' . $id . ')?$#' ),
			array( 'POST', '#^/collections$#' ),
			array( 'PUT', '#^/collections/' . $id . '$#' ),
			array( 'DELETE', '#^/collections/' . $id . '$#' ),
			array( 'GET', '#^/attributes(?:/' . $id . ')?$#' ),
			array( 'POST', '#^/attributes$#' ),
			array( 'PUT', '#^/attributes/' . $id . '$#' ),
			array( 'DELETE', '#^/attributes/' . $id . '$#' ),
			array( 'GET', '#^/attributes/' . $id . '/values(?:/' . $id . ')?$#' ),
			array( 'POST', '#^/attributes/' . $id . '/values$#' ),
			array( 'PUT', '#^/attributes/' . $id . '/values/' . $id . '$#' ),
			array( 'GET', '#^/orders(?:/' . $id . ')?$#' ),
			array( 'GET', '#^/orders/' . $id . '/edit$#' ),
			array( 'GET', '#^/orders/' . $id . '/activities$#' ),
			array( 'POST', '#^/orders$#' ),
			array( 'PUT', '#^/orders/' . $id . '$#' ),
			array( 'PATCH', '#^/orders/' . $id . '(?:/action)?$#' ),
			array( 'GET', '#^/coupons(?:/' . $id . ')?$#' ),
			array( 'GET', '#^/coupons/generate-new-code$#' ),
			array( 'GET', '#^/coupons/validate$#' ),
			array( 'POST', '#^/coupons$#' ),
			array( 'PUT', '#^/coupons/' . $id . '$#' ),
			array( 'PATCH', '#^/coupons/' . $id . '/action$#' ),
			array( 'GET', '#^/customers(?:/' . $id . ')?$#' ),
			array( 'POST', '#^/customers$#' ),
			array( 'PUT', '#^/customers/' . $id . '$#' ),
			array( 'GET', '#^/currencies(?:/list)?$#' ),
			array( 'GET', '#^/currencies/' . $id . '$#' ),
			array( 'GET', '#^/tax-profiles(?:/' . $id . ')?$#' ),
			array( 'POST', '#^/tax-profiles$#' ),
			array( 'PUT', '#^/tax-profiles/' . $id . '$#' ),
			array( 'GET', '#^/shipping-profiles(?:/' . $id . ')?$#' ),
			array( 'POST', '#^/shipping-profiles$#' ),
			array( 'PUT', '#^/shipping-profiles/' . $id . '$#' ),
			array( 'GET', '#^/shipping-methods$#' ),
			array( 'GET', '#^/shipping-boxes(?:/' . $id . ')?$#' ),
			array( 'GET', '#^/payment-methods$#' ),
			array( 'GET', '#^/online-payments$#' ),
			array( 'GET', '#^/offline-payments(?:/' . $id . ')?$#' ),
			array( 'GET', '#^/settings/' . $codes . '$#' ),
			array( 'PUT', '#^/settings$#' ),
			array( 'GET', '#^/dashboard/overview$#' ),
			array( 'GET', '#^/dashboard/attention$#' ),
			array( 'GET', '#^/analytics/metrics/' . $codes . '$#' ),
			array( 'GET', '#^/setup-checklist$#' ),
			array( 'GET', '#^/app-config$#' ),
			array( 'GET', '#^/pages$#' ),
			array( 'GET', '#^/countries(?:/' . $codes . ')?$#' ),
			array( 'GET', '#^/onboarding/defaults$#' ),
			array( 'GET', '#^/analytics/regions$#' ),
			array( 'GET', '#^/orders/' . $id . '/shipping-carriers$#' ),
			array( 'POST', '#^/calculate/order$#' ),
			array( 'POST', '#^/shipping-boxes$#' ),
			array( 'PUT', '#^/shipping-boxes/' . $id . '$#' ),
			array( 'DELETE', '#^/shipping-boxes/' . $id . '$#' ),
			array( 'DELETE', '#^/tax-profiles/' . $id . '$#' ),
			array( 'DELETE', '#^/shipping-profiles/' . $id . '$#' ),
			array( 'DELETE', '#^/coupons/' . $id . '$#' ),
			array( 'DELETE', '#^/orders/' . $id . '$#' ),
			array( 'GET', '#^/migration/sources$#' ),
			array( 'POST', '#^/migration/scan$#' ),
			array( 'GET', '#^/migration/runs/latest$#' ),
			array( 'POST', '#^/migration/runs$#' ),
			array( 'GET', '#^/migration/runs/' . $id . '$#' ),
			array( 'POST', '#^/migration/runs/' . $id . '/chunk$#' ),
		);
	}

	/**
	 * Call an allowlisted Kirki eCommerce admin route as the current user.
	 *
	 * @param string $method HTTP method.
	 * @param string $path Path under the namespace, e.g. /products.
	 * @param array  $params Query or JSON body.
	 * @return array
	 */
	public static function request( $method, $path, array $params = array() ) {
		if ( ! self::installed() ) {
			throw new \RuntimeException( 'Kirki eCommerce is not installed or not active. Install the free kirki-ecommerce plugin from WordPress.org, then retry. The builder plugin is not required.' );
		}
		$method = strtoupper( (string) $method );
		$path   = self::normalize_path( $path );
		if ( ! self::is_allowed_route( $method, $path ) ) {
			throw new \InvalidArgumentException( 'That Kirki eCommerce route is not available through Kirki MCP. Catalog, orders, coupons, customers, shipping, tax, settings, and analytics reads are allowed. Payment webhooks, gateway installs, password changes, and webhook endpoints are not.' );
		}
		if ( ! class_exists( '\WP_REST_Request' ) || ! function_exists( 'rest_do_request' ) ) {
			throw new \RuntimeException( 'WordPress REST is not available.' );
		}
		$request = new \WP_REST_Request( $method, '/' . self::REST_NAMESPACE . $path );
		if ( in_array( $method, array( 'GET', 'DELETE' ), true ) ) {
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
			$message = 'Kirki eCommerce returned HTTP ' . $status . '.';
			if ( is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) ) {
				$message = $data['message'];
			}
			throw new \RuntimeException( $message );
		}
		return is_array( $data ) ? $data : array( 'data' => $data );
	}

	/**
	 * @param string $path Raw path.
	 * @return string
	 */
	public static function normalize_path( $path ) {
		$path = '/' . ltrim( (string) $path, '/' );
		$path = preg_replace( '#/+#', '/', $path );
		if ( 0 === strpos( $path, '/kirki/ecommerce/v1/' ) ) {
			$path = substr( $path, strlen( '/kirki/ecommerce/v1' ) );
		}
		$cut = strpos( $path, '?' );
		if ( false !== $cut ) {
			$path = substr( $path, 0, $cut );
		}
		return rtrim( $path, '/' ) === '' ? '/' : rtrim( $path, '/' );
	}
}
