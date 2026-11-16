<?php
/**
 * Front-end checks for class cascade, anchors, and font loading.
 *
 * Run with: wp eval-file tests/e2e/class-anchor-check.php --path=/tmp/wp
 *
 * @package KirkiMCP
 */

if ( ! function_exists( 'wp_set_current_user' ) ) {
	fwrite( STDERR, "Load this file with wp eval-file.\n" );
	return;
}

wp_set_current_user( 1 );

/**
 * @param string $name Ability.
 * @param array  $parameters Parameters.
 * @return array
 */
function kirki_mcp_check( $name, array $parameters ) {
	$ability = wp_get_ability( 'kirki-mcp/' . $name );
	$result  = $ability->execute( empty( $parameters ) ? array() : $parameters );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	return $result;
}

kirki_mcp_check(
	'upsert-global-class',
	array(
		'name'   => 't-display',
		'styles' => array(
			'md' => 'font-size:64px;font-family:Fraunces,serif;',
		),
	)
);

$fonts = kirki_mcp_check(
	'set-global-variables',
	array(
		'fonts' => array(
			array(
				'name'  => 'font-display',
				'value' => 'Fraunces, serif',
			),
		),
	)
);
if ( empty( $fonts['fonts_loaded'][0]['font_url'] ) || false === strpos( $fonts['fonts_loaded'][0]['font_url'], 'fonts.googleapis.com' ) ) {
	throw new RuntimeException( 'The font variable did not register a stylesheet.' );
}

$created_page = kirki_mcp_check(
	'create-page',
	array(
		'title'    => 'Anchor check',
		'slug'     => 'anchor-check',
		'status'   => 'publish',
		'sections' => array(
			array(
				'key'      => 'damex-digital',
				'elements' => array(
					array(
						'type'    => 'heading',
						'tag'     => 'h1',
						'text'    => 'Damex',
						'classes' => array( 't-display' ),
					),
					array(
						'type'  => 'list',
						'items' => array( 'Scope', 'Price' ),
					),
				),
			),
		),
	)
);

kirki_mcp_check(
	'update-custom-code',
	array(
		'page_id' => $created_page['id'],
		'head'    => '<style>:root{--makerspace-ink:#17181a}</style>',
	)
);

$html = Kirki\HelperFunctions::retrieve_post_content( $created_page['id'] );
$code = get_post_meta( $created_page['id'], 'kirki_page_custom_code', true );
$head = is_array( $code ) && isset( $code['head']['value'] ) ? $code['head']['value'] : '';
$font = Kirki\HelperFunctions::get_custom_fonts_tags();

if ( false === strpos( $html, 'id="damex-digital"' ) ) {
	throw new RuntimeException( 'The section key did not become an HTML id.' );
}
if ( false === strpos( $html, '<ul ' ) ) {
	throw new RuntimeException( 'The list did not render as a ul.' );
}
if ( false !== strpos( $html, 'font-size:16px' ) ) {
	throw new RuntimeException( 'The heading still has the default 16px rule.' );
}
if ( false === strpos( $html, '.t-display' ) || false === strpos( $html, 'font-size:64px' ) ) {
	throw new RuntimeException( 'The global class rule is missing from the page CSS.' );
}
if ( false === strpos( $head, '--makerspace-ink' ) ) {
	throw new RuntimeException( 'Custom head code was not stored for the page.' );
}
if ( false === strpos( $font, 'fonts.googleapis.com' ) || false === strpos( $font, 'Fraunces' ) ) {
	throw new RuntimeException( 'Kirki did not print the font stylesheet. ' . $font );
}

echo 'class, anchor, list, and font checks passed for page ' . esc_html( (string) $created_page['id'] ) . "\n";
