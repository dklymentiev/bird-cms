# Bird CMS MCP server

Model Context Protocol stdio server. Lets MCP-aware clients (Claude
Desktop, Cursor, Continue, Zed, Claude Code) read and write Bird CMS
articles directly — no copy-paste, no custom integration.

> **Status: v0.2.** 11 tools shipped. Article CRUD + page CRUD +
> publish toggle + search.

## Quick start

### Claude Desktop

Add to `claude_desktop_config.json` (location varies by OS — see
[Anthropic docs](https://modelcontextprotocol.io/quickstart/user)):

```json
{
  "mcpServers": {
    "bird-cms": {
      "command": "php",
      "args": ["/absolute/path/to/your-site/mcp/server.php"],
      "env": {
        "BIRD_SITE_DIR": "/absolute/path/to/your-site"
      }
    }
  }
}
```

Restart Claude Desktop. The Bird CMS tools appear in the tools menu.

### Cursor / Continue / Zed

Same config, in their respective MCP server settings. The protocol is
standard.

### Try it

Once connected, ask your agent:

> List all my published articles in the "tutorials" category, then
> write a new draft article called "scaling-bird-on-hostinger" in
> the same category. Use the howto type.

The agent will call `list_articles`, then `write_article`, and the
two files (`scaling-bird-on-hostinger.md` and
`scaling-bird-on-hostinger.meta.yaml`) appear under
`content/articles/tutorials/`.

## Site root resolution

The server figures out which site to manage in this order:

1. `BIRD_SITE_DIR` env var (explicit, recommended)
2. Current working directory if it contains `content/articles/` or `config/app.php`
3. Walks up from the script location looking for `content/articles/`

If you have multiple Bird CMS sites, register one MCP server per site
in your client config (different `BIRD_SITE_DIR` per entry).

## Tools (v0.2)

### Articles
| Tool | Description |
|---|---|
| `list_articles` | Filter by category/status, returns slug, title, status, date |
| `read_article` | Returns frontmatter object + markdown body |
| `write_article` | Atomically writes `.md` + `.meta.yaml`, creates category dir if needed |
| `delete_article` | Removes both `.md` + `.meta.yaml`, idempotent |
| `list_categories` | Returns category slugs that have at least one article |

### Pages
| Tool | Description |
|---|---|
| `list_pages` | Lists every page in `content/pages/` |
| `read_page` | Returns frontmatter + body for a single page |
| `write_page` | Atomic create/update under `content/pages/<slug>.{md,meta.yaml}` |

### Publishing
| Tool | Description |
|---|---|
| `publish` | Sets `status: published` in `.meta.yaml` without touching the body |
| `unpublish` | Sets `status: draft` |

### Search
| Tool | Description |
|---|---|
| `search` | Case-insensitive substring match across `content/**/*.{md,yaml}`. Returns file, line, snippet. PHP-native walk (no shell dep), works on Alpine/BusyBox. |

## Roadmap

Future tools (file an issue if you need them sooner):
- `delete_page(slug)` — symmetric with `delete_article`
- `read_resource('llms.txt')` — surface generated AEO file via MCP `resources/read`
- `read_resource('sitemap.xml')` — same for sitemap
- Image upload (binary writes via MCP)
- Webhook on git push (resource subscriptions)

## Security model

Stdio MCP is **local-only** by design. The client launches the
server as a subprocess; if the user can launch the server, they
already have filesystem access to the site. No bearer token, no
auth — the OS file permissions are the security boundary.

If you want remote/SSE MCP for a hosted multi-site editor, that's a
different server (not yet built). File an issue if you need it.

## Anti-pattern: do NOT call this from a browser

The server has no HTTP transport. Don't expose it via PHP-FPM and
nginx. The protocol is JSON-RPC over stdin/stdout — wrapping it in a
web endpoint defeats both the security model and the protocol.

## Verifying the server runs

From your site directory:

```
echo '{"jsonrpc":"2.0","id":1,"method":"initialize"}' | php engine/mcp/server.php
```

You should see a JSON response with `serverInfo: {name: "bird-cms"}`.

If it errors with "cannot find site root," set `BIRD_SITE_DIR`:

```
BIRD_SITE_DIR=/path/to/site echo '...' | php /path/to/engine/mcp/server.php
```
