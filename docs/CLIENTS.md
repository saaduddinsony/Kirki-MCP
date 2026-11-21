# MCP client setup for Kirki MCP

Kirki MCP is a WordPress plugin. It does not start a local MCP server process. The official [WordPress MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) exposes abilities at:

```text
https://YOUR_SITE_URL/wp-json/mcp/mcp-adapter-default-server
```

Authenticate with a WordPress [application password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) over HTTPS. Send HTTP Basic authentication: the username is the WordPress username, the password is the application password (spaces in the password are optional).

Placeholders used in this document and in `examples/clients/`:

| Placeholder | Meaning |
| --- | --- |
| `YOUR_SITE_URL` | Site origin, including `https://`, with no trailing slash. Example: `https://example.com` |
| `YOUR_USERNAME` | WordPress username |
| `YOUR_APP_PASSWORD` | Application password from Users → Profile |
| `YOUR_BASIC_CREDENTIAL` | Base64 encoding of `YOUR_USERNAME:YOUR_APP_PASSWORD` (no spaces around the colon). Used only by clients that want a ready-made `Authorization` header |

Do not commit a real password or a populated Basic header to git.

## Transports

This endpoint is a **Streamable HTTP** MCP server. It is not a stdio process.

- Clients that speak Streamable HTTP (or generic HTTP MCP) should set the URL above and send `Authorization: Basic YOUR_BASIC_CREDENTIAL`.
- Clients that only start a local stdio process should use [`@automattic/mcp-wordpress-remote`](https://github.com/Automattic/mcp-wordpress-remote) (preferred for WordPress) or [`mcp-remote`](https://www.npmjs.com/package/mcp-remote) as a bridge. A ready example is `examples/clients/stdio-mcp-wordpress-remote.json`.

The adapter presents three meta-tools (`mcp-adapter-discover-abilities`, `mcp-adapter-get-ability-info`, `mcp-adapter-execute-ability`). Agents discover `kirki-mcp/…` abilities through those tools. Start with `kirki-mcp/get-site-context`.

One-click install badges are listed only where the vendor documents them. Cursor marketplace one-click is for Cursor marketplace plugins, not a generic remote HTTP URL. The official VS Code installer is `code --add-mcp`, not a verified generic `vscode:mcp/install` deeplink.

## Compatibility

| Client | Config location | Official docs | Ready example | Notes |
| --- | --- | --- | --- | --- |
| Claude Code | Project `.mcp.json` or `~/.claude.json` | [MCP in Claude Code](https://docs.anthropic.com/en/docs/claude-code/mcp) | `examples/clients/claude-code.mcp.json` | Must set `"type": "http"` (or `streamable-http`) plus `url` and `headers`. CLI: `claude mcp add --transport http`. |
| Claude Desktop | macOS `~/Library/Application Support/Claude/claude_desktop_config.json`; Windows `%APPDATA%\Claude\claude_desktop_config.json`; Linux `~/.config/Claude/claude_desktop_config.json` | [Custom connectors (remote MCP)](https://support.claude.com/en/articles/11175166-get-started-with-custom-connectors-using-remote-mcp) | `examples/clients/claude-desktop.json` | Desktop can add a remote connector in the UI on current builds. The stdio fallback is `@automattic/mcp-wordpress-remote`. |
| Cursor | `.cursor/mcp.json` or `~/.cursor/mcp.json` | [Cursor MCP](https://cursor.com/docs/context/mcp) | `examples/clients/cursor.mcp.json` | `mcpServers` with `url` and `headers`. No verified generic HTTP one-click badge. |
| VS Code (GitHub Copilot agent mode) | Workspace `.vscode/mcp.json` (`servers`); portable `.mcp.json` (`mcpServers`); user `~/.copilot/mcp-config.json` | [VS Code MCP](https://code.visualstudio.com/docs/copilot/customization/mcp-servers) | `examples/clients/vscode.mcp.json` | Official install: `code --add-mcp '{...}'`. HTTP servers use `"type": "http"`. |
| Windsurf | `~/.codeium/windsurf/mcp_config.json`; Cascade/`devin` also reads `~/.config/devin/mcp_config.json` (Windows `%AppData%/devin/mcp_config.json`) | [Windsurf MCP](https://docs.windsurf.com/windsurf/cascade/mcp) | `examples/clients/windsurf.mcp_config.json` | Remote servers use `serverUrl` or `url` plus `headers`. |
| Google Antigravity (IDE and agent) | `~/.gemini/config/mcp_config.json` and workspace `.agents/mcp_config.json` | [Antigravity MCP](https://antigravity.google/docs) | `examples/clients/antigravity.mcp_config.json` | `serverUrl` plus `headers`. |
| Kilo Code | `~/.config/kilo/kilo.jsonc`; project `kilo.jsonc` or `.kilo/kilo.jsonc` | [Kilo Code MCP](https://kilo.ai/docs/agent/mcp) | `examples/clients/kilo.kilo.jsonc` | Remote: `"type": "remote"` with `url` and `headers`. |
| Cline | `~/.cline/data/settings/cline_mcp_settings.json` (`CLINE_MCP_SETTINGS_PATH`) | [Cline MCP](https://docs.cline.bot/mcp/configuring-mcp-servers) | `examples/clients/cline_mcp_settings.json` | Set `"type": "streamableHttp"` explicitly; omitting `type` can fall back to legacy SSE. CLI: `cline mcp add`. |
| Roo Code | Global `mcp_settings.json` (from the Roo MCP settings view); project `.roo/mcp.json` | [Roo Code MCP](https://docs.roocode.com/features/mcp/using-mcp-in-roo) | `examples/clients/roo.mcp.json` | Streamable HTTP uses `"type": "streamable-http"` plus `url` and `headers`. |
| OpenCode | `opencode.json` / `opencode.jsonc` | [OpenCode MCP](https://opencode.ai/docs/mcp) | `examples/clients/opencode.json` | Current V2 schema is `"mcp": { "servers": { "name": { "type": "remote", "url": "…", "headers": {…} } } }`. Do not put server names directly under `mcp`. |
| Continue | Workspace `.continue/mcpServers/*.yaml` or a JSON file dropped in that folder | [Continue MCP](https://docs.continue.dev/customize/deep-dives/mcp) | `examples/clients/continue.mcp.json` | Continue also accepts Cursor/Claude-style JSON placed in `.continue/mcpServers/`. |
| Zed | Zed settings, `context_servers` | [Zed MCP](https://zed.dev/docs/ai/mcp) | `examples/clients/zed-settings.json` | Remote servers use `url` and `headers`. |
| JetBrains AI Assistant / Junie | Settings in the IDE; JSON `mcpServers` entries | [JetBrains AI Assistant MCP](https://www.jetbrains.com/help/ai-assistant/mcp.html) | `examples/clients/jetbrains-ai-assistant.json` | Streamable HTTP is the recommended transport. Official examples use `mcpServers` with a `url`. Add the Basic `Authorization` header if the Settings UI or JSON form offers headers. |
| Gemini CLI | `settings.json` → `mcpServers` | [Gemini CLI MCP](https://github.com/google-gemini/gemini-cli/blob/main/docs/tools/mcp-server.md) | `examples/clients/gemini-settings.json` | Streamable HTTP uses `httpUrl` (plain `url` is SSE) plus `headers`. |
| OpenAI Codex CLI | `~/.codex/config.toml` or project `.codex/config.toml` | [Codex MCP](https://developers.openai.com/codex/mcp/) | `examples/clients/codex.config.toml` | `[mcp_servers.name]` with `url` and `http_headers`. |
| Warp | `~/.warp/.mcp.json` or project `.warp/.mcp.json` | [Warp MCP](https://docs.warp.dev/knowledge-and-collaboration/mcp) | `examples/clients/warp.mcp.json` | `mcpServers` with `url` and `headers`. |
| LM Studio | In-app Program tab → Install → Edit `mcp.json` | [LM Studio MCP](https://lmstudio.ai/docs/app/plugins/mcp) | `examples/clients/lmstudio.mcp.json` | Follows Cursor `mcpServers` notation, including `url` and `headers`. |
| Augment | Augment MCP settings / `mcpServers` | [Augment MCP](https://docs.augmentcode.com/setup-augment/mcp) | `examples/clients/augment.mcp.json` | HTTP servers use `"type": "http"`, `url`, and `headers`. |

### Not verified from official pages in this release

These were requested, but an official, current configuration schema could not be confirmed from a usable vendor page (empty, JS-rendered, or unreachable at documentation time). No guessed config is shipped:

- **Amazon Q Developer** — [AWS page](https://docs.aws.amazon.com/amazonq/latest/qdeveloper-ug/mcp.html) did not yield a usable schema in the fetched document.
- **Trae** — [Trae MCP page](https://docs.trae.ai/ide/model-context-protocol) was not usable as fetched.
- **Goose** — official documentation URLs were not reachable at documentation time.

If you use one of those clients, follow its current vendor docs, or use the stdio bridge in `examples/clients/stdio-mcp-wordpress-remote.json` when the client only starts local processes.

## VS Code official one-click / CLI

From a Bash shell, with your real URL and header substituted:

```bash
code --add-mcp '{"name":"kirki","type":"http","url":"https://YOUR_SITE_URL/wp-json/mcp/mcp-adapter-default-server","headers":{"Authorization":"Basic YOUR_BASIC_CREDENTIAL"}}'
```

This is the official VS Code installer. It writes editor-managed configuration, not a portable Copilot file.

## Shared stdio bridge

```json
{
  "mcpServers": {
    "kirki": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote"],
      "env": {
        "WP_API_URL": "https://YOUR_SITE_URL/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "YOUR_USERNAME",
        "WP_API_PASSWORD": "YOUR_APP_PASSWORD"
      }
    }
  }
}
```
