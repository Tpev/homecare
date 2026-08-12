import { describe, expect, it, vi } from 'vitest';

import { ContentApiClient, type Fetch } from '../src/client.js';
import { SERVER_INSTRUCTIONS } from '../src/server.js';
import { executeTool, TOOL_DEFINITIONS, TOOL_SCHEMAS } from '../src/tools.js';

const UUID = '123e4567-e89b-42d3-a456-426614174000';

function harness(body: unknown = { data: { ok: true } }): {
  client: ContentApiClient;
  fetch: ReturnType<typeof vi.fn<Fetch>>;
} {
  const mockedFetch = vi.fn<Fetch>().mockResolvedValue(new Response(JSON.stringify(body), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  }));
  return {
    client: new ContentApiClient({
      apiBaseUrl: new URL('https://cms.example.test/api/content/v1/'),
      token: 'secret',
    }, mockedFetch),
    fetch: mockedFetch,
  };
}

describe('MCP tool contract', () => {
  it('exposes exactly the required tool names', () => {
    expect(Object.keys(TOOL_SCHEMAS)).toEqual([
      'list_articles',
      'get_article',
      'list_content_options',
      'create_article_draft',
      'update_article',
      'upload_article_media',
      'preview_article',
      'audit_article',
      'submit_article_for_review',
      'schedule_article',
      'publish_article',
    ]);
  });

  it('marks scheduling and publishing as approval-requiring destructive idempotent writes', () => {
    for (const name of ['schedule_article', 'publish_article'] as const) {
      expect(TOOL_DEFINITIONS[name].annotations).toMatchObject({
        readOnlyHint: false,
        destructiveHint: true,
        idempotentHint: true,
        openWorldHint: true,
      });
      expect(TOOL_DEFINITIONS[name].description).toContain('explicit user approval');
    }
  });

  it('marks only retrieval/preview tools read-only and retains workflow instructions', () => {
    for (const name of ['list_articles', 'get_article', 'list_content_options', 'preview_article'] as const) {
      expect(TOOL_DEFINITIONS[name].annotations.readOnlyHint).toBe(true);
    }
    expect(TOOL_DEFINITIONS.audit_article.annotations.readOnlyHint).toBe(false);
    expect(TOOL_DEFINITIONS.audit_article.annotations.idempotentHint).toBe(false);
    expect(TOOL_DEFINITIONS.submit_article_for_review.annotations.idempotentHint).toBe(false);
    expect(SERVER_INSTRUCTIONS.slice(0, 512)).toContain('Never review or approve');
    expect(SERVER_INSTRUCTIONS).toContain('explicit approval');
    expect(SERVER_INSTRUCTIONS).toContain('If readiness requires independent review');
    expect(SERVER_INSTRUCTIONS).toContain('otherwise submission is optional');
  });

  it('rejects path traversal and non-canonical article identifiers before any request', async () => {
    const test = harness();
    await expect(executeTool('get_article', { article_id: '../options' }, test.client)).rejects.toThrow();
    expect(test.fetch).not.toHaveBeenCalled();
  });

  it.each([
    ['create_article_draft', { title: 'Bad canonical URL', canonical_url: 'not-a-url' }],
    ['create_article_draft', { title: 'Bad source URL', sources: [{ uuid: UUID, title: 'Source', url: 'not-a-url' }] }],
    ['upload_article_media', { article_id: 1, file_path: 'image.jpg', alt_text: 'Alt', source_url: 'not-a-url' }],
  ] as const)('returns schema validation for malformed URLs in %s', async (name, args) => {
    const test = harness();
    await expect(executeTool(name, args, test.client)).rejects.toMatchObject({ name: 'ZodError' });
    expect(test.fetch).not.toHaveBeenCalled();
  });
});

describe('executeTool API mapping', () => {
  it.each([
    ['list_articles', { status: 'draft', page: 2 }, 'GET', '/posts?status=draft&page=2'],
    ['get_article', { article_id: 5 }, 'GET', '/posts/5'],
    ['list_content_options', { include: ['authors', 'tags'] }, 'GET', '/options?include=authors%2Ctags'],
    ['preview_article', { article_id: 5 }, 'GET', '/posts/5/preview'],
  ] as const)('maps %s to its Content API read endpoint', async (name, args, method, path) => {
    const test = harness();
    await executeTool(name, args, test.client);
    const [url, init] = test.fetch.mock.calls[0] ?? [];
    expect(init?.method).toBe(method);
    expect(String(url)).toContain(path);
  });

  it.each([
    ['audit_article', { article_id: 5, idempotency_key: UUID }, '/posts/5/audit', {}],
    ['submit_article_for_review', { article_id: 5, edit_version: 3, idempotency_key: UUID }, '/posts/5/submit', { edit_version: 3 }],
    ['schedule_article', { article_id: 5, edit_version: 3, scheduled_for: '2026-09-01T10:00:00+02:00', idempotency_key: UUID }, '/posts/5/schedule', { edit_version: 3, scheduled_for: '2026-09-01T10:00:00+02:00' }],
    ['publish_article', { article_id: 5, edit_version: 3, idempotency_key: UUID }, '/posts/5/publish', { edit_version: 3 }],
  ] as const)('maps %s to its idempotent action endpoint', async (name, args, path, expectedBody) => {
    const test = harness();
    await executeTool(name, args, test.client);
    const [url, init] = test.fetch.mock.calls[0] ?? [];
    expect(init?.method).toBe('POST');
    expect(String(url)).toContain(path);
    expect(new Headers(init?.headers).get('idempotency-key')).toBe(UUID);
    expect(JSON.parse(String(init?.body))).toEqual(expectedBody);
  });

  it('converts Markdown before creating a draft and preserves stable sources', async () => {
    const test = harness();
    await executeTool('create_article_draft', {
      title: 'A safe guide',
      markdown: `Evidence matters {{cite:${UUID}}}.`,
      sources: [{ uuid: UUID, title: 'Primary source', url: 'https://example.test/source' }],
      idempotency_key: UUID,
    }, test.client);
    const [, init] = test.fetch.mock.calls[0] ?? [];
    const body = JSON.parse(String(init?.body));
    expect(body.markdown).toBeUndefined();
    expect(body.content_json.type).toBe('doc');
    expect(body.sources[0].uuid).toBe(UUID);
  });

  it('requires draft citation UUIDs to be present in supplied sources', async () => {
    const test = harness();
    await expect(executeTool('create_article_draft', {
      title: 'Missing source',
      markdown: `Claim {{cite:${UUID}}}.`,
      sources: [],
    }, test.client)).rejects.toThrow('not supplied');
    expect(test.fetch).not.toHaveBeenCalled();
  });

  it('maps updates to PATCH and keeps the optimistic edit_version', async () => {
    const test = harness();
    await executeTool('update_article', {
      article_id: 8,
      edit_version: 4,
      title: 'Updated title',
      content_json: { type: 'doc', content: [{ type: 'paragraph' }] },
      idempotency_key: UUID,
    }, test.client);
    const [url, init] = test.fetch.mock.calls[0] ?? [];
    expect(String(url)).toContain('/posts/8');
    expect(init?.method).toBe('PATCH');
    expect(JSON.parse(String(init?.body))).toMatchObject({ edit_version: 4, title: 'Updated title' });
  });
});
