import { describe, expect, it } from 'vitest';

import { loadConfig } from '../src/config.js';

describe('loadConfig', () => {
  it('normalizes an HTTPS origin to the versioned API base', () => {
    const config = loadConfig({
      LOLO_CONTENT_API_URL: 'https://cms.example.test/root/',
      LOLO_CONTENT_API_TOKEN: 'secret-value',
    });
    expect(config.apiBaseUrl.href).toBe('https://cms.example.test/root/api/content/v1/');
    expect(config.token).toBe('secret-value');
  });

  it('does not duplicate a supplied versioned API path', () => {
    expect(loadConfig({
      LOLO_CONTENT_API_URL: 'https://cms.example.test/api/content/v1/',
      LOLO_CONTENT_API_TOKEN: 'token',
    }).apiBaseUrl.href).toBe('https://cms.example.test/api/content/v1/');
  });

  it.each(['http://localhost:8000', 'http://127.0.0.1:8000', 'http://[::1]:8000'])(
    'allows loopback HTTP development at %s',
    (url) => expect(() => loadConfig({
      LOLO_CONTENT_API_URL: url,
      LOLO_CONTENT_API_TOKEN: 'token',
    })).not.toThrow(),
  );

  it.each([
    'http://cms.example.test',
    'ftp://cms.example.test',
    'https://user:pass@cms.example.test',
    'https://cms.example.test?token=bad',
  ])('rejects unsafe API URL %s', (url) => {
    expect(() => loadConfig({
      LOLO_CONTENT_API_URL: url,
      LOLO_CONTENT_API_TOKEN: 'token',
    })).toThrow();
  });

  it('requires both settings without including their values in errors', () => {
    expect(() => loadConfig({})).toThrow('LOLO_CONTENT_API_URL is required');
    expect(() => loadConfig({ LOLO_CONTENT_API_URL: 'https://cms.example.test' })).toThrow('LOLO_CONTENT_API_TOKEN is required');
  });
});
