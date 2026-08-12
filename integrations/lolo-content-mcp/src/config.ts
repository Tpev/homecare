export type ContentApiConfig = {
  apiBaseUrl: URL;
  token: string;
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
