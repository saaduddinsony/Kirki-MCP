<?php
/**
 * Runtime checks for staging, publish, and symbol role order.
 *
 * Test installs only. It publishes a page and temporarily takes the header
 * role, then deletes that page and restores the previous header.
 *
 * Run with: KIRKI_MCP_STAGING_CHECK=1 wp eval-file tests/e2e/staging-check.php --path=/tmp/wp
 *
 * @package KirkiMCP
 */

if ( ! function_exists( 'wp_set_current_user' ) ) {
	fwrite( STDERR, "Load this file with wp eval-file.\n" );
	return;
}

$kirki_mcp_staging_ok = ( defined( 'KIRKI_MCP_STAGING_CHECK' ) && KIRKI_MCP_STAGING_CHECK ) || getenv( 'KIRKI_MCP_STAGING_CHECK' );
if ( ! $kirki_mcp_staging_ok ) {
	fwrite( STDERR, "Refusing to run. This script publishes a page and changes the site header. Set KIRKI_MCP_STAGING_CHECK=1 on a test install.\n" );
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

$kirki_mcp_page_id   = 0;
$kirki_mcp_header_id = 0;
$kirki_mcp_previous  = 0;
$kirki_mcp_symbols   = get_posts(
	array(
		'post_type'   => 'kirki_symbol',
		'post_status' => array( 'draft', 'publish', 'private' ),
		'numberposts' => -1,
	)
);
foreach ( $kirki_mcp_symbols as $kirki_mcp_symbol ) {
	$kirki_mcp_meta = get_post_meta( $kirki_mcp_symbol->ID, 'kirki', true );
	if ( is_array( $kirki_mcp_meta ) && 'header' === ( $kirki_mcp_meta['setAs'] ?? '' ) ) {
		$kirki_mcp_previous = (int) $kirki_mcp_symbol->ID;
		break;
	}
}

try {
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

	$page_id           = (int) $created['id'];
	$kirki_mcp_page_id = $page_id;
	$heading           = $created['tree']['children'][0]['children'][0]['children'][0]['id'];
	$live              = get_post_meta( $page_id, 'kirki', true );
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
	$after = kirki_mcp_run( 'get-page', array( 'page_id' => $page_id ) );
	if ( null !== $after['stage'] || 'published_meta' !== $after['writing_to'] ) {
		throw new RuntimeException( 'publish-page left an older revision open as the working stage.' );
	}

	kirki_mcp_run(
		'update-element',
		array(
			'page_id'    => $page_id,
			'element_id' => $heading,
			'text'       => 'Second edit',
		)
	);
	kirki_mcp_run( 'publish-page', array( 'page_id' => $page_id ) );
	$second = get_post_meta( $page_id, 'kirki', true );
	if ( false === strpos( wp_json_encode( $second ), 'Second edit' ) ) {
		throw new RuntimeException( 'The second publish did not keep the new edit.' );
	}
	kirki_mcp_run( 'publish-page', array( 'page_id' => $page_id ) );
	$third = get_post_meta( $page_id, 'kirki', true );
	if ( false === strpos( wp_json_encode( $third ), 'Second edit' ) || false !== strpos( wp_json_encode( $third ), 'Published heading' ) ) {
		throw new RuntimeException( 'Publishing again reverted the page to an older stage.' );
	}

	$header                = kirki_mcp_run(
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
	$kirki_mcp_header_id   = (int) $header['id'];
	$read                  = kirki_mcp_run( 'get-symbol', array( 'symbol_id' => $header['id'] ) );
	$kirki_mcp_header_text = isset( $read['tree']['children'][0]['text'] ) ? $read['tree']['children'][0]['text'] : '';
	$kirki_mcp_nested_text = isset( $read['tree']['children'][0]['children'][0]['text'] ) ? $read['tree']['children'][0]['children'][0]['text'] : '';
	if ( 'Keep this header' !== $kirki_mcp_header_text && 'Keep this header' !== $kirki_mcp_nested_text ) {
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

	echo 'staging and symbol checks passed for page ' . esc_html( (string) $page_id ) . "\n";
} finally {
	if ( $kirki_mcp_previous && $kirki_mcp_previous !== $kirki_mcp_header_id ) {
		try {
			kirki_mcp_run(
				'update-symbol',
				array(
					'symbol_id' => $kirki_mcp_previous,
					'role'      => 'header',
				)
			);
		} catch ( Throwable $error ) {
			fwrite( STDERR, 'Could not restore the previous header: ' . $error->getMessage() . "\n" );
		}
	}
	if ( $kirki_mcp_header_id ) {
		wp_delete_post( $kirki_mcp_header_id, true );
	}
	if ( $kirki_mcp_page_id ) {
		wp_delete_post( $kirki_mcp_page_id, true );
	}
}
