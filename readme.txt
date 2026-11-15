=== Kirki MCP ===
Contributors: saaduddinsony
Tags: kirki, mcp, ai, page-builder, abilities
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets MCP-capable AI agents build and edit sites made with the Kirki visual builder.

== Description ==

Kirki MCP is a companion to the free Kirki 6 builder. It registers WordPress abilities for pages, sections, elements, global classes, colors, fonts, and header and footer symbols. The official MCP Adapter plugin exposes those abilities to Claude Code, Cursor, Claude Desktop, and any other MCP client.

The plugin does not modify Kirki. Pages are saved in the post meta Kirki's editor already uses, so a page built through MCP opens and edits in the Kirki canvas.

Requires:

* WordPress 6.9 or newer (the Abilities API)
* Kirki 6.x
* The MCP Adapter plugin

Connect an agent with an application password to:

`https://example.com/wp-json/mcp/mcp-adapter-default-server`

See the plugin README for Claude Code, Cursor, and Claude Desktop configuration.

Destructive actions (`remove-element`, `trash-page`) require an explicit `confirm` flag. Global style changes require `edit_theme_options`.

== Installation ==

1. Install and activate Kirki and MCP Adapter.
2. Upload the Kirki MCP zip and activate it.
3. Create an application password under Users → Profile.
4. Point your MCP client at the endpoint shown under Settings → Kirki MCP.

== Frequently Asked Questions ==

= Does this replace Kirki? =

No. Kirki renders the pages. This plugin writes the design data Kirki already understands.

= Which Kirki versions are supported? =

Kirki 6.x. It was tested with 6.4.2. Kirki 7 is not supported yet.

= Can an agent publish a page? =

Yes, with `kirki-mcp/publish-page`, and only when the user can publish pages.

== Changelog ==

= 0.2.1 =
* Global classes style elements, because classed elements no longer keep the default local CSS that was covering them.
* Font variables register a Google Fonts stylesheet through Kirki's custom fonts.
* A section key becomes the HTML id used by anchors.
* Lists, per-viewport hiding, and page custom code.

= 0.2.0 =
* Stage edits on published pages and publish them without discarding the stage.
* Navigation, forms with email delivery, inline links, SEO meta description, web fonts, and text styles.
* Header and footer changes are validated before the previous symbol loses its role.

= 0.1.0 =
* First release. Pages, elements, global classes and variables, header and footer symbols, core media upload, and publish.
* Symbols are created through Kirki's own symbol manager. Kirki 6.4.2 has no REST route for that, and its ajax route needs a browser nonce.
* Does not call Kirki's template saver, which writes published meta immediately.

== Upgrade Notice ==

= 0.1.0 =
First release.
