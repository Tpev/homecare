import { basename, extname } from 'node:path';
import { readFile, stat } from 'node:fs/promises';
import { createHash, createHmac, randomUUID } from 'node:crypto';

import type { ContentApiConfig } from './config.js';

export type Fetch = typeof fetch;
export type QueryValue = string | number | boolean | null | undefined;

export type ApiCallResult = {
  data: unknown;
  idempotency_key?: string;
};

type ApiErrorBody = {
  message?: unknown;
  code?: unknown;
  errors?: unknown;
  error?: unknown;
};

export class ContentApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly fieldErrors: Record<string, string[]>;
  readonly details: unknown;

  constructor(options: {
    status: number;
    code: string;
    message: string;
    fieldErrors?: Record<string, string[]>;
    details?: unknown;
  }) {
    super(options.message);
    this.name = 'ContentApiError';
    this.status = options.status;
    this.code = options.code;
    this.fieldErrors = options.fieldErrors ?? {};
    this.details = options.details;
  }

  toJSON(): Record<string, unknown> {
    const result: Record<string, unknown> = {
      error: this.code,
      message: this.message,
      status: this.status,
    };
    if (Object.keys(this.fieldErrors).length > 0) result.fields = this.fieldErrors;
    if (this.details !== undefined) result.details = this.details;
    if (this.status === 409) {
      result.action = 'Fetch the article again, merge changes, and retry with its latest edit_version.';
    } else if (this.status === 422) {
      result.action = 'Correct the listed fields and retry with a new idempotency_key.';
    } else if (this.status === 401 || this.status === 403) {
      result.action = 'Ask an administrator to verify the token actor, expiry, revocation state, and required scope.';
    } else if (this.status === 429) {
      result.action = 'Wait for the API rate-limit window to reset before retrying.';
    }
    return result;
  }
}

function fieldErrors(value: unknown): Record<string, string[]> {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return {};
  const result: Record<string, string[]> = {};
  for (const [field, messages] of Object.entries(value)) {
    if (Array.isArray(messages)) result[field] = messages.map(String);
    else if (typeof messages === 'string') result[field] = [messages];
  }
  return result;
}

function errorParts(body: ApiErrorBody, fallback: string): {
  code: string;
  message: string;
  fields: Record<string, string[]>;
  details?: unknown;
} {
  const nested = body.error && typeof body.error === 'object' && !Array.isArray(body.error)
    ? body.error as Record<string, unknown>
    : undefined;
  const code = String(nested?.code ?? body.code ?? (typeof body.error === 'string' ? body.error : 'content_api_error'));
  const message = String(nested?.message ?? body.message ?? fallback);
  const fields = fieldErrors(nested?.errors ?? nested?.fields ?? body.errors);
  const details = nested?.details;
  return details === undefined ? { code, message, fields } : { code, message, fields, details };
}

async function parseResponse(response: Response): Promise<unknown> {
  const contentType = response.headers.get('content-type') ?? '';
  if (response.status === 204) return null;
  if (contentType.includes('application/json')) {
    try {
      return await response.json();
    } catch {
      return {};
    }
  }
  const text = await response.text();
  return text.length <= 2_000 ? text : `${text.slice(0, 2_000)}…`;
}

function detectedImageType(bytes: Uint8Array): { mime: string; extension: string } | undefined {
  if (bytes.length >= 8 && bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47) {
    return { mime: 'image/png', extension: '.png' };
  }
  if (bytes.length >= 3 && bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff) {
    return { mime: 'image/jpeg', extension: '.jpg' };
  }
  if (bytes.length >= 12 && String.fromCharCode(...bytes.slice(0, 4)) === 'RIFF' && String.fromCharCode(...bytes.slice(8, 12)) === 'WEBP') {
    return { mime: 'image/webp', extension: '.webp' };
  }
  if (bytes.length >= 6) {
    const signature = String.fromCharCode(...bytes.slice(0, 6));
    if (signature === 'GIF87a' || signature === 'GIF89a') return { mime: 'image/gif', extension: '.gif' };
  }
  return undefined;
}

export class ContentApiClient {
  readonly #config: ContentApiConfig;
  readonly #fetch: Fetch;

  constructor(config: ContentApiConfig, fetchImplementation: Fetch = fetch) {
    this.#config = config;
    this.#fetch = fetchImplementation;
  }

  async get(path: string, query: Record<string, QueryValue> = {}): Promise<ApiCallResult> {
    const url = this.#url(path);
    for (const [key, value] of Object.entries(query)) {
      if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value));
    }
    return this.#request(url, { method: 'GET' });
  }

  async mutate(
    method: 'POST' | 'PATCH',
    path: string,
    body: Record<string, unknown>,
    idempotencyKey?: string,
  ): Promise<ApiCallResult> {
    const key = idempotencyKey?.trim() || randomUUID();
    const result = await this.#request(this.#url(path), {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Idempotency-Key': key,
      },
      body: JSON.stringify(body),
    });
    return { ...result, idempotency_key: key };
  }

  async uploadImage(
    articleId: string | number,
    filePath: string,
    fields: Record<string, string | number | undefined>,
    idempotencyKey?: string,
  ): Promise<ApiCallResult> {
    const info = await stat(filePath);
    if (!info.isFile()) throw new Error('file_path must identify a regular file.');
    if (info.size <= 0 || info.size > 20 * 1024 * 1024) throw new Error('The image must be between 1 byte and 20 MiB.');

    const bytes = await readFile(filePath);
    return this.uploadImageBytes(articleId, basename(filePath), bytes, fields, idempotencyKey);
  }

  async uploadImageBase64(
    articleId: string | number,
    filename: string,
    encodedBytes: string,
    fields: Record<string, string | number | undefined>,
    idempotencyKey?: string,
  ): Promise<ApiCallResult> {
    if (!/^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/.test(encodedBytes)) {
      throw new Error('file_base64 must be canonical standard Base64 without whitespace or a data-URL prefix.');
    }
    const bytes = Buffer.from(encodedBytes, 'base64');
    if (bytes.toString('base64') !== encodedBytes) {
      throw new Error('file_base64 is not a canonical Base64 encoding.');
    }
    return this.uploadImageBytes(articleId, filename, bytes, fields, idempotencyKey);
  }

  async uploadImageBytes(
    articleId: string | number,
    filename: string,
    bytes: Uint8Array,
    fields: Record<string, string | number | undefined>,
    idempotencyKey?: string,
  ): Promise<ApiCallResult> {
    if (basename(filename) !== filename || /[\\/\0]/.test(filename) || filename.length > 255) {
      throw new Error('filename must be a safe basename no longer than 255 characters.');
    }
    if (bytes.byteLength <= 0 || bytes.byteLength > 20 * 1024 * 1024) {
      throw new Error('The image must be between 1 byte and 20 MiB.');
    }
    const imageType = detectedImageType(bytes);
    if (!imageType) throw new Error('Unsupported image content. Use a real PNG, JPEG, WebP, or GIF file.');
    const suppliedExtension = extname(filename).toLowerCase();
    const jpegExtensions = ['.jpg', '.jpeg'];
    if (suppliedExtension && suppliedExtension !== imageType.extension
      && !(imageType.mime === 'image/jpeg' && jpegExtensions.includes(suppliedExtension))) {
      throw new Error('The image filename extension does not match its file content.');
    }

    const key = idempotencyKey?.trim() || randomUUID();
    const form = new FormData();
    const imageBuffer = new ArrayBuffer(bytes.byteLength);
    new Uint8Array(imageBuffer).set(bytes);
    form.set('file', new Blob([imageBuffer], { type: imageType.mime }), filename);
    for (const [name, value] of Object.entries(fields)) {
      if (value !== undefined) form.set(name, String(value));
    }

    const result = await this.#request(this.#url(`posts/${encodeURIComponent(String(articleId))}/media`), {
      method: 'POST',
      headers: { 'Idempotency-Key': key },
      body: form,
    }, 60_000);
    return { ...result, idempotency_key: key };
  }

  #url(path: string): URL {
    if (path.includes('://') || /[\\?#]/.test(path) || path.split('/').some((segment) => segment === '.' || segment === '..')) {
      throw new Error('API paths must be safe relative paths without traversal, query, or fragment components.');
    }
    return new URL(path.replace(/^\/+/, ''), this.#config.apiBaseUrl);
  }

  async #request(url: URL, init: RequestInit, timeoutMs = 30_000): Promise<ApiCallResult> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('Authorization', `Bearer ${this.#config.token}`);
    headers.set('User-Agent', '@lolo-care/content-mcp/1.0.0');
    if (this.#config.delegation) {
      const now = Math.floor(Date.now() / 1000);
      const payload = Buffer.from(JSON.stringify({
        v: 1,
        oauth_token_id: this.#config.delegation.oauthTokenId,
        method: String(init.method ?? 'GET').toUpperCase(),
        path: url.pathname,
        iat: now,
        exp: now + 30,
        nonce: randomUUID(),
      })).toString('base64url');
      const key = createHash('sha256').update(this.#config.token).digest();
      headers.set('X-LoLo-MCP-Delegation', payload);
      headers.set('X-LoLo-MCP-Signature', createHmac('sha256', key).update(payload).digest('base64url'));
    }

    let response: Response;
    try {
      response = await this.#fetch(url, {
        ...init,
        headers,
        redirect: 'error',
        signal: AbortSignal.timeout(timeoutMs),
      });
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unknown network failure';
      throw new ContentApiError({
        status: 0,
        code: 'content_api_unreachable',
        message: `The LoLo Content API could not be reached: ${message}`,
      });
    }

    const body = await parseResponse(response);
    if (!response.ok) {
      const parts = errorParts(
        body && typeof body === 'object' && !Array.isArray(body) ? body as ApiErrorBody : {},
        `The Content API returned HTTP ${response.status}.`,
      );
      throw new ContentApiError({
        status: response.status,
        code: parts.code,
        message: parts.message,
        fieldErrors: parts.fields,
        ...(parts.details === undefined ? {} : { details: parts.details }),
      });
    }
    return { data: body };
  }
}
