# Kirki MCP

Kirki MCP is a WordPress plugin that lets any MCP-capable agent build and edit sites made with the [Kirki](https://kirki.com) visual builder. It is free and licensed GPL-2.0-or-later.

The plugin does not modify Kirki. It registers WordPress abilities, and the official [MCP Adapter](https://github.com/WordPress/mcp-adapter) exposes those abilities to Claude Code, Cursor, Claude Desktop, and any other MCP client.

Pages are stored in the same post meta Kirki's editor writes (`kirki` for the element map, `kirki_global_style_block_random` for CSS style blocks), so a page created through MCP opens in the Kirki editor.

## Requirements

- WordPress 6.9 or newer
- PHP 7.4 or newer
- Kirki 6.4.x (tested with 6.4.2; 6.5 and 7 are not supported)
- The [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) plugin

## Install on a live site

1. Install and activate **Kirki** and **MCP Adapter** from Plugins → Add New.
2. Install this plugin. Either upload the release zip from `bin/build-zip.sh`, or copy this directory to `wp-content/plugins/kirki-mcp` and activate **Kirki MCP**.
3. In WordPress, open **Users → Profile**, scroll to **Application Passwords**, and create a password for your agent. The user needs Kirki builder access, which Kirki gives to administrators and editors. `edit_pages` is required to design pages and `publish_pages` to publish. Builder access is enough to change global classes and variables.
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

### Claude Code

Claude Code can talk to the endpoint over HTTP. Add this to `.mcp.json` in the project, with a Basic header of `username:application password`:

```json
{
  "mcpServers": {
    "kirki": {
      "type": "http",
      "url": "https://example.com/wp-json/mcp/mcp-adapter-default-server",
      "headers": {
        "Authorization": "Basic BASE64_OF_USERNAME_COLON_APPLICATION_PASSWORD"
      }
    }
  }
}
```

### Cursor

In Cursor, add an MCP server with the same URL and Basic authorization header. If the client only speaks stdio, use the proxy below instead.

### Claude Desktop, and any stdio-only client

Use [`@automattic/mcp-wordpress-remote`](https://github.com/Automattic/mcp-wordpress-remote):

```json
{
  "mcpServers": {
    "kirki": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote"],
      "env": {
        "WP_API_URL": "https://example.com/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "your-application-password"
      }
    }
  }
}
```

## Abilities

| Ability | What it does |
| --- | --- |
| `kirki-mcp/get-site-context` | Viewports, variables, classes, symbols |
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
- `remove-element` and `trash-page` refuse to run unless `confirm` is `true`.
- Edits to a published page stay on an unpublished Kirki stage until `publish-page`. Drafts are saved in post meta. `writing_to` on each page response says which of the two happened.

## Development

```bash
php phpunit.phar --configuration phpunit.xml.dist
bash bin/build-zip.sh
KIRKI_MCP_STAGING_CHECK=1 wp eval-file tests/e2e/staging-check.php
```

`tests/e2e/staging-check.php` is for a test install. It refuses to run unless `KIRKI_MCP_STAGING_CHECK` is set. It publishes a page, edits it, publishes again, and temporarily takes the header role, then deletes that page and restores the previous header. The zip is written to `dist/kirki-mcp-0.2.9.zip`.

## Known gaps

- Content Manager collections and items are not writable.
- Popups, sliders, and interactions are not generated. Forms (with email delivery and a submit button) and navigation (with a mobile menu) are.
- Template ZIP import is not implemented. Existing `kirki_template` posts can be listed.
- Kirki Pro-only behavior was not tested. This plugin targets the free 6.4.x plugin.
- Supported range is Kirki `>= 6.4.0` and `< 6.5.0`, tested against 6.4.2. `kirki_mcp_supported_kirki_range` can widen it after you have checked that build. The stored JSON is not a public schema.
