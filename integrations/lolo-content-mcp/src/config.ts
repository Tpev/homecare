export type ContentApiConfig = {
  apiBaseUrl: URL;
  token: string;
  delegation?: {
    oauthTokenId: string;
  };
};

export type HostedMcpConfig = {
  contentApi: ContentApiConfig;
  publicUrl: URL;
  oauthIssuer: URL;
  introspectionUrl: URL;
  protectedResourceMetadataUrl: URL;
  host: '127.0.0.1';
  port: number;
  allowedOrigins: ReadonlySet<string>;
};

const ENV_URL = 'LOLO_CONTENT_API_URL';
const ENV_TOKEN = 'LOLO_CONTENT_API_TOKEN';

function isLoopback(hostname: string): boolean {
  const normalized = hostname.toLowerCase().replace(/^\[|\]$/g, '');
  return normalized === 'localhost' || normalized === '127.0.0.1' || normalized === '::1';
}

export function loadConfig(env: NodeJS.ProcessEnv = process.env): ContentApiConfig {
  const rawUrl = env[ENV_URL]?.trim();
  const token = env[ENV_TOKEN]?.trim();

  if (!rawUrl) {
    throw new Error(`${ENV_URL} is required.`);
  }
  if (!token) {
    throw new Error(`${ENV_TOKEN} is required.`);
  }

  let url: URL;
  try {
    url = new URL(rawUrl);
  } catch {
    throw new Error(`${ENV_URL} must be an absolute URL.`);
  }

  if (url.username || url.password) {
    throw new Error(`${ENV_URL} must not contain credentials.`);
  }
  if (url.search || url.hash) {
    throw new Error(`${ENV_URL} must not contain a query string or fragment.`);
  }
  if (url.protocol !== 'https:' && !(url.protocol === 'http:' && isLoopback(url.hostname))) {
    throw new Error(`${ENV_URL} must use HTTPS (HTTP is allowed only for loopback development).`);
  }

  const normalizedPath = url.pathname.replace(/\/+$/, '');
  url.pathname = normalizedPath.endsWith('/api/content/v1')
    ? `${normalizedPath}/`
    : `${normalizedPath}/api/content/v1/`.replace(/\/{2,}/g, '/');

  return { apiBaseUrl: url, token };
}

function absoluteHttpsUrl(raw: string | undefined, name: string, fallback?: string): URL {
  const value = raw?.trim() || fallback;
  if (!value) throw new Error(`${name} is required.`);
  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new Error(`${name} must be an absolute URL.`);
  }
  if (url.username || url.password || url.search || url.hash) {
    throw new Error(`${name} must not contain credentials, a query string, or a fragment.`);
  }
  if (url.protocol !== 'https:' && !(url.protocol === 'http:' && isLoopback(url.hostname))) {
    throw new Error(`${name} must use HTTPS (HTTP is allowed only for loopback tests).`);
  }
  return url;
}

export function loadHostedConfig(env: NodeJS.ProcessEnv = process.env): HostedMcpConfig {
  const contentApi = loadConfig(env);
  const publicUrl = absoluteHttpsUrl(
    env.LOLO_CONTENT_MCP_PUBLIC_URL ?? env.CONTENT_MCP_PUBLIC_URL,
    'LOLO_CONTENT_MCP_PUBLIC_URL',
    'https://carelolo.com/mcp/content',
  );
  if (publicUrl.pathname !== '/mcp/content' && publicUrl.pathname !== '/mcp/content/') {
    throw new Error('LOLO_CONTENT_MCP_PUBLIC_URL must use the /mcp/content endpoint.');
  }
  publicUrl.pathname = '/mcp/content';

  const oauthIssuer = absoluteHttpsUrl(
    env.LOLO_CONTENT_MCP_OAUTH_ISSUER,
    'LOLO_CONTENT_MCP_OAUTH_ISSUER',
    publicUrl.origin,
  );
  oauthIssuer.pathname = oauthIssuer.pathname.replace(/\/+$/, '') || '/';
  const introspectionUrl = new URL('oauth/introspect', oauthIssuer.href.endsWith('/') ? oauthIssuer : new URL(`${oauthIssuer.href}/`));
  const protectedResourceMetadataUrl = new URL('/.well-known/oauth-protected-resource/mcp/content', oauthIssuer);

  const rawPort = env.LOLO_CONTENT_MCP_PORT ?? env.CONTENT_MCP_SERVICE_PORT ?? '8090';
  const port = Number(rawPort);
  if (!Number.isInteger(port) || port < 1024 || port > 65535) {
    throw new Error('LOLO_CONTENT_MCP_PORT must be an integer from 1024 through 65535.');
  }

  const allowedOrigins = new Set<string>([publicUrl.origin]);
  for (const rawOrigin of (env.LOLO_CONTENT_MCP_ALLOWED_ORIGINS ?? '').split(',')) {
    const trimmed = rawOrigin.trim();
    if (!trimmed) continue;
    const origin = absoluteHttpsUrl(trimmed, 'LOLO_CONTENT_MCP_ALLOWED_ORIGINS entry');
    if (origin.pathname !== '/') throw new Error('Allowed MCP origins must not include a path.');
    allowedOrigins.add(origin.origin);
  }

  return {
    contentApi,
    publicUrl,
    oauthIssuer,
    introspectionUrl,
    protectedResourceMetadataUrl,
    host: '127.0.0.1',
    port,
    allowedOrigins,
  };
}
