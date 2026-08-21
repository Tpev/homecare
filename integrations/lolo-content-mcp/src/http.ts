#!/usr/bin/env node
import { createHostedMcpApp } from './hosted.js';
import { loadHostedConfig } from './config.js';

async function main(): Promise<void> {
  const config = loadHostedConfig();
  const app = createHostedMcpApp(config);
  const listener = app.listen(config.port, config.host, () => {
    process.stderr.write(`LoLo Content MCP listening on ${config.host}:${config.port}.\n`);
  });

  const shutdown = (signal: string): void => {
    process.stderr.write(`LoLo Content MCP received ${signal}; shutting down.\n`);
    listener.close((error) => {
      process.exitCode = error ? 1 : 0;
    });
    setTimeout(() => process.exit(1), 10_000).unref();
  };
  process.once('SIGTERM', () => shutdown('SIGTERM'));
  process.once('SIGINT', () => shutdown('SIGINT'));
}

main().catch(() => {
  process.stderr.write('LoLo Content MCP failed to start. Verify configuration and the production build.\n');
  process.exitCode = 1;
});
