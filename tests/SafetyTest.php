<?php
/**
 * Guards against unserializing agent input and against Kirki's publish-only template writer.
 *
 * @package KirkiMCP
 */

use KirkiMCP\Json_Data;
use PHPUnit\Framework\TestCase;

/**
 * Safety checks.
 */
class SafetyTest extends TestCase {

	/**
	 * Nested PHP objects are rejected.
	 */
	public function test_json_guard_rejects_objects() {
		$this->expectException( InvalidArgumentException::class );
		Json_Data::only_array(
			array(
				'data' => array(
					'root' => new stdClass(),
				),
			),
			'Symbol'
		);
	}

	/**
	 * A plain JSON-shaped array is accepted.
	 */
	public function test_json_guard_accepts_arrays() {
		$value = Json_Data::only_array(
			array(
				'root'  => 'root',
				'setAs' => 'header',
				'data'  => array(
					'root' => array(
						'id' => 'root',
					),
				),
			),
			'Symbol'
		);
		$this->assertSame( 'header', $value['setAs'] );
	}

	/**
	 * The plugin source must not unserialize input or call Kirki's template saver.
	 */
	public function test_plugin_source_avoids_unsafe_kirki_entry_points() {
		$files = glob( dirname( __DIR__ ) . '/includes/*.php' );
		$files[] = dirname( __DIR__ ) . '/kirki-mcp.php';
		foreach ( $files as $file ) {
			$code = file_get_contents( $file );
			$code = preg_replace( '!/\*.*?\*/!s', '', $code );
			$code = preg_replace( '/^\s*\/\/.*$/m', '', $code );
			$this->assertStringNotContainsString( 'unserialize(', $code, $file );
			$this->assertStringNotContainsString( 'maybe_unserialize(', $code, $file );
			$this->assertStringNotContainsString( 'save_template_data(', $code, $file );
			$this->assertStringNotContainsString( 'admin-ajax.php', $code, $file );
		}
	}
}
