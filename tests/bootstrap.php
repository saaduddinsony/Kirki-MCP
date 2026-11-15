<?php
/**
 * Loads the pure compiler classes without WordPress.
 *
 * @package KirkiMCP
 */

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/includes/class-css.php';
require dirname( __DIR__ ) . '/includes/class-json-data.php';
require dirname( __DIR__ ) . '/includes/class-ids.php';
require dirname( __DIR__ ) . '/includes/class-spec-compiler.php';
require dirname( __DIR__ ) . '/includes/class-document.php';
