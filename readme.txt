=== Kirki MCP ===
Contributors: saaduddinsony
Tags: kirki, mcp, ai, page-builder, abilities
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets MCP-capable AI agents build and edit Kirki sites, Customizer values, and Kirki eCommerce stores.

== Description ==

Kirki MCP is a companion to the free Kirki 6.4 builder, optional Kirki Pro, and the standalone Kirki eCommerce plugin. It registers WordPress abilities for pages, sections, elements (including video, sliders, and tabs), popups, Content Manager collections, Customizer theme mods, and, when kirki-ecommerce is active, store catalog, orders, coupons, shipping, and WooCommerce migration. The official MCP Adapter plugin exposes those abilities to Claude Code, Cursor, VS Code, Windsurf, and other MCP clients. Setup files live in docs/CLIENTS.md and examples/clients/.

The plugin does not modify Kirki. Pages are saved in the post meta Kirki's editor already uses, so a page built through MCP opens and edits in the Kirki canvas.

Requires:

* WordPress 6.9 or newer (the Abilities API)
* The MCP Adapter plugin
* Kirki 6.4.x for visual-builder abilities (tested with 6.4.2)
* Optional: Kirki Pro (detected at runtime; the license key is never exposed)
* Optional: Kirki eCommerce 1.0.x (detected at runtime; the builder is not required)

Connect an agent with an application password to:

`https://example.com/wp-json/mcp/mcp-adapter-default-server`

See docs/CLIENTS.md and examples/clients/ for current MCP client configuration. See docs/GAPS.md for the feature coverage matrix.

Destructive actions (`remove-element`, `trash-page`) require an explicit `confirm` flag. Global style changes require Kirki builder access, which administrators and editors have.

== Installation ==

1. Install and activate MCP Adapter. Install Kirki 6.4.x for builder features, and optionally Kirki eCommerce.
2. Upload the Kirki MCP zip and activate it.
3. Create an application password under Users → Profile.
4. Point your MCP client at the endpoint shown under Settings → Kirki MCP.

== Frequently Asked Questions ==

= Does this replace Kirki? =

No. Kirki renders the pages. This plugin writes the design data Kirki already understands.

= Which Kirki versions are supported? =

Kirki 6.4.x only. It was tested with 6.4.3. Kirki 6.5 and 7 are not supported. Forms, navigation, lists, and inline links can be generated.

= Can an agent publish a page? =

Yes, with `kirki-mcp/publish-page`, and only when the user can publish pages.

== Changelog ==

= 0.3.0 =
* Client setup for Claude Code, Claude Desktop, Cursor, VS Code, Windsurf, Antigravity, Kilo Code, Cline, Roo Code, OpenCode, Continue, Zed, JetBrains AI Assistant, Gemini CLI, Codex CLI, Warp, LM Studio, and Augment.
* Kirki Pro is detected at runtime. The license key is never returned.
* New builder types: video, slider, tabs. Popup and Content Manager abilities.
* Theme mod, option, Customizer field, and changeset abilities. Secret option keys are blocked.
* Kirki eCommerce abilities with runtime detection. Catalog, orders, coupons, shipping, tax, settings, analytics, and WooCommerce migration are allowlisted. Payment webhooks, gateway installs, and checkout are not.
* Coverage matrix: docs/GAPS.md.

= 0.2.9 =
* Old titles that contain quotes are stripped in the database, in stages, and on read. Unicode and ordinary spaces stay.
* audit-unsafe-titles reports headings and link targets as well as sections.

= 0.2.8 =
* Stored link fixes are written back from the raw meta, including unpublished stages.
* Symbols this plugin built before 0.2.6 get a unique root element id. Symbols built in Kirki's editor are left alone.
* Reading a page no longer rewrites hand-written anchors or non-Latin titles.

= 0.2.7 =
* Link URLs cannot break out of a single-quoted href. Section links only target sections or anchored elements. Form names are safe labels.
* Each symbol root element has its own id, so a header and footer no longer share one HTML id.
* Symbol migration runs on activation, upgrade, or an authorized write, and skips symbols this plugin did not create.
* Duplicated pages are inserted as drafts. A class update refreshes symbols in batches of 20.

= 0.2.6 =
* Duplicated pages are drafts. Optional title and slug are sanitized, and the slug is made unique.
* Global classes on navigation items and inline links are applied. Unknown classes are rejected.
* reapply-global-classes accepts symbol_id without page_id.
* Each symbol root gets its own HTML id, so a header and footer cannot both render the same id.

= 0.2.5 =
* Select and radio option values cannot contain quotes or markup. Labels are stored as HTML entities.
* Radio fields keep required. Field ids are unique per form.
* Updating a global class refreshes the copy stored on symbols that use it.

= 0.2.4 =
* After publish, an older Kirki revision is no longer treated as the open stage.
* Navigation stays visible at every width instead of starting in Kirki's hidden hamburger state.
* Symbols store the global classes they use. reapply-global-classes accepts a symbol id.
* Forms can show a success and error message, and fields can be select, checkbox, radio, or url.

= 0.2.3 =
* Stored font localUrl values are escaped as well as fontUrl, and &#038; is decoded back to &.
* Saving a page or symbol slugs section titles that were stored earlier. audit-unsafe-titles lists any that remain.
* Explicit anchors no longer collide with ids Kirki numbers from repeated titles, such as card-1.

= 0.2.2 =
* Section titles and symbol names can no longer break out of Kirki's HTML id attribute.
* Repeated section titles are allowed. Explicit anchors are compared after slugging, so "Our Work" and our-work collide.
* Head code rejects markup that follows a style block, allows a CSS child combinator, and allows a media attribute on style.

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
