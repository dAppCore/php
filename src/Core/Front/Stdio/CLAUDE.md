# Front/Stdio

Stdio frontage for terminal/pipe-based I/O.

## Files

- **Boot.php** -- ServiceProvider for stdin/stdout transport. Handles non-HTTP contexts: Artisan commands, MCP stdio transport (agents connecting via pipes), and interactive CLI tools. Currently a placeholder with empty command registration -- core CLI commands will be registered here. No HTTP middleware -- this is a different transport entirely.
