import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js';
import { describe, expect, it, vi } from 'vitest';

import type { Fetch } from '../src/client.js';
import { createServer, SERVER_INSTRUCTIONS } from '../src/server.js';

describe('MCP server integration', () => {
  it('advertises instructions, exact tools, and annotations through MCP', async () => {
    const mockedFetch = vi.fn<Fetch>().mockResolvedValue(new Response(JSON.stringify({ data: [] }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }));
    const server = createServer({
      LOLO_CONTENT_API_URL: 'https://cms.example.test',
      LOLO_CONTENT_API_TOKEN: 'not-a-real-token',
    }, mockedFetch);
    const client = new Client({ name: 'connector-test', version: '1.0.0' });
    const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair();

    await Promise.all([server.connect(serverTransport), client.connect(clientTransport)]);
    try {
      expect(client.getInstructions()).toBe(SERVER_INSTRUCTIONS);
      const listed = await client.listTools();
      expect(listed.tools).toHaveLength(11);
      expect(listed.tools.map((tool) => tool.name)).toContain('publish_article');
      expect(listed.tools.find((tool) => tool.name === 'publish_article')?.annotations).toMatchObject({
        readOnlyHint: false,
        destructiveHint: true,
        idempotentHint: true,
      });

      const called = await client.callTool({ name: 'list_articles', arguments: { status: 'draft' } });
      expect(called.isError).not.toBe(true);
      expect(called.structuredContent).toMatchObject({ data: [] });
      expect(mockedFetch).toHaveBeenCalledOnce();
      expect(String(mockedFetch.mock.calls[0]?.[0])).toContain('/api/content/v1/posts?status=draft');
    } finally {
      await Promise.all([client.close(), server.close()]);
    }
  });
});
