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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		$definitions = self::definitions();
		if ( ! Compatibility::supported() ) {
			$always      = array(
				'get-site-context',
				'list-theme-mods',
				'get-theme-mod',
				'set-theme-mod',
				'get-option',
				'set-option',
				'list-customizer-fields',
				'list-changesets',
				'publish-changeset',
				'ecommerce-status',
				'ecommerce-get',
				'ecommerce-mutate',
			);
			$definitions = array_intersect_key( $definitions, array_flip( $always ) );
		}
		foreach ( $definitions as $name => $args ) {
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
			'get-site-context'       => self::tool(
				'Get Kirki site context',
				'Read this before designing. Returns the Kirki version, viewports, global color, size, and font variables, global classes, and header or footer symbols.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'get_site_context' ),
				true,
				false,
				'read'
			),
			'get-element-schema'     => self::tool(
				'Get the Kirki element schema',
				'Describes the section and element spec accepted by create-page, add-section, add-element, and create-symbol, including CSS variant names.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'get_element_schema' )
			),
			'list-pages'             => self::tool(
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
			'get-page'               => self::tool(
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
			'create-page'            => self::tool(
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
			'update-page'            => self::tool(
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
			'update-custom-code'     => self::tool(
				'Update page custom code',
				'Write head or body code that Kirki prints immediately. This is not staged. Head code must be one or more <style> blocks. Body code is filtered with wp_kses_post. Requires Kirki builder access and unfiltered_html.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id' => $page,
						'head'    => array(
							'type'        => 'string',
							'description' => 'HTML printed in the head, such as a style element.',
						),
						'body'    => array(
							'type' => 'string',
						),
					),
					'required'   => array( 'page_id' ),
				),
				array( self::class, 'update_custom_code' ),
				true,
				false,
				'unfiltered_html'
			),
			'update-page-settings'   => self::tool(
				'Update SEO settings',
				'Set the meta description Kirki prints, and an optional SEO title. The description is also stored as the page excerpt. SEO settings are written to published meta immediately. Kirki does not stage them.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id'          => $page,
						'meta_description' => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'seo_title'        => array(
							'type' => 'string',
						),
					),
					'required'   => array( 'page_id', 'meta_description' ),
				),
				array( self::class, 'update_page_settings' ),
				true
			),
			'add-section'            => self::tool(
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
			'add-element'            => self::tool(
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
			'update-element'         => self::tool(
				'Update an element',
				'Change text, tag, link, image source, or alt on an existing element. Pass section_id to link a button to a section element id. Does not remove the element.',
				false,
				array(
					'type'                 => 'object',
					'properties'           => array(
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
						'convert'       => array(
							'type'        => 'boolean',
							'description' => 'Turn a non-Kirki page into a Kirki page. Omit it to refuse that page.',
						),
					),
					'additionalProperties' => false,
					'required'             => array( 'page_id', 'element_id' ),
				),
				array( self::class, 'update_element' ),
				true
			),
			'update-styles'          => self::tool(
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
			'reapply-global-classes' => self::tool(
				'Reapply global classes',
				'Remove Kirki\'s default local CSS from elements that already have a global class. Pass page_id or symbol_id. symbol_id alone is enough. clear_local also removes CSS copied onto the element by hand. Page edits are staged. Symbol edits are saved on the symbol.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id'     => $page,
						'symbol_id'   => array(
							'type'        => 'integer',
							'description' => 'Symbol id, instead of page_id, for a header, footer, or component.',
						),
						'clear_local' => array(
							'type'        => 'boolean',
							'description' => 'Drop the entire local rule on classed elements.',
						),
						'convert'     => array(
							'type' => 'boolean',
						),
					),
					'required'   => array(),
				),
				array( self::class, 'reapply_global_classes' ),
				true
			),
			'remove-element'         => self::tool(
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
			'duplicate-page'         => self::tool(
				'Duplicate a page',
				'Duplicate a Kirki page as a new draft, including its elements and styles. The copy is always a draft, even when the source is published. Optional title and slug replace the copy title and permalink.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'page_id' => $page,
						'title'   => array(
							'type'        => 'string',
							'description' => 'Title for the copy. Defaults to the source title plus " copy".',
						),
						'slug'    => array(
							'type'        => 'string',
							'description' => 'Slug for the copy. Made unique if that permalink is taken.',
						),
					),
					'required'   => array( 'page_id' ),
				),
				array( self::class, 'duplicate_page' )
			),
			'publish-page'           => self::tool(
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
				true,
				false,
				'publish_pages'
			),
			'trash-page'             => self::tool(
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
			'register-font'          => self::tool(
				'Register a web font',
				'Register a Google Fonts stylesheet so Kirki loads it on the front end. The URL must be https://fonts.googleapis.com. Example family: Fraunces. Example url: https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500&display=swap.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'family' => array(
							'type' => 'string',
						),
						'url'    => array(
							'type' => 'string',
						),
					),
					'required'   => array( 'family', 'url' ),
				),
				array( self::class, 'register_font' )
			),
			'list-global-classes'    => self::tool(
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
				'edit_pages'
			),
			'upsert-global-class'    => self::tool(
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
				'edit_pages'
			),
			'list-global-variables'  => self::tool(
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
				'edit_pages'
			),
			'set-global-variables'   => self::tool(
				'Set global colors, fonts, and sizes',
				'Create or update global design variables by name. Colors are hex or rgb(). Fonts are family names. Sizes need a number and a unit. Existing names are updated.',
				false,
				array(
					'type'       => 'object',
					'properties' => array(
						'colors'      => array(
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
						'fonts'       => array(
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
						'text_styles' => array(
							'type'        => 'array',
							'description' => 'Named text styles. styles is a map of md, tablet, or mobile to font-size, line-height, letter-spacing, font-weight, and font-style.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'name'        => array( 'type' => 'string' ),
									'font_family' => array( 'type' => 'string' ),
									'styles'      => array(
										'type' => 'object',
									),
								),
								'required'   => array( 'name' ),
							),
						),
						'sizes'       => array(
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
				'edit_pages'
			),
			'upload-media'           => self::tool(
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
			'list-symbols'           => self::tool(
				'List symbols',
				'List Kirki symbols. set_as is header, footer, or empty for a reusable component.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'list_symbols' )
			),
			'get-symbol'             => self::tool(
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
			'create-symbol'          => self::tool(
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
			'update-symbol'          => self::tool(
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
			'audit-unsafe-titles'    => self::tool(
				'Audit unsafe titles',
				'Read-only. Lists Kirki pages and symbols whose stored section titles or symbol names still contain characters Kirki would print unescaped in an HTML id. It does not change them. Saving the page or symbol slugs those titles.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'audit_unsafe_titles' ),
				true
			),
			'list-templates'         => self::tool(
				'List Kirki templates',
				'List kirki_template posts. Applying a template ZIP is not available in this version.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'list_templates' )
			),
			'list-popups'            => self::tool(
				'List Kirki popups',
				'List kirki_popup posts. The popup data format exists in free Kirki 6.4.2; Kirki Pro advertises a fuller popup builder.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 20,
						),
					),
				),
				array( self::class, 'list_popups' )
			),
			'get-popup'              => self::tool(
				'Get a Kirki popup',
				'Return one kirki_popup post.',
				true,
				array(
					'type'       => 'object',
					'required'   => array( 'popup_id' ),
					'properties' => array(
						'popup_id' => array( 'type' => 'integer' ),
					),
				),
				array( self::class, 'get_popup' )
			),
			'create-popup'           => self::tool(
				'Create a Kirki popup',
				'Create a kirki_popup draft or published post from the same section spec used by create-page.',
				false,
				array(
					'type'       => 'object',
					'required'   => array( 'title', 'sections' ),
					'properties' => array(
						'title'    => array( 'type' => 'string' ),
						'sections' => array( 'type' => 'array' ),
						'status'   => array(
							'type' => 'string',
							'enum' => array( 'draft', 'publish' ),
						),
					),
				),
				array( self::class, 'create_popup' )
			),
			'update-popup'           => self::tool(
				'Update a Kirki popup',
				'Update a popup title and optionally replace its sections.',
				false,
				array(
					'type'       => 'object',
					'required'   => array( 'popup_id' ),
					'properties' => array(
						'popup_id' => array( 'type' => 'integer' ),
						'title'    => array( 'type' => 'string' ),
						'sections' => array( 'type' => 'array' ),
					),
				),
				array( self::class, 'update_popup' )
			),
			'trash-popup'            => self::tool(
				'Trash a Kirki popup',
				'Move a kirki_popup post to the trash. Requires confirm: true.',
				false,
				array(
					'type'       => 'object',
					'required'   => array( 'popup_id', 'confirm' ),
					'properties' => array(
						'popup_id' => array( 'type' => 'integer' ),
						'confirm'  => array( 'type' => 'boolean' ),
					),
				),
				array( self::class, 'trash_popup' ),
				false,
				true
			),
			'content-manager-get'    => self::tool(
				'Read a Kirki Content Manager collection',
				'GET an allowlisted /kirki/v1/content-manager route. Path examples: /content-manager/collections, /content-manager/collections/1, /content-manager/collections/items. This is the visual-builder Content Manager, not Kirki eCommerce collections.',
				true,
				array(
					'type'       => 'object',
					'required'   => array( 'path' ),
					'properties' => array(
						'path'  => array( 'type' => 'string' ),
						'query' => array( 'type' => 'object' ),
					),
				),
				array( self::class, 'content_manager_get' )
			),
			'content-manager-mutate' => self::tool(
				'Create or update a Kirki Content Manager collection',
				'POST or DELETE an allowlisted /kirki/v1/content-manager route. DELETE requires confirm: true.',
				false,
				array(
					'type'       => 'object',
					'required'   => array( 'method', 'path' ),
					'properties' => array(
						'method'  => array(
							'type' => 'string',
							'enum' => array( 'POST', 'DELETE' ),
						),
						'path'    => array( 'type' => 'string' ),
						'body'    => array( 'type' => 'object' ),
						'confirm' => array( 'type' => 'boolean' ),
					),
				),
				array( self::class, 'content_manager_mutate' ),
				false,
				true
			),
			'list-theme-mods'        => self::tool(
				'List theme mods',
				'List WordPress theme_mods for the current theme. Values that look like secrets are omitted.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'list_theme_mods' ),
				true,
				false,
				'edit_theme_options'
			),
			'get-theme-mod'          => self::tool(
				'Get a theme mod',
				'Read one theme_mod by name.',
				true,
				array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name' => array( 'type' => 'string' ),
					),
				),
				array( self::class, 'get_theme_mod' ),
				true,
				false,
				'edit_theme_options'
			),
			'set-theme-mod'          => self::tool(
				'Set a theme mod',
				'Write one theme_mod. Does not invent new Customizer field registrations; it writes the stored value.',
				false,
				array(
					'type'       => 'object',
					'required'   => array( 'name', 'value' ),
					'properties' => array(
						'name'  => array( 'type' => 'string' ),
						'value' => array(),
					),
				),
				array( self::class, 'set_theme_mod' ),
				false,
				false,
				'edit_theme_options'
			),
			'get-option'             => self::tool(
				'Get a registered option',
				'Read a WordPress option. Core auth options, salts, passwords, license keys, and Kirki eCommerce payment options are blocked. Unknown new options cannot be created here.',
				true,
				array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name' => array( 'type' => 'string' ),
					),
				),
				array( self::class, 'get_option' ),
				true,
				false,
				'manage_options'
			),
			'set-option'             => self::tool(
				'Set a registered option',
				'Write a WordPress option that is already registered (for example by a Kirki field). Blocked keys and brand-new option names are rejected.',
				false,
				array(
					'type'       => 'object',
					'required'   => array( 'name', 'value' ),
					'properties' => array(
						'name'  => array( 'type' => 'string' ),
						'value' => array(),
					),
				),
				array( self::class, 'set_option' ),
				false,
				false,
				'manage_options'
			),
			'list-customizer-fields' => self::tool(
				'List registered Kirki Customizer fields',
				'List fields registered in PHP by Kirki or a theme. Creating new panels, sections, or control types at runtime is not supported.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'list_customizer_fields' ),
				true,
				false,
				'edit_theme_options'
			),
			'list-changesets'        => self::tool(
				'List Customizer changesets',
				'List customize_changeset posts (Customizer drafts).',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'list_changesets' ),
				true,
				false,
				'edit_theme_options'
			),
			'publish-changeset'      => self::tool(
				'Publish a Customizer changeset',
				'Publish a customize_changeset post. WordPress Customizer publish has additional side effects in the Customizer UI that this call may not fully reproduce. Requires confirm: true.',
				false,
				array(
					'type'       => 'object',
					'required'   => array( 'changeset_id', 'confirm' ),
					'properties' => array(
						'changeset_id' => array( 'type' => 'integer' ),
						'confirm'      => array( 'type' => 'boolean' ),
					),
				),
				array( self::class, 'publish_changeset' ),
				false,
				true,
				'edit_theme_options'
			),
			'ecommerce-status'       => self::tool(
				'Kirki eCommerce status',
				'Whether the standalone kirki-ecommerce plugin is installed. The Kirki visual builder is not required. Never returns secrets.',
				true,
				array(
					'type'       => 'object',
					'properties' => array(),
				),
				array( self::class, 'ecommerce_status' ),
				true,
				false,
				'manage_options'
			),
			'ecommerce-get'          => self::tool(
				'Read a Kirki eCommerce admin route',
				'GET an allowlisted /wp-json/kirki/ecommerce/v1 path, for example /products, /orders, /coupons, /settings/general, /migration/sources, or /dashboard/overview. Returns a clear error when Kirki eCommerce is not installed. Payment webhooks and gateway installs are not available.',
				true,
				array(
					'type'       => 'object',
					'required'   => array( 'path' ),
					'properties' => array(
						'path'  => array( 'type' => 'string' ),
						'query' => array( 'type' => 'object' ),
					),
				),
				array( self::class, 'ecommerce_get' ),
				true,
				false,
				'manage_options'
			),
			'ecommerce-mutate'       => self::tool(
				'Create or update Kirki eCommerce data',
				'POST, PUT, PATCH, or DELETE an allowlisted kirki/ecommerce/v1 admin route. DELETE requires confirm: true. Catalog, orders, coupons, customers, shipping, tax, settings, and WooCommerce migration runs are allowed. Payment credential writes, gateway plugin installs, webhooks, and password changes are not.',
				false,
				array(
					'type'       => 'object',
					'required'   => array( 'method', 'path' ),
					'properties' => array(
						'method'  => array(
							'type' => 'string',
							'enum' => array( 'POST', 'PUT', 'PATCH', 'DELETE' ),
						),
						'path'    => array( 'type' => 'string' ),
						'body'    => array( 'type' => 'object' ),
						'confirm' => array( 'type' => 'boolean' ),
					),
				),
				array( self::class, 'ecommerce_mutate' ),
				false,
				true,
				'manage_options'
			),
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function get_site_context() {
		return self::guard(
			function () {
				$reason            = Compatibility::unsupported_reason();
				list( $min, $max ) = Compatibility::range();
				$shared            = array(
					'plugin_version'    => KIRKI_MCP_VERSION,
					'kirki_version'     => defined( 'KIRKI_VERSION' ) ? KIRKI_VERSION : '',
					'kirki_pro'         => Compatibility::is_pro_user(),
					'kirki_ecommerce'   => Ecommerce_Repository::status(),
					'builder_supported' => ( '' === $reason ),
					'supported_range'   => array(
						'minimum' => $min,
						'below'   => $max,
					),
				);
				if ( '' !== $reason ) {
					$shared['supported'] = false;
					$shared['reason']    = $reason;
					return $shared;
				}
				$context                   = Global_Repository::context();
				$context                   = array_merge( $context, $shared );
				$context['supported']      = true;
				$context['style_variants'] = Css::variant_vocabulary();
				return $context;
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
				if ( 'publish' === $status && ! Access::can_publish() ) {
					throw new \InvalidArgumentException( 'Publishing requires Kirki builder access and publish_pages. Create a draft instead.' );
				}
				$sections = isset( $input['sections'] ) && is_array( $input['sections'] ) ? $input['sections'] : array();
				Page_Repository::resolve_classes( $sections );
				$document = Document::from_sections( $sections );
				$document->validate( Global_Repository::class_id_list() );
				$page_id = Page_Repository::create(
					$input['title'],
					isset( $input['slug'] ) ? $input['slug'] : '',
					'draft'
				);
				try {
					Page_Repository::save( $page_id, $document, null );
					if ( 'publish' === $status ) {
						Page_Repository::publish( $page_id, false );
					}
				} catch ( \Throwable $error ) {
					wp_delete_post( $page_id, true );
					throw $error;
				}
				$result         = Page_Repository::present( $page_id );
				$result['keys'] = $document->keys;
				return $result;
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function update_custom_code( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Page_Repository::update_custom_code(
					(int) $input['page_id'],
					isset( $input['head'] ) ? $input['head'] : '',
					isset( $input['body'] ) ? $input['body'] : ''
				);
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function update_page_settings( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Page_Repository::update_seo(
					(int) $input['page_id'],
					$input['meta_description'],
					isset( $input['seo_title'] ) ? $input['seo_title'] : ''
				);
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
					},
					! empty( $input['convert'] )
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
					},
					! empty( $input['convert'] )
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
						unset( $changes['page_id'], $changes['element_id'], $changes['class_ids'], $changes['classes'], $changes['convert'] );
						$document->update_element( (string) $input['element_id'], $changes );
						if ( ! empty( $input['classes'] ) && is_array( $input['classes'] ) ) {
							$spec = array( array( 'classes' => $input['classes'] ) );
							Page_Repository::resolve_classes( $spec );
							$document->set_class_ids( (string) $input['element_id'], $spec[0]['class_ids'] );
						}
						return array();
					},
					! empty( $input['convert'] )
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
					},
					! empty( $input['convert'] )
				);
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function reapply_global_classes( $input ) {
		return self::guard(
			function () use ( $input ) {
				if ( empty( $input['symbol_id'] ) && empty( $input['page_id'] ) ) {
					throw new \InvalidArgumentException( 'Pass page_id or symbol_id.' );
				}
				if ( ! empty( $input['symbol_id'] ) ) {
					Symbol_Repository::assert_symbol_post( get_post( (int) $input['symbol_id'] ) );
					return array(
						'symbol_id' => (int) $input['symbol_id'],
						'elements'  => Symbol_Repository::reapply_classes( (int) $input['symbol_id'], ! empty( $input['clear_local'] ) ),
					);
				}
				$target = (int) $input['page_id'];
				$post   = get_post( $target );
				if ( $post && 'kirki_symbol' === $post->post_type ) {
					return array(
						'symbol_id' => $target,
						'elements'  => Symbol_Repository::reapply_classes( $target, ! empty( $input['clear_local'] ) ),
					);
				}
				return self::mutate(
					$target,
					function ( Document $document ) use ( $input ) {
						$count = $document->release_class_defaults( ! empty( $input['clear_local'] ) );
						return array( 'elements' => $count );
					},
					! empty( $input['convert'] )
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
					},
					! empty( $input['convert'] )
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
				$copy = Page_Repository::duplicate(
					(int) $input['page_id'],
					isset( $input['title'] ) ? (string) $input['title'] : '',
					isset( $input['slug'] ) ? (string) $input['slug'] : ''
				);
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
				if ( ! Access::can_publish() ) {
					throw new \InvalidArgumentException( 'Publishing requires Kirki builder access and the publish_pages capability.' );
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
				if ( ! Access::can_delete_page( (int) $input['page_id'] ) ) {
					throw new \InvalidArgumentException( 'Trashing this page requires Kirki builder access and delete_post for the page.' );
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
	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function register_font( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Global_Repository::register_font( $input['family'], $input['url'] );
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
				return Global_Repository::set_variables(
					isset( $input['colors'] ) && is_array( $input['colors'] ) ? $input['colors'] : array(),
					isset( $input['fonts'] ) && is_array( $input['fonts'] ) ? $input['fonts'] : array(),
					isset( $input['sizes'] ) && is_array( $input['sizes'] ) ? $input['sizes'] : array(),
					isset( $input['text_styles'] ) && is_array( $input['text_styles'] ) ? $input['text_styles'] : array()
				);
			}
		);
	}

	/**
	 * @param array $input Input.
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
	public static function audit_unsafe_titles() {
		return self::guard(
			function () {
				return array(
					'pages'   => Page_Repository::audit_unsafe_titles(),
					'symbols' => Symbol_Repository::audit_unsafe_titles(),
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
	 * @param bool     $convert Allow turning a non-Kirki page into a Kirki page.
	 * @return array
	 */
	private static function mutate( $page_id, $mutator, $convert = false ) {
		$loaded = Page_Repository::load( $page_id, $convert );
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
			$caller = '';
			$trace  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- The caller name decides whether a read-only ability may migrate.
			if ( isset( $trace[1]['function'] ) ) {
				$caller = (string) $trace[1]['function'];
			}
			if ( Plugin::migrates_for( $caller ) ) {
				Plugin::migrate_if_needed();
			}
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
	 * @param bool     $read_only Read only.
	 * @param array    $input_schema Input schema.
	 * @param callable $callback Callback.
	 * @param bool     $idempotent Idempotent.
	 * @param bool     $destructive Destructive.
	 * @param string   $capability Capability.
	 * @return array
	 */
	private static function tool( $label, $description, $read_only, array $input_schema, $callback, $idempotent = false, $destructive = false, $capability = 'edit_pages' ) {
		return array(
			'label'               => $label,
			'description'         => $description,
			'category'            => self::CATEGORY,
			'input_schema'        => $input_schema,
			'execute_callback'    => $callback,
			'permission_callback' => function () use ( $capability ) {
				if ( 'read' === $capability ) {
					return Access::can_read_context();
				}
				if ( 'publish_pages' === $capability ) {
					return Access::can_publish();
				}
				if ( 'upload_files' === $capability ) {
					return Access::can_upload();
				}
				if ( 'delete_pages' === $capability ) {
					return Access::can_build();
				}
				if ( 'unfiltered_html' === $capability ) {
					return Access::can_edit_custom_code();
				}
				if ( 'manage_options' === $capability ) {
					return Access::can_manage_store();
				}
				if ( 'edit_theme_options' === $capability ) {
					return Access::can_customize();
				}
				return Access::can_build();
			},
			'meta'                => array(
				'public'       => true,
				'show_in_rest' => true,
				'mcp'          => array(
					'type' => 'tool',
				),
				'annotations'  => array(
					'readonly'    => $read_only,
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
			'description'          => 'An element. type is section, div, heading, paragraph, text, link-text, button, image, link-block, navigation, form, or list.',
			'additionalProperties' => false,
			'properties'           => array(
				'type'            => array(
					'type' => 'string',
				),
				'key'             => array(
					'type'        => 'string',
					'description' => 'Stable name. A section key becomes its HTML id, so a key of damex-digital is the target of /work/#damex-digital.',
				),
				'anchor'          => array(
					'type'        => 'string',
					'description' => 'HTML id. Overrides key for the id only.',
				),
				'hide_on'         => array(
					'type'        => 'array',
					'description' => 'Viewports to hide: md, tablet, or mobile.',
					'items'       => array( 'type' => 'string' ),
				),
				'ordered'         => array(
					'type'        => 'boolean',
					'description' => 'True renders a numbered list.',
				),
				'submit'          => array(
					'type'        => 'boolean',
					'description' => 'True makes a button submit the form that contains it.',
				),
				'success_message' => array(
					'type'        => 'string',
					'description' => 'Shown after the form submits.',
				),
				'error_message'   => array(
					'type'        => 'string',
					'description' => 'Shown when the form submit fails.',
				),
				'submit_label'    => array(
					'type'        => 'string',
					'description' => 'Label of the submit button a form adds automatically. Default Send.',
				),
				'text'            => array(
					'type' => 'string',
				),
				'tag'             => array(
					'type' => 'string',
				),
				'href'            => array(
					'type' => 'string',
				),
				'link_key'        => array(
					'type' => 'string',
				),
				'target'          => array(
					'type' => 'string',
				),
				'src'             => array(
					'type' => 'string',
				),
				'alt'             => array(
					'type' => 'string',
				),
				'styles'          => self::style_schema(),
				'classes'         => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'inline'          => array(
					'type'        => 'array',
					'description' => 'Mixed strings and links for heading, paragraph, or text. A link is an object with text and href.',
				),
				'items'           => array(
					'type'        => 'array',
					'description' => 'Navigation items with text and href or link_key.',
				),
				'email'           => array( 'type' => 'string' ),
				'subject'         => array( 'type' => 'string' ),
				'name'            => array( 'type' => 'string' ),
				'title'           => array( 'type' => 'string' ),
				'fields'          => array(
					'type'        => 'array',
					'description' => 'Form fields with name, label, type, and required.',
				),
				'attachment_id'   => array( 'type' => 'integer' ),
				'elements'        => array(
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

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function list_popups( $input ) {
		return self::guard(
			function () use ( $input ) {
				$page = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
				$per  = isset( $input['per_page'] ) ? min( 50, max( 1, (int) $input['per_page'] ) ) : 20;
				return Popup_Repository::list_popups( $page, $per );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function get_popup( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Popup_Repository::present( (int) $input['popup_id'] );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function create_popup( $input ) {
		return self::guard(
			function () use ( $input ) {
				$status   = isset( $input['status'] ) ? $input['status'] : 'draft';
				$sections = isset( $input['sections'] ) && is_array( $input['sections'] ) ? $input['sections'] : array();
				Page_Repository::resolve_classes( $sections );
				return Popup_Repository::create( $input['title'], $sections, $status );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function update_popup( $input ) {
		return self::guard(
			function () use ( $input ) {
				$sections = array_key_exists( 'sections', $input ) && is_array( $input['sections'] ) ? $input['sections'] : null;
				if ( is_array( $sections ) ) {
					Page_Repository::resolve_classes( $sections );
				}
				return Popup_Repository::update(
					(int) $input['popup_id'],
					$sections,
					isset( $input['title'] ) ? $input['title'] : ''
				);
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function trash_popup( $input ) {
		return self::guard(
			function () use ( $input ) {
				if ( empty( $input['confirm'] ) ) {
					throw new \InvalidArgumentException( 'Trashing a popup requires confirm: true.' );
				}
				return Popup_Repository::trash( (int) $input['popup_id'] );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function content_manager_get( $input ) {
		return self::guard(
			function () use ( $input ) {
				$query = isset( $input['query'] ) && is_array( $input['query'] ) ? $input['query'] : array();
				return Collection_Repository::request( 'GET', $input['path'], $query );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function content_manager_mutate( $input ) {
		return self::guard(
			function () use ( $input ) {
				$method = strtoupper( (string) $input['method'] );
				if ( 'DELETE' === $method && empty( $input['confirm'] ) ) {
					throw new \InvalidArgumentException( 'Deleting a Content Manager record requires confirm: true.' );
				}
				$body = isset( $input['body'] ) && is_array( $input['body'] ) ? $input['body'] : array();
				return Collection_Repository::request( $method, $input['path'], $body );
			}
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function list_theme_mods() {
		return self::guard(
			function () {
				return Customizer_Repository::list_mods();
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function get_theme_mod( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Customizer_Repository::get_mod( $input['name'] );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function set_theme_mod( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Customizer_Repository::set_mod( $input['name'], $input['value'] );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function get_option( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Customizer_Repository::get_option( $input['name'] );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function set_option( $input ) {
		return self::guard(
			function () use ( $input ) {
				return Customizer_Repository::set_option( $input['name'], $input['value'] );
			}
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function list_customizer_fields() {
		return self::guard(
			function () {
				return array( 'fields' => Customizer_Repository::registered_fields() );
			}
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function list_changesets() {
		return self::guard(
			function () {
				return Customizer_Repository::list_changesets();
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function publish_changeset( $input ) {
		return self::guard(
			function () use ( $input ) {
				if ( empty( $input['confirm'] ) ) {
					throw new \InvalidArgumentException( 'Publishing a changeset requires confirm: true.' );
				}
				return Customizer_Repository::publish_changeset( (int) $input['changeset_id'] );
			}
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function ecommerce_status() {
		return self::guard(
			function () {
				return Ecommerce_Repository::status();
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function ecommerce_get( $input ) {
		return self::guard(
			function () use ( $input ) {
				$query = isset( $input['query'] ) && is_array( $input['query'] ) ? $input['query'] : array();
				return Ecommerce_Repository::request( 'GET', $input['path'], $query );
			}
		);
	}

	/**
	 * @param array $input Input.
	 * @return array|\WP_Error
	 */
	public static function ecommerce_mutate( $input ) {
		return self::guard(
			function () use ( $input ) {
				$method = strtoupper( (string) $input['method'] );
				if ( 'DELETE' === $method && empty( $input['confirm'] ) ) {
					throw new \InvalidArgumentException( 'Deleting Kirki eCommerce records requires confirm: true.' );
				}
				$body = isset( $input['body'] ) && is_array( $input['body'] ) ? $input['body'] : array();
				return Ecommerce_Repository::request( $method, $input['path'], $body );
			}
		);
	}
}
