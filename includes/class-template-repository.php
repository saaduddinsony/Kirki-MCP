<?php
/**
 * Kirki template export/import and the Pro pre-built / premium catalog.
 *
 * Site packs use Kirki's TemplateExport / TemplateImport. Single-page zips use
 * the kirki-data.json format. Import never overwrites a published page unless
 * confirm is true; new applies land on a draft by default.
 *
 * Premium catalog URLs are the public CloudFront endpoints the Pro 6.4.3
 * editor uses. A stored license key is appended only at download time and is
 * never returned.
 *
 * @package KirkiMCP
 */

namespace KirkiMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template export, import, and catalog.
 */
class Template_Repository {

	const ASSETS_BASE = 'https://d31d7414w5c76z.cloudfront.net';

	const PREBUILT = array(
		'pages'       => array(
			'type' => 'page',
			'base' => '/pre-built/pre-built-pages',
		),
		'navigations' => array(
			'type' => 'section',
			'base' => '/pre-built/pre-built-navigations',
		),
		'heroes'      => array(
			'type' => 'section',
			'base' => '/pre-built/pre-built-heroes',
		),
		'features'    => array(
			'type' => 'section',
			'base' => '/pre-built/pre-built-features',
		),
		'buttons'     => array(
			'type' => 'element',
			'base' => '/pre-built/pre-built-buttons',
		),
		'cards'       => array(
			'type' => 'element',
			'base' => '/pre-built/pre-built-cards',
		),
		'forms'       => array(
			'type' => 'element',
			'base' => '/pre-built/pre-built-forms',
		),
		'interactive' => array(
			'type' => 'element',
			'base' => '/pre-built/pre-built-interactives',
		),
	);

	const ALLOWED_EXTENSIONS = array(
		'jpg',
		'jpeg',
		'png',
		'webp',
		'gif',
		'avif',
		'mp4',
		'mov',
		'webm',
		'mp3',
		'json',
		'lottie',
		'txt',
	);

	/**
	 * List premium full-site templates (isPro in the official catalog).
	 *
	 * @return array
	 */
	public static function list_premium() {
		$data  = self::fetch_json( self::ASSETS_BASE . '/templates/templates.json' );
		$items = array();
		if ( is_array( $data ) ) {
			foreach ( $data as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$items[] = self::public_template_row( $row, 'premium' );
			}
		}
		return array(
			'templates'           => $items,
			'count'               => count( $items ),
			'catalog'             => self::ASSETS_BASE . '/templates/templates.json',
			'kirki_pro_installed' => Compatibility::pro_plugin_installed(),
			'kirki_pro_licensed'  => Compatibility::is_pro_user(),
		);
	}

	/**
	 * List pre-built pages and sections.
	 *
	 * @param string $category Category key, or empty for all.
	 * @return array
	 */
	public static function list_prebuilt( $category = '' ) {
		$out       = array();
		$want_dark = false;
		if ( $category && substr( $category, -5 ) === '-dark' ) {
			$want_dark = true;
			$category  = substr( $category, 0, -5 );
		}
		foreach ( self::PREBUILT as $key => $meta ) {
			if ( '' !== $category && $key !== $category ) {
				continue;
			}
			$file        = $want_dark ? '/all-dark.json' : '/all.json';
			$url         = self::ASSETS_BASE . $meta['base'] . $file;
			$data        = self::fetch_json( $url );
			$out[ $key ] = array(
				'type'     => $meta['type'],
				'catalog'  => $url,
				'elements' => is_array( $data ) ? $data : array(),
			);
		}
		return array(
			'prebuilt'            => $out,
			'kirki_pro_installed' => Compatibility::pro_plugin_installed(),
			'kirki_pro_licensed'  => Compatibility::is_pro_user(),
		);
	}

	/**
	 * Start a site export. Call process_export until status is done.
	 *
	 * @param bool $with_assets Include media.
	 * @param bool $only_published Only published pages.
	 * @return array
	 */
	public static function start_export( $with_assets = true, $only_published = true ) {
		self::assert_export_import();
		$exporter = new \Kirki\ExportImport\TemplateExport();
		$result   = $exporter->export( (bool) $with_assets, (bool) $only_published );
		Audit::record( 'template-export-start', array( 'with_assets' => (bool) $with_assets ) );
		return is_array( $result ) ? $result : array( 'status' => 'init' );
	}

	/**
	 * Advance or finish a site export.
	 *
	 * @param int $max_steps Steps to run in this call.
	 * @return array
	 */
	public static function process_export( $max_steps = 20 ) {
		self::assert_export_import();
		$exporter = new \Kirki\ExportImport\TemplateExport();
		$max      = max( 1, min( 40, (int) $max_steps ) );
		$result   = array();
		for ( $i = 0; $i < $max; $i++ ) {
			$result = $exporter->process();
			if ( ! is_array( $result ) ) {
				break;
			}
			if ( isset( $result['status'] ) && in_array( $result['status'], array( 'done', false ), true ) ) {
				break;
			}
		}
		if ( isset( $result['zip_file_url'] ) ) {
			Audit::record( 'template-export-done', array( 'has_url' => true ) );
		}
		return is_array( $result ) ? $result : array( 'status' => false );
	}

	/**
	 * Export one page as a kirki-data.json zip. Returns a site-local URL.
	 *
	 * @param int $page_id Page id.
	 * @return array
	 */
	public static function export_page( $page_id ) {
		$loaded   = Page_Repository::load( (int) $page_id, false );
		$document = $loaded['document'];
		$payload  = array(
			'blocks' => $document->blocks,
			'styles' => $document->styles,
		);
		if ( ! class_exists( '\ZipArchive' ) ) {
			throw new \RuntimeException( 'ZipArchive is required to export a page.' );
		}
		$uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : array();
		if ( empty( $uploads['basedir'] ) ) {
			throw new \RuntimeException( 'Uploads directory is not available.' );
		}
		$dir = $uploads['basedir'] . '/kirki-mcp';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new \RuntimeException( 'Could not create the export folder.' );
		}
		$name = 'kirki-page-' . (int) $page_id . '-' . gmdate( 'YmdHis' ) . '.zip';
		$path = $dir . '/' . $name;
		$zip  = new \ZipArchive();
		if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			throw new \RuntimeException( 'Could not create the export zip.' );
		}
		$zip->addFromString( 'kirki-data.json', wp_json_encode( $payload ) );
		$zip->close();
		$url = trailingslashit( $uploads['baseurl'] ) . 'kirki-mcp/' . $name;
		Audit::record( 'template-export-page', array( 'page_id' => (int) $page_id ) );
		return array(
			'page_id' => (int) $page_id,
			'path'    => $path,
			'url'     => $url,
			'file'    => $name,
		);
	}

	/**
	 * Inspect a zip without applying it.
	 *
	 * @param string $zip_path Absolute path.
	 * @return array
	 */
	public static function preview_zip( $zip_path ) {
		$zip_path = self::assert_local_zip( $zip_path );
		$kind     = self::zip_kind( $zip_path );
		return array(
			'kind' => $kind,
			'path' => basename( $zip_path ),
		);
	}

	/**
	 * Import a local zip. Site packs go through Kirki TemplateImport.
	 * Page packs are applied to a draft (or to $page_id when confirm is set on a published page).
	 *
	 * @param string $zip_path Path.
	 * @param bool   $confirm Confirm overwrite / site import.
	 * @param string $mode Variable mode.
	 * @param int    $page_id Optional target page.
	 * @return array
	 */
	public static function import_zip( $zip_path, $confirm, $mode = 'default', $page_id = 0 ) {
		if ( ! $confirm ) {
			throw new \InvalidArgumentException( 'Importing a template ZIP requires confirm: true.' );
		}
		$zip_path = self::assert_local_zip( $zip_path );
		$kind     = self::zip_kind( $zip_path );
		if ( 'site' === $kind ) {
			self::assert_export_import();
			$importer = new \Kirki\ExportImport\TemplateImport();
			$result   = $importer->import( $zip_path, false, (string) $mode );
			Audit::record( 'template-import-site', array( 'mode' => (string) $mode ) );
			return is_array( $result ) ? array_merge( array( 'kind' => 'site' ), $result ) : array(
				'kind'   => 'site',
				'status' => (bool) $result,
			);
		}
		if ( 'page' === $kind ) {
			return self::apply_page_zip( $zip_path, $page_id, $confirm );
		}
		throw new \InvalidArgumentException( 'That zip is not a Kirki site pack or a kirki-data.json page template.' );
	}

	/**
	 * Advance a site import queue.
	 *
	 * @param int $max_steps Steps.
	 * @return array
	 */
	public static function process_import( $max_steps = 20 ) {
		self::assert_export_import();
		$importer = new \Kirki\ExportImport\TemplateImport();
		$max      = max( 1, min( 40, (int) $max_steps ) );
		$result   = array();
		for ( $i = 0; $i < $max; $i++ ) {
			$result = $importer->process();
			if ( ! is_array( $result ) ) {
				break;
			}
			if ( ! empty( $result['status'] ) && 'importing' !== $result['status'] ) {
				break;
			}
			if ( isset( $result['status'] ) && true === $result['status'] && empty( $result['queue'] ) ) {
				break;
			}
		}
		Audit::record( 'template-import-process', array( 'done' => isset( $result['status'] ) ? $result['status'] : '' ) );
		return is_array( $result ) ? $result : array( 'status' => false );
	}

	/**
	 * Apply a catalog item (premium site zip or pre-built page/section zip) to a draft, or to page_id.
	 *
	 * @param string $source premium or a prebuilt category.
	 * @param string $id Catalog id.
	 * @param bool   $confirm Confirm.
	 * @param int    $page_id Optional page.
	 * @param string $mode Mode.
	 * @return array
	 */
	public static function apply_catalog_item( $source, $id, $confirm, $page_id = 0, $mode = 'default' ) {
		if ( ! $confirm ) {
			throw new \InvalidArgumentException( 'Applying a catalog template requires confirm: true.' );
		}
		$id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id );
		if ( '' === $id ) {
			throw new \InvalidArgumentException( 'A catalog item id is required.' );
		}
		if ( 'premium' === $source ) {
			$url  = self::ASSETS_BASE . '/' . $id . '.zip';
			$path = self::download_catalog_zip( $url );
			try {
				return self::import_zip( $path, true, $mode, $page_id );
			} finally {
				if ( $path && file_exists( $path ) ) {
					wp_delete_file( $path );
				}
			}
		}
		if ( ! isset( self::PREBUILT[ $source ] ) ) {
			throw new \InvalidArgumentException( 'Unknown catalog source.' );
		}
		$base = self::PREBUILT[ $source ]['base'];
		$url  = self::ASSETS_BASE . $base . '/' . $id . '.zip';
		$path = self::download_catalog_zip( $url );
		try {
			return self::import_zip( $path, true, $mode, $page_id );
		} finally {
			if ( $path && file_exists( $path ) ) {
				wp_delete_file( $path );
			}
		}
	}

	/**
	 * @param string $zip_path Zip.
	 * @param int    $page_id Page.
	 * @param bool   $confirm Confirm published overwrite.
	 * @return array
	 */
	private static function apply_page_zip( $zip_path, $page_id, $confirm ) {
		$json = self::read_page_json( $zip_path );
		if ( $page_id ) {
			$post = get_post( (int) $page_id );
			if ( ! $post ) {
				throw new \InvalidArgumentException( 'Page not found.' );
			}
			if ( 'publish' === $post->post_status && ! $confirm ) {
				throw new \InvalidArgumentException( 'Overwriting a published page requires confirm: true. Omit page_id to create a draft instead.' );
			}
		} else {
			$page_id = Page_Repository::create( 'Imported template', '', 'draft' );
		}
		$blocks   = isset( $json['blocks'] ) ? $json['blocks'] : $json;
		$styles   = isset( $json['styles'] ) ? $json['styles'] : array();
		$document = Document::from_stored( is_array( $blocks ) ? $blocks : array(), is_array( $styles ) ? $styles : array() );
		Page_Repository::save( (int) $page_id, $document, null );
		Audit::record( 'template-import-page', array( 'page_id' => (int) $page_id ) );
		return array(
			'kind'    => 'page',
			'page_id' => (int) $page_id,
			'result'  => Page_Repository::present( (int) $page_id ),
		);
	}

	/**
	 * @param string $zip_path Path.
	 * @return array
	 */
	private static function read_page_json( $zip_path ) {
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			throw new \InvalidArgumentException( 'Could not open the zip.' );
		}
		$json = $zip->getFromName( 'kirki-data.json' );
		$zip->close();
		if ( ! is_string( $json ) || '' === $json ) {
			throw new \InvalidArgumentException( 'The zip has no kirki-data.json file.' );
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'kirki-data.json is not valid JSON.' );
		}
		return $data;
	}

	/**
	 * @param string $zip_path Path.
	 * @return string site|page|unknown
	 */
	public static function zip_kind( $zip_path ) {
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			throw new \InvalidArgumentException( 'Could not open the zip.' );
		}
		$has_site = false !== $zip->locateName( 'site-settings.json' ) && false !== $zip->locateName( 'pages.json' );
		$has_page = false !== $zip->locateName( 'kirki-data.json' );
		$zip->close();
		if ( $has_site ) {
			return 'site';
		}
		if ( $has_page ) {
			return 'page';
		}
		return 'unknown';
	}

	/**
	 * @param string $path Path.
	 * @return string
	 */
	public static function assert_local_zip( $path ) {
		$path = (string) $path;
		if ( '' === $path || ! preg_match( '/\.zip$/i', $path ) ) {
			throw new \InvalidArgumentException( 'A .zip path is required.' );
		}
		if ( preg_match( '#\.\.|phar://|php://#i', $path ) ) {
			throw new \InvalidArgumentException( 'That zip path is not allowed.' );
		}
		if ( ! is_file( $path ) ) {
			throw new \InvalidArgumentException( 'The zip file was not found.' );
		}
		return $path;
	}

	/**
	 * Download a catalog zip. A stored license is used only if the first request fails.
	 * The key is never returned.
	 *
	 * @param string $url URL.
	 * @return string Local path.
	 */
	public static function download_catalog_zip( $url ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$url = self::safe_catalog_url( $url );
		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			$licensed = self::catalog_url_with_license( $url );
			if ( $licensed !== $url ) {
				$tmp = download_url( $licensed, 60 );
			}
		}
		if ( is_wp_error( $tmp ) ) {
			throw new \RuntimeException( 'Could not download the catalog template. A valid Kirki Pro license may be required for this file.' );
		}
		return $tmp;
	}

	/**
	 * @param string $url URL.
	 * @return string
	 */
	private static function safe_catalog_url( $url ) {
		$url = (string) $url;
		if ( 0 !== strpos( $url, self::ASSETS_BASE . '/' ) ) {
			throw new \InvalidArgumentException( 'Catalog downloads are limited to the official Kirki assets host.' );
		}
		if ( ! preg_match( '/\.zip(\?.*)?$/i', $url ) && ! preg_match( '/\.json(\?.*)?$/i', $url ) ) {
			throw new \InvalidArgumentException( 'Catalog URLs must point at a json or zip file.' );
		}
		return $url;
	}

	/**
	 * Append a stored license for official CDN requests without exposing it.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function catalog_url_with_license( $url ) {
		$key = self::stored_license_key();
		if ( '' === $key ) {
			return $url;
		}
		$host = function_exists( 'wp_parse_url' ) && function_exists( 'home_url' ) ? wp_parse_url( home_url(), PHP_URL_HOST ) : '';
		return add_query_arg(
			array(
				'license_key' => $key,
				'host'        => is_string( $host ) ? $host : '',
			),
			$url
		);
	}

	/**
	 * Read the license from Kirki common data in memory. Never returned to callers.
	 *
	 * @return string
	 */
	private static function stored_license_key() {
		if ( ! function_exists( 'get_option' ) || ! defined( 'KIRKI_WP_ADMIN_COMMON_DATA' ) ) {
			return '';
		}
		$data = get_option( KIRKI_WP_ADMIN_COMMON_DATA, array() );
		if ( is_array( $data ) && isset( $data['license_key']['key'] ) && is_string( $data['license_key']['key'] ) ) {
			return $data['license_key']['key'];
		}
		return '';
	}

	/**
	 * @param string $url URL.
	 * @return array|null
	 */
	public static function fetch_json( $url ) {
		$url = self::safe_catalog_url( $url );
		if ( ! function_exists( 'wp_remote_get' ) ) {
			$raw = @file_get_contents( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- Unit/offline fallback.
			if ( ! is_string( $raw ) ) {
				return null;
			}
			$data = json_decode( $raw, true );
			return is_array( $data ) ? $data : null;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		$body     = '';
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$body = (string) wp_remote_retrieve_body( $response );
		}
		if ( '' === $body ) {
			$body = self::fallback_http_get( $url );
		}
		if ( '' === $body ) {
			return null;
		}
		$data = json_decode( $body, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * @param array  $row Row.
	 * @param string $kind Kind.
	 * @return array
	 */
	private static function public_template_row( array $row, $kind ) {
		return array(
			'id'         => isset( $row['id'] ) ? (string) $row['id'] : '',
			'title'      => isset( $row['title'] ) ? (string) $row['title'] : '',
			'is_pro'     => ! empty( $row['isPro'] ),
			'src'        => isset( $row['src'] ) ? (string) $row['src'] : '',
			'thumbnail'  => isset( $row['thumbnail'] ) ? (string) $row['thumbnail'] : '',
			'preview'    => isset( $row['preview'] ) ? (string) $row['preview'] : '',
			'total_page' => isset( $row['totalPage'] ) ? (int) $row['totalPage'] : 0,
			'min_kirki'  => isset( $row['minKirkiVersion'] ) ? (string) $row['minKirkiVersion'] : '',
			'categories' => isset( $row['categories'] ) && is_array( $row['categories'] ) ? $row['categories'] : array(),
			'kind'       => $kind,
		);
	}

	/**
	 * @return void
	 */
	private static function assert_export_import() {
		if ( ! class_exists( '\Kirki\ExportImport\TemplateExport' ) || ! class_exists( '\Kirki\ExportImport\TemplateImport' ) ) {
			throw new \RuntimeException( 'Kirki template export/import classes are not available. Activate Kirki 6.4.x.' );
		}
	}
	/**
	 * Fetch a catalog URL when wp_remote_get cannot complete the TLS session.
	 *
	 * @param string $url URL already validated by safe_catalog_url.
	 * @return string
	 */
	private static function fallback_http_get( $url ) {
		if ( function_exists( 'file_get_contents' ) ) {
			$ctx = stream_context_create(
				array(
					'http' => array(
						'timeout' => 20,
						'header'  => "Accept: application/json\r\n",
					),
				)
			);
			$raw = @file_get_contents( $url, false, $ctx ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- Fallback when wp_remote_get fails mid-TLS.
			if ( is_string( $raw ) && '' !== $raw ) {
				return $raw;
			}
		}
		if ( function_exists( 'curl_init' ) ) {
			$curl = curl_init( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- Fallback when wp_remote_get fails mid-TLS.
			if ( $curl ) {
				curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
				curl_setopt( $curl, CURLOPT_TIMEOUT, 20 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
				curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Accept: application/json' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
				$raw = curl_exec( $curl ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
				curl_close( $curl ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
				if ( is_string( $raw ) && '' !== $raw ) {
					return $raw;
				}
			}
		}
		return '';
	}
}
