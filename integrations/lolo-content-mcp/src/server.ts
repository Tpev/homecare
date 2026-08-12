import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';

import { ContentApiClient, type Fetch } from './client.js';
import { loadConfig } from './config.js';
import { registerTools } from './tools.js';

export const SERVER_INSTRUCTIONS = 'Never review or approve an article with this connector, and never seek a shortcut around independent review or readiness gates. Before schedule_article or publish_article, show the user the intended article and timing and obtain explicit approval; those are high-impact writes. Fetch the latest article before edits and use its edit_version. Reuse one idempotency_key when retrying a mutation. Use audit_article before submission, then submit_article_for_review; a different authorized human must review in the CMS. Preview URLs protect unpublished content and must not be shared publicly.';

export function createServer(
  env: NodeJS.ProcessEnv = process.env,
  fetchImplementation: Fetch = fetch,
): McpServer {
  const client = new ContentApiClient(loadConfig(env), fetchImplementation);
  const server = new McpServer(
    { name: 'lolo-content', version: '1.0.0' },
    { instructions: SERVER_INSTRUCTIONS },
  );
  registerTools(server, client);
  return server;
}
