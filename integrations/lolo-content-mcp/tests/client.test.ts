import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { tmpdir } from 'node:os';

import { afterEach, describe, expect, it, vi } from 'vitest';

import { ContentApiClient, ContentApiError, type Fetch } from '../src/client.js';

const config = {
  apiBaseUrl: new URL('https://cms.example.test/api/content/v1/'),
  token: 'super-secret-token',
};

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('ContentApiClient', () => {
  it('sends scoped GET queries and the bearer credential in a header', async () => {
    const mockedFetch = vi.fn<Fetch>().mockResolvedValue(jsonResponse({ data: [{ id: 4 }] }));
    const client = new ContentApiClient(config, mockedFetch);
    const response = await client.get('posts', { status: 'draft', page: 2, unused: undefined });

    expect(response.data).toEqual({ data: [{ id: 4 }] });
    const [url, init] = mockedFetch.mock.calls[0] ?? [];
    expect(String(url)).toBe('https://cms.example.test/api/content/v1/posts?status=draft&page=2');
    const headers = new Headers(init?.headers);
    expect(headers.get('authorization')).toBe('Bearer super-secret-token');
    expect(String(url)).not.toContain('super-secret-token');
  });

  it('sets the supplied idempotency key and returns it for safe retries', async () => {
    const mockedFetch = vi.fn<Fetch>().mockResolvedValue(jsonResponse({ data: { id: 9 } }, 201));
    const client = new ContentApiClient(config, mockedFetch);
    const key = '123e4567-e89b-42d3-a456-426614174000';
    const response = await client.mutate('POST', 'posts', { title: 'Draft' }, key);

    expect(response.idempotency_key).toBe(key);
    const [, init] = mockedFetch.mock.calls[0] ?? [];
    expect(new Headers(init?.headers).get('idempotency-key')).toBe(key);
    expect(JSON.parse(String(init?.body))).toEqual({ title: 'Draft' });
  });

  it('signs short-lived request-bound actor delegation without replacing the service bearer', async () => {
    const mockedFetch = vi.fn<Fetch>().mockResolvedValue(jsonResponse({ data: [] }));
    const client = new ContentApiClient({
      ...config,
      delegation: { oauthTokenId: '123e4567-e89b-42d3-a456-426614174000' },
    }, mockedFetch);
    await client.get('posts');
    const [url, init] = mockedFetch.mock.calls[0] ?? [];
    const headers = new Headers(init?.headers);
    const payload = JSON.parse(Buffer.from(String(headers.get('x-lolo-mcp-delegation')), 'base64url').toString());
    expect(headers.get('authorization')).toBe('Bearer super-secret-token');
    expect(headers.get('x-lolo-mcp-signature')).toMatch(/^[A-Za-z0-9_-]{43}$/);
    expect(payload).toMatchObject({
      v: 1,
      oauth_token_id: '123e4567-e89b-42d3-a456-426614174000',
      method: 'GET',
      path: new URL(String(url)).pathname,
    });
    expect(payload.exp - payload.iat).toBe(30);
  });

  it('maps validation and conflict responses to actionable structured errors', async () => {
    const validationFetch = vi.fn<Fetch>().mockResolvedValue(jsonResponse({
      message: 'The given data was invalid.',
      errors: { title: ['A title is required.'] },
    }, 422));
    const validationClient = new ContentApiClient(config, validationFetch);
    const validation = await validationClient.mutate('POST', 'posts', {}).catch((error: unknown) => error);
    expect(validation).toBeInstanceOf(ContentApiError);
    expect((validation as ContentApiError).toJSON()).toMatchObject({
      status: 422,
      fields: { title: ['A title is required.'] },
      action: expect.stringContaining('new idempotency_key'),
    });

    const conflictFetch = vi.fn<Fetch>().mockResolvedValue(jsonResponse({
      error: { code: 'edit_version_conflict', message: 'Article changed.', details: { current_edit_version: 8 } },
    }, 409));
    const conflictClient = new ContentApiClient(config, conflictFetch);
    const conflict = await conflictClient.mutate('PATCH', 'posts/4', { edit_version: 7 }).catch((error: unknown) => error);
    expect((conflict as ContentApiError).toJSON()).toMatchObject({
      error: 'edit_version_conflict',
      details: { current_edit_version: 8 },
      action: expect.stringContaining('Fetch the article again'),
    });
  });

  describe('multipart uploads', () => {
    let directory: string | undefined;
    afterEach(async () => {
      if (directory) await rm(directory, { recursive: true, force: true });
      directory = undefined;
    });

    it('uploads image bytes and metadata without sending a local path', async () => {
      directory = await mkdtemp(join(tmpdir(), 'lolo-mcp-'));
      const path = join(directory, 'care.png');
      await writeFile(path, Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 0x00]));
      const mockedFetch = vi.fn<Fetch>().mockResolvedValue(jsonResponse({ data: { id: 12 } }, 201));
      const client = new ContentApiClient(config, mockedFetch);

      await client.uploadImage(7, path, {
        alt_text: 'Caregiver helping a family',
        license: 'Licensed by example photographer',
        source_url: 'https://images.example.test/original',
      }, '123e4567-e89b-42d3-a456-426614174000');
      const [url, init] = mockedFetch.mock.calls[0] ?? [];
      expect(String(url)).toContain('/posts/7/media');
      expect(String(url)).not.toContain(directory);
      expect(init?.body).toBeInstanceOf(FormData);
      const form = init?.body as FormData;
      expect(form.get('alt_text')).toBe('Caregiver helping a family');
      expect(form.get('license')).toBe('Licensed by example photographer');
      expect(form.get('source_url')).toBe('https://images.example.test/original');
      expect((form.get('file') as File).name).toBe('care.png');
      expect(new Headers(init?.headers).has('content-type')).toBe(false);
    });

    it('rejects mismatched and unsupported files before fetch', async () => {
      directory = await mkdtemp(join(tmpdir(), 'lolo-mcp-'));
      const path = join(directory, 'not-image.png');
      await writeFile(path, 'plain text');
      const mockedFetch = vi.fn<Fetch>();
      const client = new ContentApiClient(config, mockedFetch);
      await expect(client.uploadImage(7, path, { alt_text: 'No' })).rejects.toThrow('Unsupported image content');
      expect(mockedFetch).not.toHaveBeenCalled();
    });

    it('uploads hosted canonical Base64 bytes with a safe filename', async () => {
      const mockedFetch = vi.fn<Fetch>().mockResolvedValue(jsonResponse({ data: { id: 13 } }, 201));
      const client = new ContentApiClient(config, mockedFetch);
      const encoded = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]).toString('base64');
      await client.uploadImageBase64(8, 'family-care.png', encoded, { alt_text: 'Family care' });
      const [, init] = mockedFetch.mock.calls[0] ?? [];
      const form = init?.body as FormData;
      expect((form.get('file') as File).name).toBe('family-care.png');
      await expect(client.uploadImageBase64(8, '../escape.png', encoded, { alt_text: 'No' })).rejects.toThrow('safe basename');
    });
  });
});
