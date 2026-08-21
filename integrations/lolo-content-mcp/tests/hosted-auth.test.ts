import { createHmac } from 'node:crypto';

import { describe, expect, it, vi } from 'vitest';

import type { Fetch } from '../src/client.js';
import type { HostedMcpConfig } from '../src/config.js';
import { HostedAuthenticationError, introspectBearer } from '../src/hosted-auth.js';

const config: HostedMcpConfig = {
  contentApi: {
    apiBaseUrl: new URL('https://carelolo.com/api/content/v1/'),
    token: 'server-only-service-token',
  },
  publicUrl: new URL('https://carelolo.com/mcp/content'),
  oauthIssuer: new URL('https://carelolo.com/'),
  introspectionUrl: new URL('https://carelolo.com/oauth/introspect'),
  protectedResourceMetadataUrl: new URL('https://carelolo.com/.well-known/oauth-protected-resource/mcp/content'),
  host: '127.0.0.1',
  port: 8090,
  allowedOrigins: new Set(['https://carelolo.com']),
};

describe('hosted OAuth introspection', () => {
  it('authenticates the resource service separately and returns a scoped actor', async () => {
    const mockedFetch = vi.fn<Fetch>().mockResolvedValue(new Response(JSON.stringify({
      active: true,
      scope: 'content:read content:draft',
      client_id: 'codex-client',
      sub: '386',
      actor_user_id: 386,
      token_id: '123e4567-e89b-42d3-a456-426614174000',
      aud: 'https://carelolo.com/mcp/content',
      exp: Math.floor(Date.now() / 1000) + 900,
    }), { status: 200, headers: { 'Content-Type': 'application/json' } }));

    const actor = await introspectBearer(config, 'charles-oauth-bearer', mockedFetch);
    expect(actor.actorUserId).toBe(386);
    expect(actor.scopes).toEqual(new Set(['content:read', 'content:draft']));
    const [, init] = mockedFetch.mock.calls[0] ?? [];
    expect(new Headers(init?.headers).get('authorization')).toBe(
      `Basic ${Buffer.from('lolo-content-mcp-resource:server-only-service-token').toString('base64')}`,
    );
    expect(String(init?.body)).toBe('token=charles-oauth-bearer');
    expect(JSON.stringify(init)).not.toContain(createHmac('sha256', 'irrelevant').digest('hex'));
  });

  it('rejects inactive and wrong-audience tokens without returning token details', async () => {
    const inactiveFetch = vi.fn<Fetch>().mockResolvedValue(new Response(JSON.stringify({ active: false }), {
      headers: { 'Content-Type': 'application/json' },
    }));
    await expect(introspectBearer(config, 'expired', inactiveFetch)).rejects.toBeInstanceOf(HostedAuthenticationError);

    const wrongAudienceFetch = vi.fn<Fetch>().mockResolvedValue(new Response(JSON.stringify({
      active: true,
      scope: 'content:read',
      client_id: 'client',
      actor_user_id: 386,
      token_id: '123e4567-e89b-42d3-a456-426614174000',
      aud: 'https://carelolo.com/api/content/v1',
      exp: Math.floor(Date.now() / 1000) + 60,
    }), { headers: { 'Content-Type': 'application/json' } }));
    await expect(introspectBearer(config, 'wrong-audience', wrongAudienceFetch)).rejects.toThrow('not valid for this MCP resource');
  });
});
