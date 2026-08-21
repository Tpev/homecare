import express, { type NextFunction, type Request, type Response } from 'express';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';

import type { Fetch } from './client.js';
import type { HostedMcpConfig } from './config.js';
import { HostedAuthenticationError, introspectBearer } from './hosted-auth.js';
import { createServer } from './server.js';
import { TOOL_REQUIRED_SCOPES, type ToolName } from './tools.js';

const SUPPORTED_SCOPES = [...new Set(Object.values(TOOL_REQUIRED_SCOPES))];

function bearerToken(request: Request): string | undefined {
  const authorization = request.header('authorization') ?? '';
  const match = /^Bearer\s+([^\s]+)$/i.exec(authorization);
  return match?.[1];
}

function requestedToolScope(body: unknown): string | undefined {
  if (!body || typeof body !== 'object' || Array.isArray(body)) return undefined;
  const message = body as { method?: unknown; params?: unknown };
  if (message.method !== 'tools/call' || !message.params || typeof message.params !== 'object') return undefined;
  const name = (message.params as { name?: unknown }).name;
  return typeof name === 'string' && name in TOOL_REQUIRED_SCOPES
    ? TOOL_REQUIRED_SCOPES[name as ToolName]
    : undefined;
}

function challenge(config: HostedMcpConfig, error?: 'insufficient_scope', scope?: string): string {
  const parts = [`Bearer resource_metadata="${config.protectedResourceMetadataUrl.href}"`];
  if (error) parts.push(`error="${error}"`);
  parts.push(`scope="${scope ?? SUPPORTED_SCOPES.join(' ')}"`);
  return parts.join(', ');
}

function hasAllowedOrigin(request: Request, config: HostedMcpConfig): boolean {
  const rawOrigin = request.header('origin');
  if (!rawOrigin) return true;
  try {
    return config.allowedOrigins.has(new URL(rawOrigin).origin);
  } catch {
    return false;
  }
}

function hasAllowedHost(request: Request, config: HostedMcpConfig): boolean {
  const hostHeader = request.header('host') ?? '';
  try {
    const hostname = new URL(`http://${hostHeader}`).hostname.toLowerCase().replace(/^\[|\]$/g, '');
    return ['127.0.0.1', 'localhost', '::1', config.publicUrl.hostname.toLowerCase()].includes(hostname);
  } catch {
    return false;
  }
}

export function createHostedMcpApp(
  config: HostedMcpConfig,
  fetchImplementation: Fetch = fetch,
): express.Express {
  const app = express();
  app.disable('x-powered-by');
  app.use(express.json({ limit: '29mb', strict: true, type: ['application/json', 'application/*+json'] }));

  app.get('/healthz', (_request, response) => {
    response.set('Cache-Control', 'no-store').json({ status: 'ok', service: 'lolo-content-mcp' });
  });

  app.all(config.publicUrl.pathname, async (request, response) => {
    response.set('Cache-Control', 'no-store');
    if (!hasAllowedHost(request, config) || !hasAllowedOrigin(request, config)) {
      response.status(403).json({ error: 'forbidden', message: 'The request origin or host is not allowed.' });
      return;
    }
    if (request.method !== 'POST') {
      response.set('Allow', 'POST').status(405).json({
        jsonrpc: '2.0',
        error: { code: -32000, message: 'Method not allowed.' },
        id: null,
      });
      return;
    }

    const bearer = bearerToken(request);
    if (!bearer) {
      response.set('WWW-Authenticate', challenge(config)).status(401).json({ error: 'unauthorized' });
      return;
    }

    let actor;
    try {
      actor = await introspectBearer(config, bearer, fetchImplementation);
    } catch (error) {
      const status = error instanceof HostedAuthenticationError ? error.httpStatus : 503;
      if (status === 401) response.set('WWW-Authenticate', challenge(config));
      response.status(status).json({ error: status === 401 ? 'unauthorized' : 'authorization_service_unavailable' });
      return;
    }

    const requiredScope = requestedToolScope(request.body);
    if (requiredScope && !actor.scopes.has(requiredScope)) {
      response.set('WWW-Authenticate', challenge(config, 'insufficient_scope', requiredScope))
        .status(403).json({ error: 'insufficient_scope', required_scope: requiredScope });
      return;
    }

    const server = createServer({
      LOLO_CONTENT_API_URL: config.contentApi.apiBaseUrl.href,
      LOLO_CONTENT_API_TOKEN: config.contentApi.token,
    }, fetchImplementation, {
      delegation: { oauthTokenId: actor.oauthTokenId },
      allowLocalFiles: false,
    });
    const transport = new StreamableHTTPServerTransport(
      { sessionIdGenerator: undefined } as unknown as ConstructorParameters<typeof StreamableHTTPServerTransport>[0],
    );
    response.on('close', () => {
      void transport.close().finally(() => server.close());
    });
    try {
      await server.connect(transport as unknown as Parameters<typeof server.connect>[0]);
      await transport.handleRequest(request, response, request.body);
    } catch {
      await Promise.allSettled([transport.close(), server.close()]);
      if (!response.headersSent) {
        response.status(500).json({
          jsonrpc: '2.0',
          error: { code: -32603, message: 'Internal MCP server error.' },
          id: null,
        });
      }
    }
  });

  app.use((error: unknown, _request: Request, response: Response, _next: NextFunction) => {
    const tooLarge = typeof error === 'object' && error !== null && 'type' in error
      && (error as { type?: unknown }).type === 'entity.too.large';
    response.status(tooLarge ? 413 : 400).json({
      error: tooLarge ? 'request_too_large' : 'invalid_json',
      message: tooLarge ? 'The MCP request exceeds the 29 MiB hosted limit.' : 'The MCP request body is invalid JSON.',
    });
  });

  return app;
}
