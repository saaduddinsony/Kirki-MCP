<?php
/**
 * Build a Kirki landing page through the MCP HTTP endpoint.
 *
 * Uses an application password and no browser nonce. Run it against a site
 * that has WordPress, Kirki 6.4.x, MCP Adapter, and this plugin active:
 *
 *   KIRKI_MCP_URL=http://127.0.0.1:8080 \
 *   KIRKI_MCP_USER=admin \
 *   KIRKI_MCP_APP_PASSWORD='xxxx xxxx xxxx xxxx' \
 *   php tests/e2e/landing.php
 *
 * @package KirkiMCP
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$base     = getenv( 'KIRKI_MCP_URL' ) ?: 'http://127.0.0.1:8080';
$user     = getenv( 'KIRKI_MCP_USER' ) ?: 'admin';
$password = getenv( 'KIRKI_MCP_APP_PASSWORD' );
if ( ! is_string( $password ) || '' === trim( $password ) ) {
	fwrite( STDERR, "Set KIRKI_MCP_APP_PASSWORD.\n" );
	exit( 1 );
}

$endpoint = rtrim( $base, '/' ) . '/wp-json/mcp/mcp-adapter-default-server';
$session  = null;

/**
 * Send one JSON-RPC request.
 *
 * @param string     $method Method.
 * @param array|null $params Params.
 * @param bool       $notify Notification without an id.
 * @return array
 */
function mcp_request( $method, $params = null, $notify = false ) {
	global $endpoint, $user, $password, $session;

	$body = array(
		'jsonrpc' => '2.0',
		'method'  => $method,
	);
	if ( ! $notify ) {
		$body['id'] = random_int( 1, 1000000 );
	}
	if ( null !== $params ) {
		$body['params'] = $params;
	}

	$headers = array(
		'Content-Type: application/json',
		'Accept: application/json, text/event-stream',
		'Authorization: Basic ' . base64_encode( $user . ':' . $password ),
	);
	if ( $session ) {
		$headers[] = 'Mcp-Session-Id: ' . $session;
		$headers[] = 'MCP-Protocol-Version: 2025-06-18';
	}

	$handle = curl_init( $endpoint );
	curl_setopt( $handle, CURLOPT_POST, true );
	curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
	curl_setopt( $handle, CURLOPT_POSTFIELDS, json_encode( $body ) );
	curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $handle, CURLOPT_HEADER, true );
	$raw  = curl_exec( $handle );
	$code = curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	$size = curl_getinfo( $handle, CURLINFO_HEADER_SIZE );
	curl_close( $handle );

	if ( ! is_string( $raw ) ) {
		fwrite( STDERR, "MCP request failed for {$method}.\n" );
		exit( 1 );
	}
	$headers_raw = substr( $raw, 0, $size );
	$payload     = substr( $raw, $size );
	if ( preg_match( '/^Mcp-Session-Id:\s*(\S+)/mi', $headers_raw, $match ) ) {
		$session = trim( $match[1] );
	}
	if ( $notify ) {
		return array();
	}
	$decoded = json_decode( $payload, true );
	if ( ! is_array( $decoded ) ) {
		fwrite( STDERR, "MCP {$method} returned HTTP {$code}: {$payload}\n" );
		exit( 1 );
	}
	if ( isset( $decoded['error'] ) ) {
		fwrite( STDERR, "MCP {$method} error: " . json_encode( $decoded['error'] ) . "\n" );
		exit( 1 );
	}
	return $decoded;
}

/**
 * Run one Kirki ability.
 *
 * @param string $name Ability name.
 * @param array  $parameters Arguments.
 * @return array
 */
function ability( $name, array $parameters ) {
	$response = mcp_request(
		'tools/call',
		array(
			'name'      => 'mcp-adapter-execute-ability',
			'arguments' => array(
				'ability_name' => $name,
				'parameters'   => empty( $parameters ) ? new stdClass() : $parameters,
			),
		)
	);
	$text     = $response['result']['content'][0]['text'] ?? '';
	$data     = json_decode( $text, true );
	if ( ! is_array( $data ) || empty( $data['success'] ) ) {
		fwrite( STDERR, "{$name} failed: {$text}\n" );
		exit( 1 );
	}
	echo $name, " ok\n";
	return $data['data'];
}

mcp_request(
	'initialize',
	array(
		'protocolVersion' => '2025-06-18',
		'capabilities'    => new stdClass(),
		'clientInfo'      => array(
			'name'    => 'kirki-mcp-e2e',
			'version' => '0.1.0',
		),
	)
);
mcp_request( 'notifications/initialized', null, true );

$image  = imagecreatetruecolor( 960, 640 );
$fill   = imagecolorallocate( $image, 15, 23, 42 );
$accent = imagecolorallocate( $image, 194, 65, 12 );
$ink    = imagecolorallocate( $image, 248, 250, 252 );
imagefilledrectangle( $image, 0, 0, 960, 640, $fill );
imagefilledrectangle( $image, 0, 0, 18, 640, $accent );
imagestring( $image, 5, 48, 290, 'Northwind workshop', $ink );
ob_start();
imagepng( $image );
$png = ob_get_clean();
imagedestroy( $image );

$direct = curl_init( rtrim( $base, '/' ) . '/wp-json/wp/v2/media' );
curl_setopt( $direct, CURLOPT_POST, true );
curl_setopt(
	$direct,
	CURLOPT_HTTPHEADER,
	array(
		'Authorization: Basic ' . base64_encode( $user . ':' . $password ),
		'Content-Type: image/png',
		'Content-Disposition: attachment; filename="direct-app-password.png"',
	)
);
curl_setopt( $direct, CURLOPT_POSTFIELDS, $png );
curl_setopt( $direct, CURLOPT_RETURNTRANSFER, true );
$direct_body = curl_exec( $direct );
$direct_code = curl_getinfo( $direct, CURLINFO_RESPONSE_CODE );
curl_close( $direct );
$direct_json = json_decode( (string) $direct_body, true );
if ( 201 !== $direct_code || empty( $direct_json['id'] ) ) {
	fwrite( STDERR, "Direct wp/v2/media upload failed ({$direct_code}): {$direct_body}\n" );
	exit( 1 );
}
echo "wp/v2/media application-password upload ok id={$direct_json['id']}\n";

$uploaded = ability(
	'kirki-mcp/upload-media',
	array(
		'filename'    => 'northwind-hero.png',
		'data_base64' => base64_encode( $png ),
		'alt'         => 'Abstract workshop panel in navy and rust',
	)
);

$variables = ability(
	'kirki-mcp/set-global-variables',
	array(
		'colors' => array(
			array(
				'name'  => 'Ink',
				'value' => '#0f172a',
			),
			array(
				'name'  => 'Paper',
				'value' => '#f8fafc',
			),
			array(
				'name'  => 'Accent',
				'value' => '#c2410c',
			),
		),
		'fonts'  => array(
			array(
				'name'  => 'Body',
				'value' => 'Inter, system-ui, sans-serif',
			),
		),
	)
);

$color_id = array();
foreach ( $variables['variables']['color'] as $color ) {
	$color_id[ $color['name'] ] = $color['id'];
}
$ink_var    = 'var(--' . $color_id['Ink'] . ')';
$paper_var  = 'var(--' . $color_id['Paper'] . ')';
$accent_var = 'var(--' . $color_id['Accent'] . ')';

ability(
	'kirki-mcp/upsert-global-class',
	array(
		'name'   => 'eyebrow',
		'styles' => array(
			'md' => 'font-size:12px;letter-spacing:0.16em;text-transform:uppercase;font-weight:600;color:' . $accent_var . ';',
		),
	)
);

ability(
	'kirki-mcp/create-symbol',
	array(
		'name'     => 'Site header',
		'role'     => 'header',
		'sections' => array(
			array(
				'styles'   => array(
					'md' => 'flex-direction:row;justify-content:space-between;align-items:center;padding:20px 8vw;background:' . $paper_var . ';color:' . $ink_var . ';width:100%;box-sizing:border-box;',
				),
				'elements' => array(
					array(
						'type'   => 'heading',
						'tag'    => 'h1',
						'text'   => 'Northwind',
						'styles' => array(
							'md' => 'font-size:20px;font-weight:700;',
						),
					),
					array(
						'type'    => 'paragraph',
						'text'    => 'A Kirki site built by an agent',
						'classes' => array( 'eyebrow' ),
					),
				),
			),
		),
	)
);

ability(
	'kirki-mcp/create-symbol',
	array(
		'name'     => 'Site footer',
		'role'     => 'footer',
		'sections' => array(
			array(
				'styles'   => array(
					'md' => 'padding:32px 8vw;background:' . $ink_var . ';color:' . $paper_var . ';width:100%;box-sizing:border-box;',
				),
				'elements' => array(
					array(
						'type' => 'paragraph',
						'text' => 'Northwind studio. Made with Kirki MCP.',
					),
				),
			),
		),
	)
);

$page = ability(
	'kirki-mcp/create-page',
	array(
		'title'    => 'Northwind',
		'slug'     => 'northwind',
		'status'   => 'draft',
		'sections' => array(
			array(
				'key'      => 'hero',
				'title'    => 'Hero',
				'styles'   => array(
					'md'     => 'flex-direction:column;gap:20px;padding:88px 8vw;background:' . $ink_var . ';color:' . $paper_var . ';width:100%;box-sizing:border-box;',
					'mobile' => 'padding:48px 24px;',
				),
				'elements' => array(
					array(
						'type'    => 'paragraph',
						'text'    => 'Workshop',
						'classes' => array( 'eyebrow' ),
					),
					array(
						'type'   => 'heading',
						'tag'    => 'h1',
						'text'   => 'Sites with a freeform canvas',
						'styles' => array(
							'md'     => 'font-size:64px;line-height:1.05;font-weight:700;color:' . $paper_var . ';',
							'mobile' => 'font-size:36px;',
						),
					),
					array(
						'type'   => 'paragraph',
						'text'   => 'This page was created through MCP, with an application password and no browser nonce. Open it in the Kirki editor to keep editing.',
						'styles' => array(
							'md' => 'font-size:18px;max-width:640px;color:' . $paper_var . ';',
						),
					),
					array(
						'type'     => 'button',
						'text'     => 'Start a project',
						'link_key' => 'contact',
						'styles'   => array(
							'md' => 'background:' . $accent_var . ';border-radius:999px;',
						),
					),
					array(
						'type'          => 'image',
						'src'           => $uploaded['url'],
						'alt'           => $uploaded['alt'],
						'attachment_id' => $uploaded['id'],
						'styles'        => array(
							'md' => 'width:100%;max-width:960px;height:auto;border-radius:16px;margin-top:12px;',
						),
					),
				),
			),
			array(
				'key'      => 'work',
				'title'    => 'Work',
				'styles'   => array(
					'md'     => 'flex-direction:row;flex-wrap:wrap;gap:24px;padding:72px 8vw;background:' . $paper_var . ';color:' . $ink_var . ';width:100%;box-sizing:border-box;',
					'mobile' => 'flex-direction:column;padding:48px 24px;',
				),
				'elements' => array(
					array(
						'type'     => 'div',
						'styles'   => array( 'md' => 'flex-direction:column;gap:8px;flex:1;min-width:220px;' ),
						'elements' => array(
							array(
								'type' => 'heading',
								'tag'  => 'h2',
								'text' => 'Pages',
							),
							array(
								'type' => 'paragraph',
								'text' => 'Sections, text, images, and buttons in the shape Kirki stores.',
							),
						),
					),
					array(
						'type'     => 'div',
						'styles'   => array( 'md' => 'flex-direction:column;gap:8px;flex:1;min-width:220px;' ),
						'elements' => array(
							array(
								'type' => 'heading',
								'tag'  => 'h2',
								'text' => 'Global styles',
							),
							array(
								'type' => 'paragraph',
								'text' => 'Colors and classes are shared across the site.',
							),
						),
					),
					array(
						'type'     => 'div',
						'styles'   => array( 'md' => 'flex-direction:column;gap:8px;flex:1;min-width:220px;' ),
						'elements' => array(
							array(
								'type' => 'heading',
								'tag'  => 'h2',
								'text' => 'Header and footer',
							),
							array(
								'type' => 'paragraph',
								'text' => 'Symbols assigned as the site header and footer.',
							),
						),
					),
				),
			),
			array(
				'key'      => 'contact',
				'title'    => 'Contact',
				'styles'   => array(
					'md' => 'flex-direction:column;gap:12px;padding:72px 8vw;background:#fff7ed;color:' . $ink_var . ';width:100%;box-sizing:border-box;',
				),
				'elements' => array(
					array(
						'type' => 'heading',
						'tag'  => 'h2',
						'text' => 'Start a project',
					),
					array(
						'type' => 'paragraph',
						'text' => 'Tell us what you want to build. The button in the hero links here.',
					),
				),
			),
		),
	)
);

$published = ability(
	'kirki-mcp/publish-page',
	array(
		'page_id'   => $page['id'],
		'set_front' => true,
	)
);

$home = curl_init( rtrim( $base, '/' ) . '/' );
curl_setopt( $home, CURLOPT_RETURNTRANSFER, true );
$html      = curl_exec( $home );
$home_code = curl_getinfo( $home, CURLINFO_RESPONSE_CODE );
curl_close( $home );

$needles = array(
	'Sites with a freeform canvas',
	'Start a project',
	'Northwind studio. Made with Kirki MCP.',
	'Global styles',
	'northwind-hero.png',
);
$missing = array();
foreach ( $needles as $needle ) {
	if ( ! is_string( $html ) || false === strpos( $html, $needle ) ) {
		$missing[] = $needle;
	}
}

$result = array(
	'page_id'         => $published['id'],
	'permalink'       => $published['permalink'],
	'editor_url'      => $published['editor_url'],
	'status'          => $published['status'],
	'home_http'       => $home_code,
	'missing'         => $missing,
	'media_id'        => $uploaded['id'],
	'direct_media_id' => $direct_json['id'],
);
file_put_contents( sys_get_temp_dir() . '/kirki-mcp-landing.json', json_encode( $result, JSON_PRETTY_PRINT ) );

if ( 200 !== $home_code || $missing ) {
	fwrite( STDERR, 'Front page check failed: ' . json_encode( $result ) . "\n" );
	exit( 1 );
}

echo "Landing page {$published['permalink']} is live. Editor: {$published['editor_url']}\n";
