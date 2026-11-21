# Kirki MCP

Copyright (C) 2026 S M Saad Uddin. This plugin is free software licensed under GPL-2.0-or-later.

Kirki MCP is a WordPress plugin that lets any MCP-capable agent build and edit sites made with the [Kirki](https://kirki.com) visual builder, including advertised Kirki Pro surfaces that already have a free data format, WordPress Customizer values, and — when it is installed — the standalone [Kirki eCommerce](https://wordpress.org/plugins/kirki-ecommerce/) plugin. It is free and licensed GPL-2.0-or-later.

The plugin does not modify Kirki. It registers WordPress abilities, and the official [MCP Adapter](https://github.com/WordPress/mcp-adapter) exposes those abilities to Claude Code, Cursor, VS Code, Windsurf, and any other MCP client. Copy-paste setup for each verified client is in [docs/CLIENTS.md](docs/CLIENTS.md) and [examples/clients/](examples/clients/). The feature coverage matrix is [docs/GAPS.md](docs/GAPS.md).

Pages are stored in the same post meta Kirki's editor writes (`kirki` for the element map, `kirki_global_style_block_random` for CSS style blocks), so a page created through MCP opens in the Kirki editor.

## Requirements

- WordPress 6.9 or newer
- PHP 7.4 or newer
- The [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) plugin
- Kirki 6.4.x for visual-builder abilities (tested with 6.4.2; 6.5 and 7 are not supported)
- Optional: Kirki Pro (detected at runtime; this plugin never returns the license key)
- Optional: [Kirki eCommerce](https://wordpress.org/plugins/kirki-ecommerce/) 1.0.x (standalone; the Kirki builder is not required)

## Install on a live site

1. Install and activate **MCP Adapter** from Plugins → Add New. Install **Kirki** 6.4.x for builder features. Optionally install **Kirki eCommerce**.
2. Install this plugin. Either upload the release zip from `bin/build-zip.sh`, or copy this directory to `wp-content/plugins/kirki-mcp` and activate **Kirki MCP**.
3. In WordPress, open **Users → Profile**, scroll to **Application Passwords**, and create a password for your agent. Builder work needs Kirki builder access, which Kirki gives to administrators and editors. `edit_pages` is required to design pages and `publish_pages` to publish. Customizer abilities need `edit_theme_options`. Kirki eCommerce abilities need `manage_options`.
4. Confirm the endpoint on **Settings → Kirki MCP**. It is:

```text
https://example.com/wp-json/mcp/mcp-adapter-default-server
```

## Connect an agent

The adapter's default server has three meta-tools. Agents discover Kirki operations and run them by name:

| MCP tool | What to pass |
| --- | --- |
| `mcp-adapter-discover-abilities` | Lists abilities, including every `kirki-mcp/…` ability |
| `mcp-adapter-get-ability-info` | `ability_name`: `kirki-mcp/create-page` |
| `mcp-adapter-execute-ability` | `ability_name` plus `parameters` |

Read `kirki-mcp/get-site-context` and `kirki-mcp/get-element-schema` before writing. Build pages with a small section spec, not raw Kirki JSON.

Ready-to-copy configuration for each verified client lives in [`examples/clients/`](examples/clients/). Full notes, official doc links, and transport differences are in [`docs/CLIENTS.md`](docs/CLIENTS.md).

Placeholders are `YOUR_SITE_URL`, `YOUR_USERNAME`, `YOUR_APP_PASSWORD`, and `YOUR_BASIC_CREDENTIAL` (Base64 of `YOUR_USERNAME:YOUR_APP_PASSWORD`). Do not commit a real password.

| Client | Config | Official docs |
| --- | --- | --- |
| Claude Code | `examples/clients/claude-code.mcp.json` | [MCP in Claude Code](https://docs.anthropic.com/en/docs/claude-code/mcp) |
| Claude Desktop | `examples/clients/claude-desktop.json` | [Custom connectors](https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp) |
| Cursor | `examples/clients/cursor.mcp.json` | [Cursor MCP](https://cursor.com/docs/context/mcp) |
| VS Code (Copilot agent mode) | `examples/clients/vscode.mcp.json` | [VS Code MCP](https://code.visualstudio.com/docs/copilot/customization/mcp-servers) |
| Windsurf | `examples/clients/windsurf.mcp_config.json` | [Windsurf MCP](https://docs.windsurf.com/windsurf/cascade/mcp) |
| Google Antigravity | `examples/clients/antigravity.mcp_config.json` | [Antigravity](https://antigravity.google/docs) |
| Kilo Code | `examples/clients/kilo.kilo.jsonc` | [Kilo Code MCP](https://kilo.ai/docs/agent/mcp) |
| Cline | `examples/clients/cline_mcp_settings.json` | [Cline MCP](https://docs.cline.bot/mcp/configuring-mcp-servers) |
| Roo Code | `examples/clients/roo.mcp.json` | [Roo Code MCP](https://docs.roocode.com/features/mcp/using-mcp-in-roo) |
| OpenCode | `examples/clients/opencode.json` | [OpenCode MCP](https://opencode.ai/docs/mcp) |
| Continue | `examples/clients/continue.mcp.json` | [Continue MCP](https://docs.continue.dev/customize/deep-dives/mcp) |
| Zed | `examples/clients/zed-settings.json` | [Zed MCP](https://zed.dev/docs/ai/mcp) |
| JetBrains AI Assistant / Junie | `examples/clients/jetbrains-ai-assistant.json` | [JetBrains AI Assistant MCP](https://www.jetbrains.com/help/ai-assistant/mcp.html) |
| Gemini CLI | `examples/clients/gemini-settings.json` | [Gemini CLI MCP](https://github.com/google-gemini/gemini-cli/blob/main/docs/tools/mcp-server.md) |
| OpenAI Codex CLI | `examples/clients/codex.config.toml` | [Codex MCP](https://developers.openai.com/codex/mcp/) |
| Warp | `examples/clients/warp.mcp.json` | [Warp MCP](https://docs.warp.dev/knowledge-and-collaboration/mcp) |
| LM Studio | `examples/clients/lmstudio.mcp.json` | [LM Studio MCP](https://lmstudio.ai/docs/app/plugins/mcp) |
| Augment | `examples/clients/augment.mcp.json` | [Augment MCP](https://docs.augmentcode.com/setup-augment/mcp) |

Amazon Q Developer, Trae, and Goose are not given a guessed config in this release; see `docs/CLIENTS.md`.

### VS Code official installer

```bash
code --add-mcp '{"name":"kirki","type":"http","url":"https://YOUR_SITE_URL/wp-json/mcp/mcp-adapter-default-server","headers":{"Authorization":"Basic YOUR_BASIC_CREDENTIAL"}}'
```

### Stdio-only clients

If a client can only start a local process, use [`@automattic/mcp-wordpress-remote`](https://github.com/Automattic/mcp-wordpress-remote). See `examples/clients/stdio-mcp-wordpress-remote.json`.

## Abilities

| Ability | What it does |
| --- | --- |
| `kirki-mcp/get-site-context` | Viewports, variables, classes, symbols, plus `kirki_pro` and `kirki_ecommerce` status |
| `kirki-mcp/get-element-schema` | Spec the compiler accepts |
| `kirki-mcp/list-pages` | Pages, Kirki pages by default |
| `kirki-mcp/get-page` | Compact tree, styles, editor URL |
| `kirki-mcp/create-page` | Draft or published page from sections |
| `kirki-mcp/update-page` | Title and slug |
| `kirki-mcp/update-page-settings` | SEO title and meta description |
| `kirki-mcp/update-custom-code` | Head and body code Kirki prints on one page |
| `kirki-mcp/add-section` | Insert a section |
| `kirki-mcp/add-element` | Insert an element under a parent |
| `kirki-mcp/update-element` | Text, tag, link, image |
| `kirki-mcp/update-styles` | Replace CSS variants on an element |
| `kirki-mcp/remove-element` | Delete an element. Requires `confirm: true` |
| `kirki-mcp/duplicate-page` | Copy a page to a new draft |
| `kirki-mcp/publish-page` | Publish, and optionally set the front page |
| `kirki-mcp/trash-page` | Trash a page. Requires `confirm: true` |
| `kirki-mcp/list-global-classes` | Global classes |
| `kirki-mcp/upsert-global-class` | Create or update a global class |
| `kirki-mcp/list-global-variables` | Colors, sizes, fonts |
| `kirki-mcp/set-global-variables` | Upsert colors, font stacks, sizes, and text styles |
| `kirki-mcp/register-font` | Load a Google Fonts stylesheet |
| `kirki-mcp/upload-media` | Image upload through `POST /wp/v2/media` |
| `kirki-mcp/list-symbols` | Symbols, including header and footer |
| `kirki-mcp/get-symbol` | One symbol's tree |
| `kirki-mcp/create-symbol` | Header, footer, or component |
| `kirki-mcp/update-symbol` | Rename, reassign the role, or replace sections |
| `kirki-mcp/list-templates` | `kirki_template` posts |
| `kirki-mcp/list-popups` | `kirki_popup` posts |
| `kirki-mcp/get-popup` | One popup |
| `kirki-mcp/create-popup` | Popup from the same section spec as pages |
| `kirki-mcp/update-popup` | Popup title and sections |
| `kirki-mcp/trash-popup` | Trash a popup. Requires `confirm: true` |
| `kirki-mcp/content-manager-get` | Allowlisted Kirki Content Manager GET |
| `kirki-mcp/content-manager-mutate` | Allowlisted Content Manager POST/DELETE. DELETE requires `confirm: true` |
| `kirki-mcp/list-theme-mods` | Current theme mods |
| `kirki-mcp/get-theme-mod` | One theme_mod |
| `kirki-mcp/set-theme-mod` | Write a theme_mod |
| `kirki-mcp/get-option` | A registered option (secrets blocked) |
| `kirki-mcp/set-option` | Write a registered option (secrets and unknown new keys blocked) |
| `kirki-mcp/list-customizer-fields` | Fields registered in PHP by Kirki or a theme |
| `kirki-mcp/list-changesets` | `customize_changeset` posts |
| `kirki-mcp/publish-changeset` | Publish a changeset. Requires `confirm: true` |
| `kirki-mcp/ecommerce-status` | Whether Kirki eCommerce is installed |
| `kirki-mcp/ecommerce-get` | Allowlisted kirki/ecommerce/v1 GET |
| `kirki-mcp/ecommerce-mutate` | Allowlisted eCommerce POST/PUT/PATCH/DELETE. DELETE requires `confirm: true` |

The compiler accepts `video`, `slider`, and `tabs` in addition to the 0.2.x element types. See `get-element-schema`. The coverage matrix is [docs/GAPS.md](docs/GAPS.md).

A section looks like this:

```json
{
  "title": "Northwind",
  "sections": [
    {
      "key": "hero",
      "styles": { "md": "padding:80px 8vw;background:#0f172a;color:#f8fafc;" },
      "elements": [
        { "type": "heading", "tag": "h1", "text": "Design without a grid" },
        { "type": "paragraph", "text": "Built through MCP, edited in Kirki." },
        { "type": "button", "text": "Start a project", "link_key": "contact" }
      ]
    },
    { "key": "contact", "elements": [ { "type": "heading", "tag": "h2", "text": "Contact" } ] }
  ]
}
```

CSS variants are `md` (desktop), `tablet`, and `mobile`, plus `_hover` and `_active`. Global colors are referenced as `var(--VARIABLE_ID)`.

## Kirki routes this plugin does not call

Checked against Kirki 6.4.2:

- Creating and editing symbols is not in `/wp-json/kirki/v1`. Kirki still does that through `admin-ajax.php` (`save-user-saved-symbol-data` and `update-user-saved-symbol-data`), and that handler requires a `wp_rest` nonce. An application password does not send one. New symbols go through Kirki's `SymbolManager`. Updates are written by this plugin as JSON arrays, because `SymbolManager` always inserts a new symbol.
- Kirki's media route is `GET /wp-json/kirki/v1/media` only. Uploads are the ajax action `upload-media`, which has the same nonce requirement. `kirki-mcp/upload-media` stores the file with WordPress core `POST /wp/v2/media`. You can also post to that endpoint directly with the same application password, then pass the attachment id to an image element.
- `Template::save_template_data()` saves with staging left off, so it would overwrite published meta. This plugin does not call it.
- Edits to a page that is already published are stored on a new unpublished Kirki stage. The live page stays as it was until `publish-page`. That ability copies the stage onto published post meta and then asks Kirki to record the version. Kirki's own publish helper only fills empty published keys, so copying first is required. Drafts are written to post meta because they are not public yet.
- A new header or footer is validated and saved before the previous symbol loses that role.
- Agent input is JSON. The plugin does not call `unserialize()` or `maybe_unserialize()`. If post meta comes back as a PHP object, it is refused. WordPress has already unserialized its own meta storage by then; this is a guard against using that value, not a way to stop WordPress reading its database.

Application passwords are a WordPress core feature. On a live site they require HTTPS. A local environment (`WP_ENVIRONMENT_TYPE=local`) can use them over HTTP.

## How writes stay safe

- Every ability checks a real WordPress capability. Anonymous requests cannot call them.
- Text is stored as plain text. Kirki escapes it when rendering.
- CSS is rejected when it contains markup, `expression()`, `javascript:`, or `@import`.
- Section titles and symbol names are limited to letters, numbers, spaces, dots, underscores, and hyphens. Kirki prints a section title as `id="…"` with no escaping.
- Kirki prints a custom font's `fontUrl`, or its `localUrl` when one is set, into a stylesheet link without escaping. New URLs are checked before they are stored. URLs already stored, including ones saved by Kirki's own font screen, are passed through `esc_url_raw` on the way out, via the `get_post_metadata` filter, before Kirki prints them. `audit-unsafe-titles` lists section titles and symbol names that were stored before that character limit and have not been saved again.
- Updating a global class through this plugin refreshes the copy stored on symbols that use it, 20 symbols at a time, header and footer first. The rest continue on a single scheduled follow-up. Kirki's own class screen writes through its database layer and does not notify the plugin, so those edits are copied the next time `upsert-global-class` or `reapply-global-classes` runs. `reapply-global-classes` with `symbol_id` updates that one symbol and also strips default local CSS.
- `remove-element`, `trash-page`, `trash-popup`, Content Manager and eCommerce DELETE, and `publish-changeset` refuse to run unless `confirm` is `true`.
- Edits to a published page stay on an unpublished Kirki stage until `publish-page`. Drafts are saved in post meta. `writing_to` on each page response says which of the two happened.

## Development

```bash
php phpunit.phar --configuration phpunit.xml.dist
bash bin/build-zip.sh
KIRKI_MCP_STAGING_CHECK=1 wp eval-file tests/e2e/staging-check.php
```

`tests/e2e/staging-check.php` is for a test install. It refuses to run unless `KIRKI_MCP_STAGING_CHECK` is set. It publishes a page, edits it, publishes again, and temporarily takes the header role, then deletes that page and restores the previous header. The zip is written to `dist/kirki-mcp-0.3.0.zip`.

## Known gaps

The full before/after matrix is [docs/GAPS.md](docs/GAPS.md). Remaining gaps in 0.3.0:

- Template ZIP import is not implemented. Existing `kirki_template` posts can be listed. Import is skipped because Kirki can overwrite published meta.
- New Customizer panels, sections, and controls cannot be registered at runtime; only stored values and already-registered fields are available.
- Advertised Kirki Pro dynamic content, the full interaction timeline, and premium template packs are not written. Pro is detected (`kirki_pro`); the license key is never returned.
- Kirki eCommerce payment webhooks, gateway installs, credential writes, checkout, and password changes are intentionally not exposed.
- Supported builder range is Kirki `>= 6.4.0` and `< 6.5.0`, tested against 6.4.2. `kirki_mcp_supported_kirki_range` can widen it after you have checked that build. The stored JSON is not a public schema.
