#!/usr/bin/env node
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';

import { createServer } from './server.js';

async function main(): Promise<void> {
  const server = createServer();
  await server.connect(new StdioServerTransport());
}

main().catch(() => {
  // STDERR is safe for STDIO MCP diagnostics. Do not include environment values or request details.
  process.stderr.write('LoLo Content MCP failed to start. Verify its required environment variables and build.\n');
  process.exitCode = 1;
});
