<?php
/**
 * Kirki eCommerce admin API, reached only when that plugin is active.
 *
 * Kirki eCommerce 1.0.2 (wordpress.org/plugins/kirki-ecommerce, Themeum, GPL)
 * is a standalone store plugin. It does not require the Kirki visual builder.
 * Merchant routes live under /wp-json/kirki/ecommerce/v1/ and, in the plugin
 * source, are gated by AuthMiddleware + AdminMiddleware (manage_options).
 *
 * Incoming payment webhooks and customer password-change stay blocked. Payment and checkout writes need an explicit opt-in.
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
			array( 'DELETE', '#^/customers/' . $id . '$#' ),
			array( 'POST', '#^/currencies$#' ),
			array( 'PUT', '#^/currencies$#' ),
			array( 'DELETE', '#^/currencies/' . $id . '$#' ),
			array( 'GET', '#^/currency-exchange/providers$#' ),
			array( 'POST', '#^/currency-exchange/sync$#' ),
			array( 'POST', '#^/orders/' . $id . '/refunds$#' ),
			array( 'PUT', '#^/orders/' . $id . '/refunds/' . $id . '$#' ),
			array( 'DELETE', '#^/orders/' . $id . '/refunds/' . $id . '$#' ),
			array( 'POST', '#^/orders/' . $id . '/activities$#' ),
			array( 'GET', '#^/settings/email/' . $codes . '/' . $codes . '/' . $codes . '/preview$#' ),
			array( 'POST', '#^/settings/email/' . $codes . '/' . $codes . '/' . $codes . '/preview/test-mail$#' ),
			array( 'POST', '#^/settings/email/' . $codes . '/' . $codes . '/' . $codes . '/restore$#' ),
			array( 'POST', '#^/onboarding/sample-data$#' ),
			array( 'POST', '#^/products/bulk$#' ),
			array( 'POST', '#^/orders/bulk$#' ),
			array( 'POST', '#^/coupons/bulk$#' ),
			array( 'GET', '#^/cart$#' ),
			array( 'POST', '#^/cart/items$#' ),
			array( 'PUT', '#^/cart/items/' . $id . '$#' ),
			array( 'DELETE', '#^/cart/items/' . $id . '$#' ),
			array( 'DELETE', '#^/cart$#' ),
			array( 'PUT', '#^/cart$#' ),
			array( 'POST', '#^/cart/coupon$#' ),
			array( 'DELETE', '#^/cart/coupon$#' ),
			array( 'POST', '#^/checkout$#' ),
			array( 'PUT', '#^/payment-methods/order$#' ),
			array( 'GET', '#^/online-payments/installable$#' ),
			array( 'GET', '#^/online-payments/' . $id . '$#' ),
			array( 'POST', '#^/online-payments/install$#' ),
			array( 'POST', '#^/online-payments/update$#' ),
			array( 'PUT', '#^/online-payments/' . $id . '$#' ),
			array( 'PATCH', '#^/online-payments/' . $id . '$#' ),
			array( 'DELETE', '#^/online-payments/' . $id . '$#' ),
			array( 'POST', '#^/offline-payments$#' ),
			array( 'PUT', '#^/offline-payments/' . $id . '$#' ),
			array( 'PATCH', '#^/offline-payments/' . $id . '$#' ),
			array( 'DELETE', '#^/offline-payments/' . $id . '$#' ),
			array( 'POST', '#^/tags/bulk$#' ),
			array( 'POST', '#^/brands/bulk$#' ),
			array( 'POST', '#^/categories/bulk$#' ),
			array( 'POST', '#^/collections/bulk$#' ),
			array( 'POST', '#^/attributes/bulk$#' ),
			array( 'DELETE', '#^/attributes/' . $id . '/values/' . $id . '$#' ),
			array( 'POST', '#^/attributes/' . $id . '/values/bulk$#' ),
			array( 'POST', '#^/attributes/' . $id . '/values/batch$#' ),
			array( 'POST', '#^/customers/bulk$#' ),
			array( 'POST', '#^/currencies/bulk$#' ),
			array( 'POST', '#^/tax-profiles/bulk$#' ),
			array( 'POST', '#^/shipping-boxes/bulk$#' ),
			array( 'POST', '#^/shipping-profiles/bulk$#' ),
			array( 'POST', '#^/products/' . $id . '/duplicate$#' ),
			array( 'POST', '#^/onboarding$#' ),
			array( 'GET', '#^/setup-checklist$#' ),
			array( 'POST', '#^/setup-checklist/' . $codes . '/complete$#' ),
			array( 'GET', '#^/home/first-sale$#' ),
			array( 'POST', '#^/home/first-sale/' . $codes . '$#' ),
			array( 'GET', '#^/shipping-carriers/last-used$#' ),
			array( 'DELETE', '#^/orders/' . $id . '/activities/' . $id . '$#' ),
			array( 'GET', '#^/pages$#' ),
			array( 'POST', '#^/pages/fix$#' ),
			array( 'GET', '#^/shop/products$#' ),
			array( 'PUT', '#^/profile$#' ),
			array( 'GET', '#^/addresses(?:/' . $id . ')?$#' ),
			array( 'POST', '#^/addresses$#' ),
			array( 'PUT', '#^/addresses/' . $id . '$#' ),
			array( 'DELETE', '#^/addresses/' . $id . '$#' ),
			array( 'PATCH', '#^/addresses/' . $id . '/set-default$#' ),
			array( 'GET', '#^/wishlist$#' ),
			array( 'POST', '#^/wishlist$#' ),
			array( 'DELETE', '#^/wishlist/' . $id . '$#' ),
			array( 'DELETE', '#^/wishlist/empty$#' ),
			array( 'POST', '#^/resend-verification-email$#' ),
		);
	}

	/**
	 * Call an allowlisted Kirki eCommerce admin route as the current user.
	 *
	 * @param string $method HTTP method.
	 * @param string $path Path under the namespace, e.g. /products.
	 * @param array  $params Query or JSON body.
	 * @param array  $options Options, including dry_run.
	 * @return array
	 */
	public static function request( $method, $path, array $params = array(), array $options = array() ) {
		$dry = ! empty( $options['dry_run'] );
		if ( ! $dry && ! self::installed() ) {
			throw new \RuntimeException( 'Kirki eCommerce is not installed or not active. Install the free kirki-ecommerce plugin from WordPress.org, then retry. The builder plugin is not required.' );
		}
		$method = strtoupper( (string) $method );
		$path   = self::normalize_path( $path );
		if ( ! self::is_allowed_route( $method, $path ) ) {
			throw new \InvalidArgumentException( 'That Kirki eCommerce route is not available through Kirki MCP. Password-change and payment webhook endpoints stay blocked. Other catalog, order, cart, checkout, and payment routes are allowlisted; payment and checkout writes need the store payment-writes opt-in.' );
		}
		$sensitive = self::is_sensitive_route( $method, $path );
		$is_write  = ! in_array( $method, array( 'GET', 'HEAD' ), true );
		if ( $sensitive && $is_write && ! Access::can_write_store_payments() && ! $dry ) {
			throw new \InvalidArgumentException( 'Payment, checkout, cart, and gateway-install writes are read-only until an administrator enables kirki_mcp_allow_store_payment_writes (set-store-payment-writes). Use dry_run: true to preview a call.' );
		}
		if ( $is_write && $sensitive ) {
			$params = self::keep_write_only_secrets( $params );
		}
		if ( $dry ) {
			Audit::record(
				'ecommerce-dry-run',
				array(
					'method'     => $method,
					'path'       => $path,
					'sensitive'  => $sensitive,
					'param_keys' => array_keys( $params ),
				)
			);
			return array(
				'dry_run'    => true,
				'method'     => $method,
				'path'       => $path,
				'sensitive'  => $sensitive,
				'would_call' => true,
				'body'       => self::redact( $params ),
			);
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
		if ( $is_write || $sensitive ) {
			Audit::record(
				'ecommerce-request',
				array(
					'method'    => $method,
					'path'      => $path,
					'sensitive' => $sensitive,
					'status'    => $status,
				)
			);
		}
		$data = is_array( $data ) ? $data : array( 'data' => $data );
		return self::redact( $data );
	}

	/**
	 * Payment, checkout, cart, and gateway-install routes.
	 *
	 * @param string $method Method.
	 * @param string $path Path.
	 * @return bool
	 */
	public static function is_sensitive_route( $method, $path ) {
		$method = strtoupper( (string) $method );
		$path   = self::normalize_path( $path );
		$rules  = array(
			'#^/cart#',
			'#^/checkout#',
			'#^/payment-methods#',
			'#^/online-payments#',
			'#^/offline-payments#',
		);
		foreach ( $rules as $rule ) {
			if ( 1 === preg_match( $rule, $path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Strip secret values from a payload or response. Keys stay, values become true write-only markers on the way out.
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 */
	public static function redact( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( $data as $key => $value ) {
			$name = is_string( $key ) ? strtolower( $key ) : '';
			if ( $name && self::is_secret_key( $name ) ) {
				$out[ $key ] = '[redacted]';
				continue;
			}
			$out[ $key ] = is_array( $value ) ? self::redact( $value ) : $value;
		}
		return $out;
	}

	/**
	 * @param string $name Key.
	 * @return bool
	 */
	public static function is_secret_key( $name ) {
		$name = strtolower( (string) $name );
		foreach ( array( 'password', 'secret', 'private_key', 'api_key', 'apikey', 'webhook', 'access_token', 'client_secret', 'clientid', 'client_id', 'publishable_key', 'secret_key', 'license_key' ) as $needle ) {
			if ( false !== strpos( $name, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keep secret fields for a write (they are sent, never echoed back).
	 *
	 * @param array $params Params.
	 * @return array
	 */
	private static function keep_write_only_secrets( array $params ) {
		return $params;
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
