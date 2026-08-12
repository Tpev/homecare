import type { CallToolResult, ToolAnnotations } from '@modelcontextprotocol/sdk/types.js';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';

import { ContentApiClient, ContentApiError } from './client.js';
import { citedSourceKeys, markdownToTiptap, MarkdownConversionError } from './markdown.js';

const articleId = z.union([
  z.number().int().positive(),
  z.string().regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/).max(220),
]);
const idempotencyKey = z.string().uuid().optional().describe('Stable UUID reused when retrying the same mutation.');
const editVersion = z.number().int().nonnegative().describe('Current optimistic-lock edit_version from get_article.');
const httpUrl = (message: string) => z.url().refine((value) => {
  try {
    return ['http:', 'https:'].includes(new URL(value).protocol);
  } catch {
    return false;
  }
}, message);
const source = z.object({
  uuid: z.string().uuid().describe('Stable citation identifier; preserve this value across updates and reordering.'),
  title: z.string().min(1).max(255),
  publisher: z.string().max(255).optional(),
  url: httpUrl('Source URLs must use HTTP or HTTPS.'),
  published_on: z.string().date().optional(),
  accessed_on: z.string().date().optional(),
  notes: z.string().max(2_000).optional(),
});
const document = z.object({
  type: z.literal('doc'),
  content: z.array(z.unknown()),
}).passthrough();

const editableFields = {
  slug: z.string().regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/).max(220).optional(),
  excerpt: z.string().max(600).nullable().optional(),
  markdown: z.string().min(1).max(500_000).optional()
    .describe('Conservative Markdown. Use {{cite:UUID}} citations and explicit :::cta directives.'),
  content_json: document.optional().describe('Structured Tiptap document; do not combine with markdown.'),
  author_id: z.number().int().positive().nullable().optional(),
  featured_media_asset_id: z.number().int().positive().nullable().optional(),
  seo_title: z.string().max(220).nullable().optional(),
  meta_description: z.string().max(320).nullable().optional(),
  canonical_url: httpUrl('canonical_url must use HTTP or HTTPS.').max(255)
    .nullable().optional(),
  social_title: z.string().max(220).nullable().optional(),
  social_description: z.string().max(320).nullable().optional(),
  content_type: z.enum(['guide', 'news', 'case-study', 'research', 'company-update']).optional(),
  schema_type: z.enum(['BlogPosting', 'Article', 'NewsArticle']).optional(),
  locale: z.string().min(2).max(16).optional(),
  editorial_checklist: z.record(z.string(), z.boolean()).optional(),
  review_notes: z.string().max(10_000).nullable().optional(),
  research_methodology: z.string().max(20_000).nullable().optional(),
  category_ids: z.array(z.number().int().positive()).max(30).optional(),
  tag_ids: z.array(z.number().int().positive()).max(50).optional(),
  related_post_ids: z.array(z.number().int().positive()).max(12).optional(),
  sources: z.array(source).max(50).optional(),
};

export const TOOL_SCHEMAS = {
  list_articles: z.object({
    status: z.enum(['draft', 'in_review', 'scheduled', 'published', 'archived']).optional(),
    search: z.string().max(220).optional(),
    author_id: z.number().int().positive().optional(),
    category_id: z.number().int().positive().optional(),
    tag_id: z.number().int().positive().optional(),
    page: z.number().int().positive().optional(),
    per_page: z.number().int().min(1).max(100).optional(),
  }),
  get_article: z.object({ article_id: articleId }),
  list_content_options: z.object({
    include: z.array(z.enum(['authors', 'categories', 'tags', 'media'])).min(1).max(4).optional(),
    search: z.string().max(220).optional(),
    page: z.number().int().positive().optional(),
    per_page: z.number().int().min(1).max(100).optional(),
  }),
  create_article_draft: z.object({
    title: z.string().min(1).max(220),
    ...editableFields,
    idempotency_key: idempotencyKey,
  }).refine((value) => !(value.markdown && value.content_json), {
    message: 'Provide markdown or content_json, not both.',
  }),
  update_article: z.object({
    article_id: articleId,
    edit_version: editVersion,
    title: z.string().min(1).max(220).optional(),
    ...editableFields,
    idempotency_key: idempotencyKey,
  }).refine((value) => !(value.markdown && value.content_json), {
    message: 'Provide markdown or content_json, not both.',
  }),
  upload_article_media: z.object({
    article_id: articleId,
    file_path: z.string().min(1).max(4_096).describe('Local image path. The path itself is never sent to the API.'),
    alt_text: z.string().min(1).max(255),
    caption: z.string().max(1_000).optional(),
    credit: z.string().max(255).optional(),
    license: z.string().max(255).optional(),
    source_url: httpUrl('source_url must use HTTP or HTTPS.').max(2_048)
      .optional(),
    edit_version: z.number().int().nonnegative().optional(),
    idempotency_key: idempotencyKey,
  }),
  preview_article: z.object({ article_id: articleId }),
  audit_article: z.object({ article_id: articleId, idempotency_key: idempotencyKey }),
  submit_article_for_review: z.object({
    article_id: articleId,
    edit_version: editVersion,
    idempotency_key: idempotencyKey,
  }),
  schedule_article: z.object({
    article_id: articleId,
    edit_version: editVersion,
    scheduled_for: z.string().datetime({ offset: true }),
    idempotency_key: idempotencyKey,
  }),
  publish_article: z.object({
    article_id: articleId,
    edit_version: editVersion,
    idempotency_key: idempotencyKey,
  }),
} as const;

export type ToolName = keyof typeof TOOL_SCHEMAS;

type ToolDefinition = {
  title: string;
  description: string;
  annotations: ToolAnnotations;
};

const readAnnotations: ToolAnnotations = {
  readOnlyHint: true,
  destructiveHint: false,
  idempotentHint: true,
  openWorldHint: true,
};
const writeAnnotations: ToolAnnotations = {
  readOnlyHint: false,
  destructiveHint: false,
  idempotentHint: false,
  openWorldHint: true,
};

export const TOOL_DEFINITIONS: Record<ToolName, ToolDefinition> = {
  list_articles: {
    title: 'List LoLo Care articles',
    description: 'List CMS articles and readiness summaries with server-side filters and pagination.',
    annotations: readAnnotations,
  },
  get_article: {
    title: 'Get a LoLo Care article',
    description: 'Get an article, structured content, sources, workflow state, readiness, and current edit_version.',
    annotations: readAnnotations,
  },
  list_content_options: {
    title: 'List LoLo Care content options',
    description: 'List available authors, categories, tags, and managed media for article authoring.',
    annotations: readAnnotations,
  },
  create_article_draft: {
    title: 'Create a LoLo Care article draft',
    description: 'Create a draft through the Content API. Accepts validated Tiptap JSON or conservative Markdown.',
    annotations: writeAnnotations,
  },
  update_article: {
    title: 'Update a LoLo Care article',
    description: 'Update draft content and metadata with required optimistic edit_version locking.',
    annotations: writeAnnotations,
  },
  upload_article_media: {
    title: 'Upload managed article media',
    description: 'Upload a validated local image as managed article media; raw local paths are not sent to the API.',
    annotations: writeAnnotations,
  },
  preview_article: {
    title: 'Preview a LoLo Care article',
    description: 'Obtain a short-lived protected preview URL for an unpublished article.',
    annotations: readAnnotations,
  },
  audit_article: {
    title: 'Audit a LoLo Care article',
    description: 'Run and attribute the CMS content audit/readiness check without changing review approval.',
    annotations: writeAnnotations,
  },
  submit_article_for_review: {
    title: 'Submit a LoLo Care article for independent review',
    description: 'Submit an eligible draft to the existing review workflow. This tool cannot approve or review it.',
    annotations: writeAnnotations,
  },
  schedule_article: {
    title: 'Schedule a LoLo Care article for publication',
    description: 'HIGH-IMPACT WRITE: schedule an independently reviewed, ready article. Obtain explicit user approval first.',
    annotations: {
      readOnlyHint: false,
      destructiveHint: true,
      idempotentHint: true,
      openWorldHint: true,
    },
  },
  publish_article: {
    title: 'Publish a LoLo Care article',
    description: 'HIGH-IMPACT WRITE: publish an independently reviewed, ready article now. Obtain explicit user approval first.',
    annotations: {
      readOnlyHint: false,
      destructiveHint: true,
      idempotentHint: true,
      openWorldHint: true,
    },
  },
};

function contentPayload(args: Record<string, unknown>, requireKnownSources: boolean): Record<string, unknown> {
  const payload = { ...args };
  delete payload.article_id;
  delete payload.idempotency_key;
  const markdown = payload.markdown;
  delete payload.markdown;
  if (typeof markdown === 'string') {
    const converted = markdownToTiptap(markdown);
    if (requireKnownSources || Array.isArray(payload.sources)) {
      const supplied = new Set(
        Array.isArray(payload.sources)
          ? payload.sources.flatMap((item) => item && typeof item === 'object' && 'uuid' in item
            ? [String((item as { uuid: unknown }).uuid).toLowerCase()]
            : [])
          : [],
      );
      const missing = citedSourceKeys(converted).filter((key) => !supplied.has(key));
      if (missing.length) {
        throw new MarkdownConversionError(`Draft citations reference source UUIDs not supplied in sources: ${missing.join(', ')}.`);
      }
    }
    payload.content_json = converted;
  }
  return payload;
}

export async function executeTool(
  name: ToolName,
  rawArgs: unknown,
  client: ContentApiClient,
): Promise<unknown> {
  const args = TOOL_SCHEMAS[name].parse(rawArgs) as Record<string, unknown>;
  const id = (): string => encodeURIComponent(String(args.article_id));
  const key = typeof args.idempotency_key === 'string' ? args.idempotency_key : undefined;

  switch (name) {
    case 'list_articles':
      return client.get('posts', args as Record<string, string | number | boolean | null | undefined>);
    case 'get_article':
      return client.get(`posts/${id()}`);
    case 'list_content_options': {
      const query = { ...args, include: Array.isArray(args.include) ? args.include.join(',') : undefined };
      return client.get('options', query as Record<string, string | number | boolean | null | undefined>);
    }
    case 'create_article_draft':
      return client.mutate('POST', 'posts', contentPayload(args, true), key);
    case 'update_article':
      return client.mutate('PATCH', `posts/${id()}`, contentPayload(args, false), key);
    case 'upload_article_media':
      return client.uploadImage(args.article_id as string | number, String(args.file_path), {
        alt_text: String(args.alt_text),
        caption: typeof args.caption === 'string' ? args.caption : undefined,
        credit: typeof args.credit === 'string' ? args.credit : undefined,
        license: typeof args.license === 'string' ? args.license : undefined,
        source_url: typeof args.source_url === 'string' ? args.source_url : undefined,
        edit_version: typeof args.edit_version === 'number' ? args.edit_version : undefined,
      }, key);
    case 'preview_article':
      return client.get(`posts/${id()}/preview`);
    case 'audit_article':
      return client.mutate('POST', `posts/${id()}/audit`, {}, key);
    case 'submit_article_for_review':
      return client.mutate('POST', `posts/${id()}/submit`, { edit_version: args.edit_version }, key);
    case 'schedule_article':
      return client.mutate('POST', `posts/${id()}/schedule`, {
        edit_version: args.edit_version,
        scheduled_for: args.scheduled_for,
      }, key);
    case 'publish_article':
      return client.mutate('POST', `posts/${id()}/publish`, { edit_version: args.edit_version }, key);
  }
}

function result(value: unknown): CallToolResult {
  let object: Record<string, unknown>;
  if (value && typeof value === 'object' && !Array.isArray(value) && 'data' in value) {
    const call = value as { data: unknown; idempotency_key?: string };
    object = call.data && typeof call.data === 'object' && !Array.isArray(call.data)
      ? { ...call.data as Record<string, unknown> }
      : { data: call.data };
    if (call.idempotency_key) object.idempotency_key = call.idempotency_key;
  } else {
    object = value && typeof value === 'object' && !Array.isArray(value)
      ? value as Record<string, unknown>
      : { result: value };
  }
  const serialized = JSON.stringify(object);
  const concise = serialized.length <= 16_000
    ? serialized
    : `${serialized.slice(0, 16_000)}… (structuredContent contains the complete result)`;
  return {
    content: [{ type: 'text', text: concise }],
    structuredContent: object,
  };
}

function errorResult(error: unknown): CallToolResult {
  let payload: Record<string, unknown>;
  if (error instanceof ContentApiError) payload = error.toJSON();
  else if (error instanceof MarkdownConversionError) {
    payload = {
      error: 'markdown_conversion_failed',
      message: error.message,
      action: 'Use only documented Markdown structures or pass validated content_json instead.',
    };
  } else if (error instanceof z.ZodError) {
    payload = {
      error: 'invalid_tool_arguments',
      message: 'Tool arguments failed validation.',
      fields: error.issues.map((issue) => ({ path: issue.path.join('.'), message: issue.message })),
    };
  } else {
    payload = {
      error: 'connector_error',
      message: error instanceof Error ? error.message : 'The connector failed unexpectedly.',
    };
  }
  return { content: [{ type: 'text', text: JSON.stringify(payload) }], isError: true };
}

export function registerTools(server: McpServer, client: ContentApiClient): void {
  for (const name of Object.keys(TOOL_SCHEMAS) as ToolName[]) {
    const definition = TOOL_DEFINITIONS[name];
    server.registerTool(
      name,
      {
        title: definition.title,
        description: definition.description,
        inputSchema: TOOL_SCHEMAS[name],
        annotations: definition.annotations,
      },
      async (args: unknown) => {
        try {
          return result(await executeTool(name, args, client));
        } catch (error) {
          return errorResult(error);
        }
      },
    );
  }
}
