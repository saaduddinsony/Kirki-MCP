<?php
/**
 * Runtime checks for staging, publish, and symbol role order.
 *
 * Run with: wp eval-file tests/e2e/staging-check.php --path=/tmp/wp
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
function kirki_mcp_run( $name, array $parameters ) {
	$ability = wp_get_ability( 'kirki-mcp/' . $name );
	if ( ! $ability ) {
		throw new RuntimeException( 'Missing ability ' . $name );
	}
	$result = $ability->execute( empty( $parameters ) ? array() : $parameters );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	return $result;
}

$created = kirki_mcp_run(
	'create-page',
	array(
		'title'    => 'Stage check',
		'slug'     => 'stage-check',
		'status'   => 'publish',
		'sections' => array(
			array(
				'elements' => array(
					array(
						'type' => 'heading',
						'tag'  => 'h1',
						'text' => 'Published heading',
					),
				),
			),
		),
	)
);

$page_id = (int) $created['id'];
$heading = $created['tree']['children'][0]['children'][0]['children'][0]['id'];
$live    = get_post_meta( $page_id, 'kirki', true );
if ( false === strpos( wp_json_encode( $live ), 'Published heading' ) ) {
	throw new RuntimeException( 'The first publish did not store the heading in post meta.' );
}

kirki_mcp_run(
	'update-element',
	array(
		'page_id'    => $page_id,
		'element_id' => $heading,
		'text'       => 'Staged heading',
	)
);

$still_live = get_post_meta( $page_id, 'kirki', true );
if ( false !== strpos( wp_json_encode( $still_live ), 'Staged heading' ) ) {
	throw new RuntimeException( 'The edit went live immediately on a published page.' );
}
if ( 'unpublished_stage' !== kirki_mcp_run( 'get-page', array( 'page_id' => $page_id ) )['writing_to'] ) {
	throw new RuntimeException( 'The edit was not stored on an unpublished stage.' );
}

kirki_mcp_run( 'publish-page', array( 'page_id' => $page_id ) );
$published = get_post_meta( $page_id, 'kirki', true );
if ( false === strpos( wp_json_encode( $published ), 'Staged heading' ) ) {
	throw new RuntimeException( 'publish-page did not copy the staged heading onto the live page.' );
}

$header = kirki_mcp_run(
	'create-symbol',
	array(
		'name'     => 'Stage header',
		'role'     => 'header',
		'sections' => array(
			array(
				'elements' => array(
					array(
						'type' => 'heading',
						'tag'  => 'h1',
						'text' => 'Keep this header',
					),
				),
			),
		),
	)
);
$read   = kirki_mcp_run( 'get-symbol', array( 'symbol_id' => $header['id'] ) );
if ( empty( $read['tree']['children'][0]['text'] ) || 'Keep this header' !== $read['tree']['children'][0]['children'][0]['text'] && 'Keep this header' !== $read['tree']['children'][0]['text'] ) {
	$encoded = wp_json_encode( $read['tree'] );
	if ( false === strpos( $encoded, 'Keep this header' ) ) {
		throw new RuntimeException( 'get-symbol did not return the header text. ' . $encoded );
	}
}

$failed = false;
try {
	kirki_mcp_run(
		'create-symbol',
		array(
			'name'     => 'Broken header',
			'role'     => 'header',
			'sections' => array(
				array(
					'classes' => array( 'missing-class' ),
				),
			),
		)
	);
} catch ( RuntimeException $error ) {
	$failed = false !== strpos( $error->getMessage(), 'Unknown global class' );
}
if ( ! $failed ) {
	throw new RuntimeException( 'A bad symbol spec did not fail.' );
}

$kept = get_post_meta( (int) $header['id'], 'kirki', true );
if ( ! is_array( $kept ) || ( $kept['setAs'] ?? '' ) !== 'header' ) {
	throw new RuntimeException( 'The existing header lost its role when a later symbol failed.' );
}

echo "staging and symbol checks passed for page {$page_id}\n";
