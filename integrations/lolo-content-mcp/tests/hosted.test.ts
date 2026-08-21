import type { AddressInfo } from 'node:net';

import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';
import { afterEach, describe, expect, it, vi } from 'vitest';

import type { Fetch } from '../src/client.js';
import type { HostedMcpConfig } from '../src/config.js';
import { createHostedMcpApp } from '../src/hosted.js';

const servers: Array<{ close: (callback: () => void) => void }> = [];

afterEach(async () => {
  await Promise.all(servers.splice(0).map((server) => new Promise<void>((resolve) => server.close(resolve))));
});

function config(): HostedMcpConfig {
  return {
    contentApi: {
      apiBaseUrl: new URL('https://carelolo.com/api/content/v1/'),
      token: 'hosted-service-secret',
    },
    publicUrl: new URL('https://carelolo.com/mcp/content'),
    oauthIssuer: new URL('https://carelolo.com/'),
    introspectionUrl: new URL('https://carelolo.com/oauth/introspect'),
    protectedResourceMetadataUrl: new URL('https://carelolo.com/.well-known/oauth-protected-resource/mcp/content'),
    host: '127.0.0.1',
    port: 8090,
    allowedOrigins: new Set(['https://carelolo.com']),
  };
}

async function listen(app: ReturnType<typeof createHostedMcpApp>): Promise<URL> {
  const server = await new Promise<ReturnType<typeof app.listen>>((resolve) => {
    const listening = app.listen(0, '127.0.0.1', () => resolve(listening));
  });
  servers.push(server);
  const address = server.address() as AddressInfo;
  return new URL(`http://127.0.0.1:${address.port}/mcp/content`);
}

describe('hosted Streamable HTTP server', () => {
  it('challenges unauthenticated calls and rejects browser origins outside the allowlist', async () => {
    const endpoint = await listen(createHostedMcpApp(config(), vi.fn<Fetch>()));
    const unauthenticated = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'initialize', params: {} }),
    });
    expect(unauthenticated.status).toBe(401);
    expect(unauthenticated.headers.get('www-authenticate')).toContain('/.well-known/oauth-protected-resource/mcp/content');

    const forbidden = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Origin: 'https://attacker.example' },
      body: JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'initialize', params: {} }),
    });
    expect(forbidden.status).toBe(403);
  });

  it('serves tools with a per-request OAuth actor and signed Content API delegation', async () => {
    const mockedFetch = vi.fn<Fetch>().mockImplementation(async (input, init) => {
      const url = new URL(String(input));
      if (url.pathname === '/oauth/introspect') {
        return new Response(JSON.stringify({
          active: true,
          scope: 'content:read',
          client_id: 'codex-charles',
          actor_user_id: 386,
          token_id: '123e4567-e89b-42d3-a456-426614174000',
          aud: 'https://carelolo.com/mcp/content',
          exp: Math.floor(Date.now() / 1000) + 900,
        }), { headers: { 'Content-Type': 'application/json' } });
      }
      expect(new Headers(init?.headers).get('authorization')).toBe('Bearer hosted-service-secret');
      expect(new Headers(init?.headers).get('x-lolo-mcp-delegation')).toBeTruthy();
      expect(new Headers(init?.headers).get('x-lolo-mcp-signature')).toMatch(/^[A-Za-z0-9_-]{43}$/);
      return new Response(JSON.stringify({ data: [] }), { headers: { 'Content-Type': 'application/json' } });
    });
    const endpoint = await listen(createHostedMcpApp(config(), mockedFetch));
    const transport = new StreamableHTTPClientTransport(endpoint, {
      requestInit: { headers: { Authorization: 'Bearer charles-oauth-token' } },
    });
    const client = new Client({ name: 'hosted-test', version: '1.0.0' });
    await client.connect(transport);
    try {
      const tools = await client.listTools();
      expect(tools.tools).toHaveLength(11);
      const called = await client.callTool({ name: 'list_articles', arguments: {} });
      expect(called.isError).not.toBe(true);
      expect(called.structuredContent).toMatchObject({ data: [] });
    } finally {
      await client.close();
    }
    expect(mockedFetch.mock.calls.some(([input]) => new URL(String(input)).pathname === '/api/content/v1/posts')).toBe(true);
  });

  it('returns HTTP insufficient_scope before invoking a write tool', async () => {
    const mockedFetch = vi.fn<Fetch>().mockResolvedValue(new Response(JSON.stringify({
      active: true,
      scope: 'content:read',
      client_id: 'codex-charles',
      actor_user_id: 386,
      token_id: '123e4567-e89b-42d3-a456-426614174000',
      aud: 'https://carelolo.com/mcp/content',
      exp: Math.floor(Date.now() / 1000) + 900,
    }), { headers: { 'Content-Type': 'application/json' } }));
    const endpoint = await listen(createHostedMcpApp(config(), mockedFetch));
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: {
        Authorization: 'Bearer oauth-read-only',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        jsonrpc: '2.0',
        id: 2,
        method: 'tools/call',
        params: { name: 'publish_article', arguments: { article_id: 1, edit_version: 2 } },
      }),
    });
    expect(response.status).toBe(403);
    expect(response.headers.get('www-authenticate')).toContain('error="insufficient_scope"');
    expect(response.headers.get('www-authenticate')).toContain('scope="content:publish"');
    expect(mockedFetch).toHaveBeenCalledOnce();
  });
});
