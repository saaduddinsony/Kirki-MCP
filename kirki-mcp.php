<?php
/**
 * Kirki MCP
 *
 * Lets MCP-capable agents build and edit sites made with the Kirki visual
 * builder. Abilities are registered with the WordPress Abilities API and
 * exposed by the official MCP Adapter plugin. This plugin does not modify Kirki.
 *
 * @package           KirkiMCP
 * @author            Kirki MCP Contributors
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Kirki MCP
 * Plugin URI:        https://github.com/saaduddinsony/Kirki-MCP
 * Description:       Build and edit Kirki sites from any MCP-capable AI agent. Requires Kirki 6 and the official WordPress MCP Adapter.
 * Version:           0.2.1
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Requires Plugins:  kirki, mcp-adapter
 * Author:            Kirki MCP Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kirki-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KIRKI_MCP_VERSION', '0.2.1' );
define( 'KIRKI_MCP_FILE', __FILE__ );
define( 'KIRKI_MCP_DIR', plugin_dir_path( __FILE__ ) );

require_once KIRKI_MCP_DIR . 'includes/class-access.php';
require_once KIRKI_MCP_DIR . 'includes/class-compatibility.php';
require_once KIRKI_MCP_DIR . 'includes/class-css.php';
require_once KIRKI_MCP_DIR . 'includes/class-json-data.php';
require_once KIRKI_MCP_DIR . 'includes/class-ids.php';
require_once KIRKI_MCP_DIR . 'includes/class-spec-compiler.php';
require_once KIRKI_MCP_DIR . 'includes/class-document.php';
require_once KIRKI_MCP_DIR . 'includes/class-page-repository.php';
require_once KIRKI_MCP_DIR . 'includes/class-global-repository.php';
require_once KIRKI_MCP_DIR . 'includes/class-symbol-repository.php';
require_once KIRKI_MCP_DIR . 'includes/class-media-repository.php';
require_once KIRKI_MCP_DIR . 'includes/class-abilities.php';
require_once KIRKI_MCP_DIR . 'includes/class-admin.php';
require_once KIRKI_MCP_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'KirkiMCP\\Plugin', 'boot' ) );
