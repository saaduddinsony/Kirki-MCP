<?php
/**
 * Kirki WooCommerce Bridge (kirki-woo/v1) proxy.
 *
 * The 21 public operations are defined by Kirki WooCommerce Bridge 1.0.0
 * (`KirkiWoo\Operations::all()`, docs/API.md). This class re-declares that
 * catalog so abilities can register even when the Bridge is not installed,
 * then proxies calls through rest_do_request as the current user.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridge detection, operation catalog, and REST proxy.
 */
class Woo_Bridge_Repository {

	const REST_NAMESPACE = 'kirki-woo/v1';
	const PLUGIN_SLUG    = 'kirki-woocommerce-bridge';
	const REPO_URL       = 'https://github.com/saaduddinsony/kirki-woocommerce-bridge';

	/**
	 * WooCommerce page contexts a design can be assigned to.
	 *
	 * @return string[]
	 */
	public static function contexts() {
		return array(
			'single_product',
			'shop',
			'product_archive',
			'product_search',
			'cart',
			'cart_empty',
			'checkout',
			'order_received',
			'my_account',
			'my_account_login',
		);
	}

	/**
	 * Element groups from the Bridge catalog.
	 *
	 * @return string[]
	 */
	public static function element_groups() {
		return array( 'product', 'shop', 'cart', 'account', 'general' );
	}

	/**
	 * Whether the Kirki WooCommerce Bridge plugin is loaded.
	 *
	 * @return bool
	 */
	public static function installed() {
		return defined( 'KIRKI_WOO_VERSION' )
			|| defined( 'KIRKI_WOO_API_NAMESPACE' )
			|| class_exists( '\KirkiWoo\Operations' )
			|| class_exists( '\KirkiWoo\Plugin' );
	}

	/**
	 * Public status for get-site-context. Never includes secrets.
	 *
	 * @return array
	 */
	public static function status() {
		$base = array(
			'installed'      => self::installed(),
			'version'        => defined( 'KIRKI_WOO_VERSION' ) ? (string) KIRKI_WOO_VERSION : '',
			'plugin_slug'    => self::PLUGIN_SLUG,
			'rest_namespace' => self::REST_NAMESPACE,
			'repo'           => self::REPO_URL,
			'ready'          => false,
			'operations'     => count( self::operations() ),
		);
		if ( ! self::installed() ) {
			$base['note'] = 'Install Kirki WooCommerce Bridge, WooCommerce, and Kirki 6.4.x, then retry.';
			return $base;
		}
		try {
			$live = self::request_raw( 'GET', '/status', array() );
			if ( is_array( $live ) ) {
				$live['installed']      = true;
				$live['plugin_slug']    = self::PLUGIN_SLUG;
				$live['rest_namespace'] = self::REST_NAMESPACE;
				$live['repo']           = self::REPO_URL;
				return $live;
			}
		} catch ( \Exception $e ) {
			$base['note'] = $e->getMessage();
		}
		$base['ready'] = false;
		return $base;
	}

	/**
	 * MCP ability names (`woo-get-status`, …).
	 *
	 * @return string[]
	 */
	public static function ability_names() {
		$names = array();
		foreach ( self::operations() as $op ) {
			$names[] = 'woo-' . $op['ability'];
		}
		return $names;
	}

	/**
	 * Look up one operation by ability slug (get-status, list-elements, …).
	 *
	 * @param string $ability Ability slug without namespace.
	 * @return array
	 */
	public static function operation( $ability ) {
		foreach ( self::operations() as $op ) {
			if ( $op['ability'] === $ability ) {
				return $op;
			}
		}
		throw new \InvalidArgumentException( 'Unknown Kirki WooCommerce Bridge operation.' );
	}

	/**
	 * The 21 official Bridge operations. When the Bridge is loaded, the live
	 * Operations::all() table is preferred (so a filter can add fields) but
	 * unknown operations are ignored.
	 *
	 * @return array[]
	 */
	public static function operations() {
		$ops = self::catalog();
		if ( class_exists( '\KirkiWoo\Operations' ) && method_exists( '\KirkiWoo\Operations', 'all' ) ) {
			$live = \KirkiWoo\Operations::all();
			if ( is_array( $live ) ) {
				$by_name = array();
				foreach ( $ops as $op ) {
					$by_name[ $op['ability'] ] = $op;
				}
				foreach ( $live as $row ) {
					if ( empty( $row['ability'] ) || ! isset( $by_name[ $row['ability'] ] ) ) {
						continue;
					}
					$by_name[ $row['ability'] ] = self::merge_operation( $by_name[ $row['ability'] ], $row );
				}
				$ops = array_values( $by_name );
			}
		}
		return $ops;
	}

	/**
	 * JSON Schema for an operation, plus confirm on destructive writes.
	 *
	 * @param array $op Operation.
	 * @return array
	 */
	public static function input_schema( array $op ) {
		$properties = isset( $op['input'] ) && is_array( $op['input'] ) ? $op['input'] : array();
		$required   = isset( $op['required'] ) && is_array( $op['required'] ) ? array_values( $op['required'] ) : array();
		if ( ! empty( $op['destructive'] ) ) {
			$properties['confirm'] = array(
				'type'        => 'boolean',
				'description' => 'Required: true to confirm a destructive change.',
			);
			if ( ! in_array( 'confirm', $required, true ) ) {
				$required[] = 'confirm';
			}
		}
		$schema = array(
			'type'       => 'object',
			'properties' => $properties,
		);
		if ( $required ) {
			$schema['required'] = $required;
		}
		return $schema;
	}

	/**
	 * Run an official operation as the current user.
	 *
	 * @param array $op Operation.
	 * @param array $input Input.
	 * @return array
	 */
	public static function run( array $op, array $input ) {
		if ( ! empty( $op['destructive'] ) && empty( $input['confirm'] ) ) {
			throw new \InvalidArgumentException( 'This Kirki WooCommerce Bridge change requires confirm: true.' );
		}
		if ( ! self::installed() ) {
			throw new \RuntimeException( 'Kirki WooCommerce Bridge is not installed or not active. Install it from https://github.com/saaduddinsony/kirki-woocommerce-bridge (requires WooCommerce and Kirki 6.4.x), then retry.' );
		}
		unset( $input['confirm'] );
		$path = self::fill_route( $op['route'], $input );
		$body = $input;
		foreach ( self::route_params( $op['route'] ) as $param ) {
			unset( $body[ $param ] );
		}
		$result = self::request_raw( $op['method'], $path, $body );
		if ( empty( $op['readonly'] ) ) {
			Audit::record(
				'woo-bridge-' . $op['ability'],
				array(
					'method' => $op['method'],
					'path'   => $path,
				)
			);
		}
		return is_array( $result ) ? $result : array( 'data' => $result );
	}

	/**
	 * Substitute named route captures and return a REST path.
	 *
	 * @param string $route Route pattern, e.g. /templates/(?P<id>[a-z0-9]+).
	 * @param array  $input Input.
	 * @return string
	 */
	public static function fill_route( $route, array $input ) {
		$route = (string) $route;
		foreach ( self::route_params( $route ) as $param ) {
			if ( ! isset( $input[ $param ] ) || '' === (string) $input[ $param ] ) {
				throw new \InvalidArgumentException( 'Missing path parameter ' . $param . '.' );
			}
			$value = (string) $input[ $param ];
			$route = preg_replace( '/\(\?P<' . preg_quote( $param, '/' ) . '>[^)]+\)/', rawurlencode( $value ), $route, 1 );
		}
		if ( false !== strpos( $route, '(?P<' ) ) {
			throw new \InvalidArgumentException( 'The route still has unsubstituted parameters.' );
		}
		return $route;
	}

	/**
	 * @param string $route Route.
	 * @return string[]
	 */
	public static function route_params( $route ) {
		preg_match_all( '/\(\?P<([a-zA-Z0-9_]+)>/', (string) $route, $matches );
		return isset( $matches[1] ) ? $matches[1] : array();
	}

	/**
	 * The built-in 21-operation catalog (schema_version 1).
	 *
	 * @return array[]
	 */
	public static function catalog() {
		$context  = array(
			'type'        => 'string',
			'enum'        => self::contexts(),
			'description' => 'WooCommerce page context.',
		);
		$element  = array(
			'type'        => 'string',
			'description' => 'Element name (kirki-woo-product-title) or slug (product-title).',
		);
		$settings = array(
			'type'        => 'object',
			'description' => 'Element settings; see the element schema.',
		);
		$id_str   = array(
			'type'        => 'string',
			'description' => 'Template rule id.',
			'pattern'     => '^[a-z0-9]+$',
		);
		$rule     = array(
			'title'      => array( 'type' => 'string' ),
			'context'    => $context,
			'design_id'  => array(
				'type'        => 'integer',
				'description' => 'kirki_template / page post id that holds the Kirki design.',
			),
			'conditions' => array(
				'type'        => 'object',
				'description' => 'Conditions allowed for the context; see template contexts.',
			),
			'priority'   => array(
				'type'    => 'integer',
				'minimum' => -1000,
				'maximum' => 1000,
			),
			'enabled'    => array( 'type' => 'boolean' ),
		);

		$ops = array(
			array(
				'ability'     => 'get-status',
				'label'       => 'Get Kirki WooCommerce status',
				'description' => 'Plugin, WooCommerce and Kirki versions, readiness, features and counts.',
				'method'      => 'GET',
				'route'       => '/status',
				'cap'         => 'edit_posts',
				'input'       => array(),
				'readonly'    => true,
			),
			array(
				'ability'     => 'list-elements',
				'label'       => 'List WooCommerce elements',
				'description' => 'Every WooCommerce element available in the Kirki builder, with settings and JSON schema.',
				'method'      => 'GET',
				'route'       => '/elements',
				'cap'         => 'edit_posts',
				'input'       => array(
					'group' => array(
						'type' => 'string',
						'enum' => self::element_groups(),
					),
				),
				'readonly'    => true,
			),
			array(
				'ability'     => 'get-element',
				'label'       => 'Get a WooCommerce element',
				'description' => 'One element definition with its settings schema.',
				'method'      => 'GET',
				'route'       => '/elements/(?P<name>[a-z0-9-]+)',
				'cap'         => 'edit_posts',
				'input'       => array( 'name' => $element ),
				'required'    => array( 'name' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'render-element',
				'label'       => 'Render a WooCommerce element',
				'description' => 'Server-render an element to HTML (the same output the frontend uses). Used for editor previews.',
				'method'      => 'POST',
				'route'       => '/render',
				'cap'         => 'edit_posts',
				'input'       => array(
					'name'       => $element,
					'settings'   => $settings,
					'product_id' => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => 'Product to render for; 0 uses the preview product.',
					),
					'strict'     => array(
						'type'        => 'boolean',
						'description' => 'Reject unknown or invalid settings instead of coercing them.',
					),
				),
				'required'    => array( 'name' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'build-block',
				'label'       => 'Build a Kirki block for an element',
				'description' => 'Return a validated Kirki element node ready to insert into Kirki page data (does not save anything).',
				'method'      => 'POST',
				'route'       => '/blocks',
				'cap'         => 'edit_posts',
				'input'       => array(
					'name'      => $element,
					'settings'  => $settings,
					'parent_id' => array(
						'type'    => 'string',
						'default' => 'body',
					),
					'id'        => array(
						'type'    => 'string',
						'pattern' => '^[a-z0-9_]*$',
					),
				),
				'required'    => array( 'name' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'list-dynamic-fields',
				'label'       => 'List WooCommerce dynamic data fields',
				'description' => 'Product and store fields usable as Kirki dynamic content and in the [kirki_woo] shortcode.',
				'method'      => 'GET',
				'route'       => '/dynamic-data/fields',
				'cap'         => 'edit_posts',
				'input'       => array(),
				'readonly'    => true,
			),
			array(
				'ability'     => 'resolve-dynamic-field',
				'label'       => 'Resolve a WooCommerce dynamic field',
				'description' => 'Value of a product or store field for a product (or the preview product).',
				'method'      => 'POST',
				'route'       => '/dynamic-data/resolve',
				'cap'         => 'edit_posts',
				'input'       => array(
					'field'      => array( 'type' => 'string' ),
					'scope'      => array(
						'type'    => 'string',
						'enum'    => array( 'product', 'store' ),
						'default' => 'product',
					),
					'product_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'meta'       => array(
						'type'        => 'string',
						'description' => 'Attribute taxonomy / meta key for the attribute and meta fields.',
					),
				),
				'required'    => array( 'field' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'get-settings',
				'label'       => 'Get Kirki WooCommerce settings',
				'description' => 'Current settings and their schema.',
				'method'      => 'GET',
				'route'       => '/settings',
				'cap'         => 'manage_woocommerce',
				'input'       => array(),
				'readonly'    => true,
			),
			array(
				'ability'     => 'update-settings',
				'label'       => 'Update Kirki WooCommerce settings',
				'description' => 'Change one or more settings. Unknown keys are rejected.',
				'method'      => 'PUT',
				'route'       => '/settings',
				'cap'         => 'manage_woocommerce',
				'input'       => array(
					'settings' => array(
						'type'        => 'object',
						'description' => 'Partial settings object.',
					),
				),
				'required'    => array( 'settings' ),
				'idempotent'  => true,
			),
			array(
				'ability'     => 'list-template-contexts',
				'label'       => 'List template contexts and conditions',
				'description' => 'WooCommerce page contexts a Kirki design can be assigned to, and the conditions each supports.',
				'method'      => 'GET',
				'route'       => '/templates/contexts',
				'cap'         => 'edit_posts',
				'input'       => array(),
				'readonly'    => true,
			),
			array(
				'ability'     => 'list-templates',
				'label'       => 'List WooCommerce template rules',
				'description' => 'Template rules in match order (priority, then specificity).',
				'method'      => 'GET',
				'route'       => '/templates',
				'cap'         => 'manage_woocommerce',
				'input'       => array( 'context' => $context ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'create-template',
				'label'       => 'Create a WooCommerce template rule',
				'description' => 'Assign an existing Kirki design to a WooCommerce context with optional conditions.',
				'method'      => 'POST',
				'route'       => '/templates',
				'cap'         => 'manage_woocommerce',
				'input'       => $rule,
				'required'    => array( 'context', 'design_id' ),
			),
			array(
				'ability'     => 'resolve-template',
				'label'       => 'Resolve which template applies',
				'description' => 'Dry-run the matcher for a context and product / term id.',
				'method'      => 'POST',
				'route'       => '/templates/resolve',
				'cap'         => 'manage_woocommerce',
				'input'       => array(
					'context'          => $context,
					'object_id'        => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => 'Product id (single_product) or term id (product_archive).',
					),
					'account_endpoint' => array( 'type' => 'string' ),
					'logged_in'        => array( 'type' => 'boolean' ),
				),
				'required'    => array( 'context' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'get-template',
				'label'       => 'Get a WooCommerce template rule',
				'description' => 'One template rule with its design.',
				'method'      => 'GET',
				'route'       => '/templates/(?P<id>[a-z0-9]+)',
				'cap'         => 'manage_woocommerce',
				'input'       => array( 'id' => $id_str ),
				'required'    => array( 'id' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'update-template',
				'label'       => 'Update a WooCommerce template rule',
				'description' => 'Partially update a rule. conditions replaces the whole conditions object.',
				'method'      => 'PUT',
				'route'       => '/templates/(?P<id>[a-z0-9]+)',
				'cap'         => 'manage_woocommerce',
				'input'       => array_merge( array( 'id' => $id_str ), $rule ),
				'required'    => array( 'id' ),
				'idempotent'  => true,
			),
			array(
				'ability'     => 'delete-template',
				'label'       => 'Delete a WooCommerce template rule',
				'description' => 'Delete a rule. The Kirki design post is kept. Requires confirm: true.',
				'method'      => 'DELETE',
				'route'       => '/templates/(?P<id>[a-z0-9]+)',
				'cap'         => 'manage_woocommerce',
				'input'       => array( 'id' => $id_str ),
				'required'    => array( 'id' ),
				'destructive' => true,
				'idempotent'  => true,
			),
			array(
				'ability'     => 'list-designs',
				'label'       => 'List Kirki designs',
				'description' => 'Kirki templates and pages that can be assigned to WooCommerce contexts.',
				'method'      => 'GET',
				'route'       => '/designs',
				'cap'         => 'manage_woocommerce',
				'input'       => array(),
				'readonly'    => true,
			),
			array(
				'ability'     => 'create-design',
				'label'       => 'Create a Kirki design for a WooCommerce context',
				'description' => 'Create a kirki_template post (optionally seeded with a starter layout) and, by default, a rule assigning it.',
				'method'      => 'POST',
				'route'       => '/designs',
				'cap'         => 'manage_woocommerce',
				'input'       => array(
					'context'     => $context,
					'title'       => array( 'type' => 'string' ),
					'starter'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'create_rule' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'required'    => array( 'context' ),
			),
			array(
				'ability'     => 'get-design',
				'label'       => 'Get a Kirki design',
				'description' => 'A design with its published element tree (ids, names, parents, children, settings of WooCommerce elements) and the rules that use it.',
				'method'      => 'GET',
				'route'       => '/designs/(?P<id>[0-9]+)',
				'cap'         => 'manage_woocommerce',
				'input'       => array(
					'id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				'required'    => array( 'id' ),
				'readonly'    => true,
			),
			array(
				'ability'     => 'add-design-elements',
				'label'       => 'Add WooCommerce elements to a Kirki design',
				'description' => 'Insert one or more elements (validated against their schemas) into the published version of a design, under parent_id at position (append by default). Existing elements are kept.',
				'method'      => 'POST',
				'route'       => '/designs/(?P<id>[0-9]+)/elements',
				'cap'         => 'manage_woocommerce',
				'input'       => array(
					'id'        => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'elements'  => array(
						'type'     => 'array',
						'minItems' => 1,
						'maxItems' => 50,
						'items'    => array(
							'type'                 => 'object',
							'properties'           => array(
								'name'     => array(
									'type'        => 'string',
									'description' => 'Element name or slug.',
								),
								'settings' => array( 'type' => 'object' ),
							),
							'required'             => array( 'name' ),
							'additionalProperties' => false,
						),
					),
					'parent_id' => array(
						'type'        => 'string',
						'default'     => 'body',
						'description' => 'Element id to insert into (see get-design).',
					),
					'position'  => array(
						'type'        => 'integer',
						'default'     => -1,
						'description' => 'Index among the parent\'s children; -1 appends.',
					),
				),
				'required'    => array( 'id', 'elements' ),
			),
			array(
				'ability'     => 'delete-design',
				'label'       => 'Delete a Kirki WooCommerce design',
				'description' => 'Move a kirki_template design to the trash (or delete it with force) and remove the rules that use it. Pages are never deleted. Requires confirm: true.',
				'method'      => 'DELETE',
				'route'       => '/designs/(?P<id>[0-9]+)',
				'cap'         => 'manage_woocommerce',
				'input'       => array(
					'id'    => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'force' => array(
						'type'        => 'boolean',
						'default'     => false,
						'description' => 'Delete permanently instead of moving to the trash.',
					),
				),
				'required'    => array( 'id' ),
				'destructive' => true,
			),
		);

		foreach ( $ops as &$op ) {
			$op = array_merge(
				array(
					'required'    => array(),
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
				$op
			);
			if ( $op['readonly'] ) {
				$op['idempotent'] = true;
			}
		}
		unset( $op );
		return $ops;
	}

	/**
	 * @param array $base Catalog row.
	 * @param array $live Live Operations row.
	 * @return array
	 */
	private static function merge_operation( array $base, array $live ) {
		foreach ( array( 'label', 'description', 'method', 'route', 'cap', 'input', 'required', 'readonly', 'destructive', 'idempotent' ) as $key ) {
			if ( isset( $live[ $key ] ) ) {
				$base[ $key ] = $live[ $key ];
			}
		}
		if ( ! empty( $base['readonly'] ) ) {
			$base['idempotent'] = true;
		}
		return $base;
	}

	/**
	 * @param string $method Method.
	 * @param string $path Path under the namespace.
	 * @param array  $params Query or body.
	 * @return array
	 */
	private static function request_raw( $method, $path, array $params ) {
		$method = strtoupper( (string) $method );
		$path   = '/' . ltrim( (string) $path, '/' );
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
			$message = 'Kirki WooCommerce Bridge returned HTTP ' . $status . '.';
			if ( is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) ) {
				$message = $data['message'];
			}
			throw new \RuntimeException( $message );
		}
		return is_array( $data ) ? $data : array( 'data' => $data );
	}
}
