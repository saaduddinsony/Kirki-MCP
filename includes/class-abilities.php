<?php
/**
 * WordPress abilities exposed through the MCP Adapter.
 *
 * The default MCP server lists these as kirki-mcp/ability-name and runs them
 * with mcp-adapter/execute-ability. Tool names with a slash are presented to
 * MCP clients as kirki-mcp-ability-name.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ability registration.
 */
class Abilities {

	const CATEGORY = 'kirki-mcp';

	/**
	 * Register the ability category.
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Kirki MCP', 'kirki-mcp' ),
				'description' => __( 'Build and edit Kirki sites.', 'kirki-mcp' ),
			)
		);
	}

	/**
	 * Register every ability.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'wp_register_ability' ) || ! defined( 'KIRKI_VERSION' ) ) {
			return;
		}
		if ( version_compare( KIRKI_VERSION, '6.0.0', '<' ) || version_compare( KIRKI_VERSION, '7.0.0', '>=' ) ) {
			return;
		}

		foreach ( self::definitions() as $name => $args ) {
			wp_register_ability( 'kirki-mcp/' . $name, $args );
		}
	}

	/**
	 * Ability definitions.
	 *
	 * @return array
	 */
	private static function definitions() {
		$page    = self::page_id_schema();
		$styles  = self::style_schema();
		$element = self::element_schema();
		$section = array(
			'type'                 => 'object',
			'description'          => 'A page section. Put headings, paragraphs, buttons, images, frames, and links in elements.',
			'additionalProperties' => true,
			'properties'           => array(
				'key'      => array(
					'type'        => 'string',
					'description' => 'Stable name, used by link_key.',
				),
				'tag'      => array(
					'type' => 'string',
				),
				'title'    => array(
					'type' => 'string',
				),
				'styles'   => $styles,
				'classes'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'elements' => array(
					'type'  => 'array',
					'items' => $element,
				),
			),
		);

		return array(
			'get-site-context'      => self::tool(
				'Get Kirki site context',
				'Read this before designing. Returns the Kirki version, viewports, global color, size, and font variables, global classes, and header or footer symbols.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'get_site_context' )
			),
			'get-element-schema'    => self::tool(
				'Get the Kirki element schema',
				'Describes the section and element spec accepted by create-page, add-section, add-element, and create-symbol, including CSS variant names.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'get_element_schema' )
			),
			'list-pages'            => self::tool(
				'List pages',
				'List WordPress pages. By default only pages built with Kirki are returned.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(
						'page'       => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page'   => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 20,
						),
						'kirki_only' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
				),
				array( self::class, 'list_pages' )
			),
			'get-page'              => self::tool(
				'Read a Kirki page',
				'Read a page before changing it. Returns a compact element tree with ids, the local style blocks, the permalink, and the Kirki editor URL.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id'     => $page,
						'include_raw' => array(
							'type'        => 'boolean',
							'description' => 'Also return the stored element map.',
							'default'     => false,
						),
					),
					'required'   => array( 'page_id' ),
				),
				array( self::class, 'get_page' )
			),
			'create-page'           => self::tool(
				'Create a Kirki page',
				'Create a page from sections. The page is a WordPress draft unless status is publish. Sections stack on the body. Use key and link_key for in-page links. Classes must already exist.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'title'    => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 200,
						),
						'slug'     => array(
							'type' => 'string',
						),
						'status'   => array(
							'type'    => 'string',
							'enum'    => array( 'draft', 'publish' ),
							'default' => 'draft',
						),
						'sections' => array(
							'type'  => 'array',
							'items' => $section,
						),
					),
					'required'   => array( 'title' ),
				),
				array( self::class, 'create_page' ),
				false
			),
			'update-page'           => self::tool(
				'Update page title or slug',
				'Change the WordPress title or slug of a Kirki page. This does not change the design. Use add-section, update-element, or update-styles for the design.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id' => $page,
						'title'   => array(
							'type' => 'string',
						),
						'slug'    => array(
							'type' => 'string',
						),
					),
					'required'   => array( 'page_id' ),
				),
				array( self::class, 'update_page' ),
				true
			),
			'add-section'           => self::tool(
				'Add a section',
				'Insert one section onto the page body at an optional index. Read the page first if you need to choose a position.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id' => $page,
						'index'   => array(
							'type'        => 'integer',
							'description' => 'Zero-based position among the body children. Omit to append.',
							'minimum'     => 0,
						),
						'section' => $section,
					),
					'required'   => array( 'page_id', 'section' ),
				),
				array( self::class, 'add_section' )
			),
			'add-element'           => self::tool(
				'Add an element',
				'Insert an element under a parent from the page tree. Parents that can hold children are body, section, div, link-block, and button.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id'   => $page,
						'parent_id' => array(
							'type' => 'string',
						),
						'index'     => array(
							'type'    => 'integer',
							'minimum' => 0,
						),
						'element'   => $element,
					),
					'required'   => array( 'page_id', 'parent_id', 'element' ),
				),
				array( self::class, 'add_element' )
			),
			'update-element'        => self::tool(
				'Update an element',
				'Change text, tag, link, image source, or alt on an existing element. Pass section_id to link a button to a section element id. Does not remove the element.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id'       => $page,
						'element_id'    => array(
							'type' => 'string',
						),
						'text'          => array(
							'type' => 'string',
						),
						'tag'           => array(
							'type' => 'string',
						),
						'title'         => array(
							'type' => 'string',
						),
						'href'          => array(
							'type' => 'string',
						),
						'section_id'    => array(
							'type'        => 'string',
							'description' => 'Element id of a section this button should scroll to.',
						),
						'target'        => array(
							'type' => 'string',
							'enum' => array( '_self', '_blank' ),
						),
						'src'           => array(
							'type' => 'string',
						),
						'alt'           => array(
							'type' => 'string',
						),
						'attachment_id' => array(
							'type' => 'integer',
						),
						'classes'       => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'required'   => array( 'page_id', 'element_id' ),
				),
				array( self::class, 'update_element' ),
				true
			),
			'update-styles'         => self::tool(
				'Update element styles',
				'Replace the named CSS variants on an element. Read the page to see the current variant. Keys are md, tablet, mobile, md_hover, and md_active. This replaces those keys; it does not append.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id'    => $page,
						'element_id' => array(
							'type' => 'string',
						),
						'styles'     => $styles,
					),
					'required'   => array( 'page_id', 'element_id', 'styles' ),
				),
				array( self::class, 'update_styles' ),
				true
			),
			'remove-element'        => self::tool(
				'Remove an element',
				'Remove an element and its children. This is destructive and does nothing unless confirm is true. Root and body cannot be removed.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id'    => $page,
						'element_id' => array(
							'type' => 'string',
						),
						'confirm'    => array(
							'type'        => 'boolean',
							'description' => 'Must be true to remove the element.',
						),
					),
					'required'   => array( 'page_id', 'element_id', 'confirm' ),
				),
				array( self::class, 'remove_element' ),
				false,
				true
			),
			'duplicate-page'        => self::tool(
				'Duplicate a page',
				'Duplicate a Kirki page as a new draft, including its elements and styles.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id' => $page,
					),
					'required'   => array( 'page_id' ),
				),
				array( self::class, 'duplicate_page' )
			),
			'publish-page'          => self::tool(
				'Publish a page',
				'Promote an unpublished Kirki stage when one exists, set the WordPress page to publish, and optionally make it the site front page.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id'   => $page,
						'set_front' => array(
							'type'        => 'boolean',
							'description' => 'Make this the site front page. Requires an administrator.',
							'default'     => false,
						),
					),
					'required'   => array( 'page_id' ),
				),
				array( self::class, 'publish_page' ),
				true
			),
			'trash-page'            => self::tool(
				'Trash a page',
				'Move a page to the WordPress trash. Requires confirm set to true.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id' => $page,
						'confirm' => array(
							'type' => 'boolean',
						),
					),
					'required'   => array( 'page_id', 'confirm' ),
				),
				array( self::class, 'trash_page' ),
				false,
				true
			),
			'list-global-classes'   => self::tool(
				'List global classes',
				'List Kirki global classes and their CSS variants.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'list_global_classes' ),
				true,
				false,
				'edit_theme_options'
			),
			'upsert-global-class'   => self::tool(
				'Create or update a global class',
				'Create a site-wide class, or replace the given CSS variants of an existing class with the same name. Attach it to an element with the classes field.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'name'   => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'styles' => $styles,
					),
					'required'   => array( 'name', 'styles' ),
				),
				array( self::class, 'upsert_global_class' ),
				true,
				false,
				'edit_theme_options'
			),
			'list-global-variables' => self::tool(
				'List global variables',
				'List global colors, sizes, and font families. Use css_var in element CSS, for example color:var(--the-id).',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'list_global_variables' ),
				true,
				false,
				'edit_theme_options'
			),
			'set-global-variables'  => self::tool(
				'Set global colors, fonts, and sizes',
				'Create or update global design variables by name. Colors are hex or rgb(). Fonts are family names. Sizes need a number and a unit. Existing names are updated.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'colors' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'  => array( 'type' => 'string' ),
									'value' => array( 'type' => 'string' ),
								),
								'required'   => array( 'name', 'value' ),
							),
						),
						'fonts'  => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'  => array( 'type' => 'string' ),
									'value' => array( 'type' => 'string' ),
								),
								'required'   => array( 'name', 'value' ),
							),
						),
						'sizes'  => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'  => array( 'type' => 'string' ),
									'value' => array( 'type' => 'number' ),
									'unit'  => array(
										'type' => 'string',
										'enum' => array( 'px', 'rem', 'em', '%', 'vh', 'vw' ),
									),
								),
								'required'   => array( 'name', 'value' ),
							),
						),
					),
				),
				array( self::class, 'set_global_variables' ),
				true,
				false,
				'edit_theme_options'
			),
			'upload-media'          => self::tool(
				'Upload an image',
				'Upload an image through WordPress core POST /wp/v2/media, authenticated as the current user. Kirki 6.4.2 uploads media through admin-ajax with a browser nonce, which an application password cannot send, and its REST API only lists media. Pass the returned id as attachment_id and url as src on an image element. data_base64 is the raw file with no data: prefix. Limited to 5 MB images.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'filename'    => array(
							'type'        => 'string',
							'description' => 'File name ending in png, jpg, jpeg, gif, or webp.',
						),
						'data_base64' => array(
							'type' => 'string',
						),
						'alt'         => array(
							'type' => 'string',
						),
					),
					'required'   => array( 'filename', 'data_base64' ),
				),
				array( self::class, 'upload_media' ),
				false,
				false,
				'upload_files'
			),
			'list-symbols'          => self::tool(
				'List symbols',
				'List Kirki symbols. set_as is header, footer, or empty for a reusable component.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'list_symbols' )
			),
			'get-symbol'            => self::tool(
				'Read a symbol',
				'Read one symbol, including its element tree.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(
						'symbol_id' => array(
							'type' => 'integer',
						),
					),
					'required'   => array( 'symbol_id' ),
				),
				array( self::class, 'get_symbol' )
			),
			'create-symbol'         => self::tool(
				'Create a symbol',
				'Create a symbol from sections. Set role to header or footer to use it as the site header or footer. Kirki allows one header and one footer; the previous symbol in that role is unassigned.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'name'     => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'role'     => array(
							'type'    => 'string',
							'enum'    => array( 'header', 'footer', 'component' ),
							'default' => 'component',
						),
						'sections' => array(
							'type'  => 'array',
							'items' => $section,
						),
					),
					'required'   => array( 'name' ),
				),
				array( self::class, 'create_symbol' )
			),
			'update-symbol'         => self::tool(
				'Update a symbol',
				'Rename a symbol, change whether it is the header or footer, or replace its sections. Omit sections to keep the current design.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'symbol_id' => array(
							'type' => 'integer',
						),
						'name'      => array(
							'type' => 'string',
						),
						'role'      => array(
							'type' => 'string',
							'enum' => array( 'header', 'footer', 'component' ),
						),
						'sections'  => array(
							'type'  => 'array',
							'items' => $section,
						),
					),
					'required'   => array( 'symbol_id' ),
				),
				array( self::class, 'update_symbol' ),
				true
			),
			'list-templates'        => self::tool(
				'List Kirki templates',
				'List kirki_template posts. Applying a template ZIP is not available in this version.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'list_templates' )
			),
		);
	}

	/**
	 * @return void
	 */
	public static function get_site_context() {
		return self::guard(
			function () {
				return Global_Repository::context();
			}
		);
	}

	/**
	 * @return array
	 */
	public static function get_element_schema() {
		return Spec_Compiler::schema();
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function list_pages( $input ) {
		return self::guard(
			function () use ( $input ) {
				$page = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
				$per  = isset( $input['per_page'] ) ? min( 50, max( 1, (int) $input['per_page'] ) ) : 20;
				$only = ! isset( $input['kirki_only'] ) || true === $input['kirki_only'];
				return Page_Repository::list_pages( $page, $per, $only );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function get_page( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Page_Repository::present( (int) $input['page_id'], ! empty( $input['include_raw'] ) );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function create_page( $input ) {
		return self::guard(
			function () use ( $input ) {
				$status = isset( $input['status'] ) ? $input['status'] : 'draft';
				if ( 'publish' === $status && true !== current_user_can( 'publish_pages' ) ) {
					throw new \InvalidArgumentException( 'Publishing requires the publish_pages capability. Create a draft instead.' );
				}
				$sections = isset( $input['sections'] ) && is_array( $input['sections'] ) ? $input['sections'] : array();
				Page_Repository::resolve_classes( $sections );
				$page_id  = Page_Repository::create(
					$input['title'],
					isset( $input['slug'] ) ? $input['slug'] : '',
					$status
				);
				$document = Document::from_sections( $sections );
				Page_Repository::save( $page_id, $document, null );
				$result          = Page_Repository::present( $page_id );
				$result['keys']  = $document->keys;
				return $result;
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function update_page( $input ) {
		return self::guard(
			function () use ( $input ) {
				Page_Repository::update_settings(
					(int) $input['page_id'],
					isset( $input['title'] ) ? $input['title'] : '',
					isset( $input['slug'] ) ? $input['slug'] : ''
				);
				return Page_Repository::present( (int) $input['page_id'] );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function add_section( $input ) {
		return self::guard(
			function () use ( $input ) {
				return self::mutate(
					(int) $input['page_id'],
					function ( Document $document ) use ( $input ) {
						$wrapped = array( $input['section'] );
						Page_Repository::resolve_classes( $wrapped );
						$compiled = $document->insert_section( $wrapped[0], isset( $input['index'] ) ? (int) $input['index'] : null );
						return $compiled['keys'];
					}
				);
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function add_element( $input ) {
		return self::guard(
			function () use ( $input ) {
				return self::mutate(
					(int) $input['page_id'],
					function ( Document $document ) use ( $input ) {
						$element = $input['element'];
						$wrapped = array( $element );
						Page_Repository::resolve_classes( $wrapped );
						$compiled = $document->insert_element( $wrapped[0], (string) $input['parent_id'], isset( $input['index'] ) ? (int) $input['index'] : null );
						return $compiled['keys'];
					}
				);
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function update_element( $input ) {
		return self::guard(
			function () use ( $input ) {
				return self::mutate(
					(int) $input['page_id'],
					function ( Document $document ) use ( $input ) {
						$changes = $input;
						unset( $changes['page_id'], $changes['element_id'] );
						if ( ! empty( $changes['classes'] ) && is_array( $changes['classes'] ) ) {
							$spec = array( array( 'classes' => $changes['classes'] ) );
							Page_Repository::resolve_classes( $spec );
							$changes['class_ids'] = $spec[0]['class_ids'];
						}
						$document->update_element( (string) $input['element_id'], $changes );
						return array();
					}
				);
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function update_styles( $input ) {
		return self::guard(
			function () use ( $input ) {
				return self::mutate(
					(int) $input['page_id'],
					function ( Document $document ) use ( $input ) {
						$document->update_styles( (string) $input['element_id'], $input['styles'] );
						return array();
					}
				);
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function remove_element( $input ) {
		return self::guard(
			function () use ( $input ) {
				return self::mutate(
					(int) $input['page_id'],
					function ( Document $document ) use ( $input ) {
						$removed = $document->remove_element( (string) $input['element_id'], ! empty( $input['confirm'] ) );
						return array( 'removed' => $removed );
					}
				);
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function duplicate_page( $input ) {
		return self::guard(
			function () use ( $input ) {
				$copy = Page_Repository::duplicate( (int) $input['page_id'] );
				return Page_Repository::present( $copy );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function publish_page( $input ) {
		return self::guard(
			function () use ( $input ) {
				if ( true !== current_user_can( 'publish_pages' ) ) {
					throw new \InvalidArgumentException( 'Publishing requires the publish_pages capability.' );
				}
				Page_Repository::publish( (int) $input['page_id'], ! empty( $input['set_front'] ) );
				return Page_Repository::present( (int) $input['page_id'] );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function trash_page( $input ) {
		return self::guard(
			function () use ( $input ) {
				if ( true !== current_user_can( 'delete_pages' ) ) {
					throw new \InvalidArgumentException( 'Trashing a page requires the delete_pages capability.' );
				}
				Page_Repository::trash( (int) $input['page_id'], ! empty( $input['confirm'] ) );
				return array(
					'trashed' => true,
					'page_id' => (int) $input['page_id'],
				);
			}
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function list_global_classes() {
		return self::guard(
			function () {
				return array( 'classes' => Global_Repository::list_classes() );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function upsert_global_class( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Global_Repository::upsert_class( $input['name'], $input['styles'] );
			}
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function list_global_variables() {
		return self::guard(
			function () {
				return array( 'variables' => Global_Repository::list_variables() );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function set_global_variables( $input ) {
		return self::guard(
			function () use ( $input ) {
				return array(
					'variables' => Global_Repository::set_variables(
						isset( $input['colors'] ) && is_array( $input['colors'] ) ? $input['colors'] : array(),
						isset( $input['fonts'] ) && is_array( $input['fonts'] ) ? $input['fonts'] : array(),
						isset( $input['sizes'] ) && is_array( $input['sizes'] ) ? $input['sizes'] : array()
					),
				);
			}
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function upload_media( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Media_Repository::upload_base64(
					$input['filename'],
					$input['data_base64'],
					isset( $input['alt'] ) ? $input['alt'] : ''
				);
			}
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function list_symbols() {
		return self::guard(
			function () {
				Page_Repository::assert_ready();
				return array( 'symbols' => Symbol_Repository::summaries() );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function get_symbol( $input ) {
		return self::guard(
			function () use ( $input ) {
				Page_Repository::assert_ready();
				return Symbol_Repository::get( (int) $input['symbol_id'] );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function create_symbol( $input ) {
		return self::guard(
			function () use ( $input ) {
				$sections = isset( $input['sections'] ) && is_array( $input['sections'] ) ? $input['sections'] : array();
				return Symbol_Repository::create(
					$input['name'],
					isset( $input['role'] ) ? $input['role'] : 'component',
					$sections
				);
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function update_symbol( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Symbol_Repository::update(
					(int) $input['symbol_id'],
					isset( $input['name'] ) ? $input['name'] : null,
					isset( $input['role'] ) ? $input['role'] : null,
					isset( $input['sections'] ) && is_array( $input['sections'] ) ? $input['sections'] : null
				);
			}
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function list_templates() {
		return self::guard(
			function () {
				Page_Repository::assert_ready();
				return array( 'templates' => Symbol_Repository::list_templates() );
			}
		);
	}

	/**
	 * Load, mutate, and save a page.
	 *
	 * @param int      $page_id Page id.
	 * @param callable $mutator Receives the document and returns extra keys.
	 * @return array
	 */
	private static function mutate( $page_id, $mutator ) {
		$loaded = Page_Repository::load( $page_id );
		$extra  = call_user_func( $mutator, $loaded['document'] );
		Page_Repository::save( $page_id, $loaded['document'], $loaded['stage'] );
		$result = Page_Repository::present( $page_id );
		if ( is_array( $extra ) ) {
			$result['operation'] = $extra;
		}
		return $result;
	}

	/**
	 * Convert exceptions into ability errors.
	 *
	 * @param callable $callback Work.
	 * @return mixed
	 */
	private static function guard( $callback ) {
		try {
			return call_user_func( $callback );
		} catch ( \InvalidArgumentException $error ) {
			return new \WP_Error( 'kirki_mcp_invalid', $error->getMessage() );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'kirki_mcp_failed', $error->getMessage() );
		}
	}

	/**
	 * Shared ability arguments.
	 *
	 * @param string   $label Label.
	 * @param string   $description Description.
	 * @param bool     $readonly Read only.
	 * @param array    $input_schema Input schema.
	 * @param callable $callback Callback.
	 * @param bool     $idempotent Idempotent.
	 * @param bool     $destructive Destructive.
	 * @param string   $capability Capability.
	 * @return array
	 */
	private static function tool( $label, $description, $readonly, array $input_schema, $callback, $idempotent = false, $destructive = false, $capability = 'edit_pages' ) {
		return array(
			'label'               => $label,
			'description'         => $description,
			'category'            => self::CATEGORY,
			'input_schema'        => $input_schema,
			'execute_callback'    => $callback,
			'permission_callback' => function () use ( $capability ) {
				return true === current_user_can( $capability );
			},
			'meta'                => array(
				'public'       => true,
				'show_in_rest' => true,
				'mcp'          => array(
					'type' => 'tool',
				),
				'annotations'  => array(
					'readonly'    => $readonly,
					'destructive' => $destructive,
					'idempotent'  => $idempotent,
				),
			),
		);
	}

	/**
	 * @return array
	 */
	private static function page_id_schema() {
		return array(
			'type'        => 'integer',
			'description' => 'WordPress page id.',
			'minimum'     => 1,
		);
	}

	/**
	 * @return array
	 */
	private static function style_schema() {
		return array(
			'type'                 => 'object',
			'description'          => 'CSS declaration lists keyed by variant: md, tablet, mobile, md_hover, md_active.',
			'additionalProperties' => array(
				'type' => 'string',
			),
		);
	}

	/**
	 * @return array
	 */
	private static function element_schema() {
		return array(
			'type'                 => 'object',
			'description'          => 'An element. type is section, div, heading, paragraph, text, button, image, or link-block. Nested elements go in elements.',
			'additionalProperties' => true,
			'properties'           => array(
				'type'     => array(
					'type' => 'string',
				),
				'key'      => array(
					'type' => 'string',
				),
				'text'     => array(
					'type' => 'string',
				),
				'tag'      => array(
					'type' => 'string',
				),
				'href'     => array(
					'type' => 'string',
				),
				'link_key' => array(
					'type' => 'string',
				),
				'target'   => array(
					'type' => 'string',
				),
				'src'      => array(
					'type' => 'string',
				),
				'alt'      => array(
					'type' => 'string',
				),
				'styles'   => self::style_schema(),
				'classes'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'elements' => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),
			),
			'required'             => array( 'type' ),
		);
	}
}
