# blocky-mcp-server

Minimal stdio MCP proxy: connects Claude Desktop (or any MCP client) to a
Blocky-powered WordPress site through the authenticated REST MCP endpoint.

## Configuration

Create a key on the site: `wp blocky key create --name="Claude" --scopes=catalog:read,documents:read,documents:write,mcp:access`

```json
{
  "mcpServers": {
    "blocky": {
      "command": "node",
      "args": ["packages/mcp-server/index.js"],
      "env": {
        "BLOCKY_SITE_URL": "https://customer.example",
        "BLOCKY_API_KEY": "bky_live_..."
      }
    }
  }
}
```

Tools exposed by the site: `blocky_list_blocks`, `blocky_get_document`,
`blocky_save_document`. The server enforces scopes, rate limits and the
block contract server-side; the proxy adds no policy of its own.
