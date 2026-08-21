import { z } from 'zod';

import type { Fetch } from './client.js';
import type { HostedMcpConfig } from './config.js';

const introspectionResponse = z.object({
  active: z.boolean(),
  scope: z.string().optional(),
  client_id: z.string().optional(),
  sub: z.string().optional(),
  actor_user_id: z.number().int().positive().optional(),
  token_id: z.string().uuid().optional(),
  aud: z.string().optional(),
  exp: z.number().int().optional(),
});

export type HostedActor = {
  oauthTokenId: string;
  actorUserId: number;
  clientId: string;
  scopes: ReadonlySet<string>;
  expiresAt: number;
};

export class HostedAuthenticationError extends Error {
  readonly httpStatus: 401 | 503;

  constructor(message: string, httpStatus: 401 | 503 = 401) {
    super(message);
    this.name = 'HostedAuthenticationError';
    this.httpStatus = httpStatus;
  }
}

export async function introspectBearer(
  config: HostedMcpConfig,
  oauthBearer: string,
  fetchImplementation: Fetch = fetch,
): Promise<HostedActor> {
  let response: Response;
  try {
    response = await fetchImplementation(config.introspectionUrl, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        Authorization: `Basic ${Buffer.from(`lolo-content-mcp-resource:${config.contentApi.token}`).toString('base64')}`,
        'Content-Type': 'application/x-www-form-urlencoded',
        'User-Agent': '@lolo-care/content-mcp/1.0.0',
      },
      body: new URLSearchParams({ token: oauthBearer }),
      redirect: 'error',
      signal: AbortSignal.timeout(10_000),
    });
  } catch {
    throw new HostedAuthenticationError('The OAuth authorization service could not be reached.', 503);
  }

  if (!response.ok) {
    throw new HostedAuthenticationError('The hosted MCP resource service was not authorized to introspect OAuth sessions.', 503);
  }

  let parsed: unknown;
  try {
    parsed = await response.json();
  } catch {
    throw new HostedAuthenticationError('The OAuth introspection response was malformed.', 503);
  }
  const result = introspectionResponse.safeParse(parsed);
  if (!result.success || !result.data.active) {
    throw new HostedAuthenticationError('The OAuth bearer is inactive.');
  }

  const data = result.data;
  if (!data.token_id || !data.actor_user_id || !data.client_id || !data.exp
    || data.aud !== config.publicUrl.href || data.exp <= Math.floor(Date.now() / 1000)) {
    throw new HostedAuthenticationError('The OAuth bearer is not valid for this MCP resource.');
  }

  return {
    oauthTokenId: data.token_id,
    actorUserId: data.actor_user_id,
    clientId: data.client_id,
    scopes: new Set((data.scope ?? '').split(/\s+/).filter(Boolean)),
    expiresAt: data.exp,
  };
}
