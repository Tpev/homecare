export type TiptapMark = {
  type: 'bold' | 'italic' | 'underline' | 'strike' | 'link';
  attrs?: Record<string, string>;
};

export type TiptapNode = {
  type: string;
  attrs?: Record<string, unknown>;
  content?: TiptapNode[];
  text?: string;
  marks?: TiptapMark[];
};

export type TiptapDocument = TiptapNode & {
  type: 'doc';
  content: TiptapNode[];
};

export class MarkdownConversionError extends Error {
  readonly line: number | undefined;

  constructor(message: string, line?: number) {
    super(line === undefined ? message : `Line ${line}: ${message}`);
    this.name = 'MarkdownConversionError';
    this.line = line;
  }
}

const UUID = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}';
const UUID_RE = new RegExp(`^${UUID}$`);
const CITE_RE = new RegExp(`^\\{\\{cite:(${UUID})(?:\\|([^}]+))?\\}\\}`);
const HEADING_RE = /^(#{1,6})\s+(.+?)\s*#*$/;
const LIST_RE = /^(\s*)([-+*]|(\d+)[.)])\s+(.+)$/;
const TABLE_DIVIDER_RE = /^\s*\|?\s*:?-{3,}:?\s*(?:\|\s*:?-{3,}:?\s*)+\|?\s*$/;
const CTA_RE = /^:::cta\s+label="([^"]+)"\s+url="([^"]+)"(?:\s+variant="(primary|secondary)")?\s*$/;

function lineError(message: string, index: number): never {
  throw new MarkdownConversionError(message, index + 1);
}

function safeUrl(value: string): boolean {
  if (!value || /[\x00-\x20\x7f]/.test(value)) return false;
  if (value.startsWith('#')) return true;
  if (value.startsWith('/') && !value.startsWith('//') && !value.startsWith('/\\')) return true;
  try {
    const parsed = new URL(value);
    return ['http:', 'https:', 'mailto:', 'tel:'].includes(parsed.protocol);
  } catch {
    return false;
  }
}

function sameMarks(left: TiptapMark[] | undefined, right: TiptapMark[] | undefined): boolean {
  return JSON.stringify(left ?? []) === JSON.stringify(right ?? []);
}

function appendText(nodes: TiptapNode[], text: string, marks: TiptapMark[] = []): void {
  if (!text) return;
  const previous = nodes.at(-1);
  if (previous?.type === 'text' && sameMarks(previous.marks, marks)) {
    previous.text = `${previous.text ?? ''}${text}`;
    return;
  }
  nodes.push({ type: 'text', text, ...(marks.length ? { marks } : {}) });
}

function addMark(nodes: TiptapNode[], mark: TiptapMark): TiptapNode[] {
  return nodes.map((node) => {
    if (node.type !== 'text') {
      throw new MarkdownConversionError('Citations cannot be placed inside formatted text or links.');
    }
    return { ...node, marks: [...(node.marks ?? []), mark] };
  });
}

export function parseInline(value: string): TiptapNode[] {
  const nodes: TiptapNode[] = [];
  let plain = '';
  let position = 0;

  const flush = (): void => {
    appendText(nodes, plain);
    plain = '';
  };

  while (position < value.length) {
    const rest = value.slice(position);

    if (rest.startsWith('\\') && rest.length > 1 && /[\\`*_[\]{}()~|>#+.!-]/.test(rest[1] ?? '')) {
      plain += rest[1];
      position += 2;
      continue;
    }

    const citation = CITE_RE.exec(rest);
    if (citation) {
      flush();
      const sourceKey = citation[1];
      const label = citation[2]?.trim();
      nodes.push({
        type: 'citation',
        attrs: {
          sourceKey,
          sourceId: null,
          ...(label ? { label } : {}),
        },
      });
      position += citation[0].length;
      continue;
    }

    if (rest.startsWith('{{cite:')) {
      throw new MarkdownConversionError('Invalid citation. Use {{cite:UUID}} or {{cite:UUID|label}} with a stable source UUID.');
    }

    const link = /^\[([^\]\n]+)]\(([^)\s]+)\)/.exec(rest);
    if (link) {
      if (!safeUrl(link[2] ?? '')) throw new MarkdownConversionError(`Unsafe or invalid link URL: ${link[2] ?? ''}`);
      flush();
      nodes.push(...addMark(parseInline(link[1] ?? ''), {
        type: 'link',
        attrs: { href: link[2] ?? '' },
      }));
      position += link[0].length;
      continue;
    }

    const formats: Array<{ token: string; mark: TiptapMark['type'] }> = [
      { token: '**', mark: 'bold' },
      { token: '__', mark: 'underline' },
      { token: '~~', mark: 'strike' },
      { token: '*', mark: 'italic' },
      { token: '_', mark: 'italic' },
    ];
    const format = formats.find(({ token }) => rest.startsWith(token));
    if (format) {
      const end = rest.indexOf(format.token, format.token.length);
      if (end < 0) throw new MarkdownConversionError(`Unclosed ${format.token} formatting delimiter.`);
      const inner = rest.slice(format.token.length, end);
      if (!inner.trim()) throw new MarkdownConversionError('Empty formatting is not supported.');
      flush();
      nodes.push(...addMark(parseInline(inner), { type: format.mark }));
      position += end + format.token.length;
      continue;
    }

    if (rest.startsWith('![')) throw new MarkdownConversionError('Markdown images are unsupported. Upload managed media with upload_article_media.');
    if (rest.startsWith('[') && /\]\(/.test(rest)) throw new MarkdownConversionError('Malformed Markdown link. Use [label](safe-url).');

    plain += value[position];
    position += 1;
  }
  flush();
  return nodes;
}

function paragraph(text: string): TiptapNode {
  const content = parseInline(text.trim());
  return { type: 'paragraph', ...(content.length ? { content } : {}) };
}

function splitTableRow(line: string): string[] {
  let value = line.trim();
  if (value.startsWith('|')) value = value.slice(1);
  if (value.endsWith('|') && !value.endsWith('\\|')) value = value.slice(0, -1);
  const cells: string[] = [];
  let cell = '';
  for (let index = 0; index < value.length; index += 1) {
    const char = value[index];
    if (char === '\\' && value[index + 1] === '|') {
      cell += '|';
      index += 1;
    } else if (char === '|') {
      cells.push(cell.trim());
      cell = '';
    } else {
      cell += char;
    }
  }
  cells.push(cell.trim());
  return cells;
}

function tableCell(type: 'tableHeader' | 'tableCell', value: string): TiptapNode {
  return { type, content: [paragraph(value)] };
}

function startsBlock(lines: string[], index: number): boolean {
  const line = lines[index] ?? '';
  const next = lines[index + 1] ?? '';
  return HEADING_RE.test(line)
    || LIST_RE.test(line)
    || /^>/.test(line)
    || /^(?:-{3,}|\*{3,}|_{3,})\s*$/.test(line)
    || CTA_RE.test(line)
    || (line.includes('|') && TABLE_DIVIDER_RE.test(next));
}

function parseBlocks(lines: string[], offset = 0): TiptapNode[] {
  const output: TiptapNode[] = [];
  let index = 0;

  while (index < lines.length) {
    const line = lines[index] ?? '';
    if (!line.trim()) {
      index += 1;
      continue;
    }

    const heading = HEADING_RE.exec(line);
    if (heading) {
      const level = heading[1]?.length ?? 0;
      if (level < 2 || level > 4) lineError('Article headings must use h2, h3, or h4.', offset + index);
      const text = heading[2]?.trim() ?? '';
      if (!text) lineError('Heading text is required.', offset + index);
      output.push({ type: 'heading', attrs: { level }, content: parseInline(text) });
      index += 1;
      continue;
    }

    const cta = CTA_RE.exec(line);
    if (cta) {
      const label = cta[1]?.trim() ?? '';
      const url = cta[2]?.trim() ?? '';
      if (!label) lineError('CTA label is required.', offset + index);
      if (!safeUrl(url)) lineError('CTA URL must be a safe HTTP(S), mailto, tel, root-relative, or fragment URL.', offset + index);
      output.push({ type: 'cta', attrs: { label, url, variant: cta[3] ?? 'primary' } });
      index += 1;
      continue;
    }
    if (line.startsWith(':::')) lineError('Unsupported directive. CTA syntax is :::cta label="…" url="…" variant="primary|secondary".', offset + index);

    if (/^(?:-{3,}|\*{3,}|_{3,})\s*$/.test(line)) {
      output.push({ type: 'horizontalRule' });
      index += 1;
      continue;
    }

    if (/^>\s*\[![A-Za-z]+]/.test(line)) {
      const first = /^>\s*\[!(NOTE|TIP|WARNING|IMPORTANT)]\s*(.*)$/i.exec(line);
      if (!first) lineError('Callouts support only NOTE, TIP, WARNING, or IMPORTANT.', offset + index);
      const body: string[] = [];
      let cursor = index + 1;
      while (cursor < lines.length && /^>/.test(lines[cursor] ?? '')) {
        body.push((lines[cursor] ?? '').replace(/^> ?/, ''));
        cursor += 1;
      }
      const content = parseBlocks(body, offset + index + 1);
      if (!content.length) lineError('A callout must contain body content on following quoted lines.', offset + index);
      output.push({
        type: 'callout',
        attrs: { kind: first[1]?.toLowerCase(), title: first[2]?.trim() ?? '' },
        content,
      });
      index = cursor;
      continue;
    }

    if (/^>/.test(line)) {
      const body: string[] = [];
      let cursor = index;
      while (cursor < lines.length && /^>/.test(lines[cursor] ?? '')) {
        body.push((lines[cursor] ?? '').replace(/^> ?/, ''));
        cursor += 1;
      }
      const content = parseBlocks(body, offset + index);
      if (!content.length) lineError('A blockquote cannot be empty.', offset + index);
      output.push({ type: 'blockquote', content });
      index = cursor;
      continue;
    }

    if (line.includes('|') && TABLE_DIVIDER_RE.test(lines[index + 1] ?? '')) {
      const header = splitTableRow(line);
      const rows: string[][] = [];
      let cursor = index + 2;
      while (cursor < lines.length && (lines[cursor] ?? '').includes('|') && (lines[cursor] ?? '').trim()) {
        rows.push(splitTableRow(lines[cursor] ?? ''));
        cursor += 1;
      }
      if (header.length < 2) lineError('Tables must contain at least two columns.', offset + index);
      if (!rows.length) lineError('Tables must contain at least one body row.', offset + index);
      if (rows.some((row) => row.length !== header.length)) lineError('Every table row must have the same number of cells.', offset + index);
      output.push({
        type: 'table',
        content: [
          { type: 'tableRow', content: header.map((cell) => tableCell('tableHeader', cell)) },
          ...rows.map((row) => ({ type: 'tableRow', content: row.map((cell) => tableCell('tableCell', cell)) })),
        ],
      });
      index = cursor;
      continue;
    }

    const list = LIST_RE.exec(line);
    if (list) {
      if ((list[1] ?? '').length > 0) lineError('Nested or indented lists are unsupported; use a flat list.', offset + index);
      const ordered = Boolean(list[3]);
      const items: TiptapNode[] = [];
      const start = ordered ? Number(list[3]) : 1;
      let cursor = index;
      while (cursor < lines.length) {
        const item = LIST_RE.exec(lines[cursor] ?? '');
        if (!item) break;
        if ((item[1] ?? '').length > 0) lineError('Nested or indented lists are unsupported; use a flat list.', offset + cursor);
        if (Boolean(item[3]) !== ordered) break;
        if (/^\[[ xX]]\s/.test(item[4] ?? '')) lineError('Task lists are unsupported.', offset + cursor);
        items.push({ type: 'listItem', content: [paragraph(item[4] ?? '')] });
        cursor += 1;
      }
      output.push({ type: ordered ? 'orderedList' : 'bulletList', ...(ordered ? { attrs: { start } } : {}), content: items });
      index = cursor;
      continue;
    }

    if (/^\s+/.test(line)) lineError('Indented blocks are unsupported.', offset + index);
    const paragraphLines = [line.trim()];
    let cursor = index + 1;
    while (cursor < lines.length && (lines[cursor] ?? '').trim() && !startsBlock(lines, cursor)) {
      if (/^\s+/.test(lines[cursor] ?? '')) lineError('Indented blocks are unsupported.', offset + cursor);
      paragraphLines.push((lines[cursor] ?? '').trim());
      cursor += 1;
    }
    output.push(paragraph(paragraphLines.join(' ')));
    index = cursor;
  }
  return output;
}

function parseFaq(lines: string[], start: number): { node: TiptapNode; next: number } {
  const items: TiptapNode[] = [];
  let index = start;
  while (index < lines.length) {
    if (!lines[index]?.trim()) {
      index += 1;
      continue;
    }
    const heading = HEADING_RE.exec(lines[index] ?? '');
    if (heading?.[1]?.length === 2) break;
    if (heading?.[1]?.length !== 3) lineError('Each FAQ entry must begin with an h3 question.', index);
    const question = heading[2]?.trim() ?? '';
    if (!question || /[*_~[`{]/.test(question)) lineError('FAQ questions must be plain text.', index);
    const answerStart = index + 1;
    let cursor = answerStart;
    while (cursor < lines.length) {
      const nextHeading = HEADING_RE.exec(lines[cursor] ?? '');
      if (nextHeading && [2, 3].includes(nextHeading[1]?.length ?? 0)) break;
      cursor += 1;
    }
    const content = parseBlocks(lines.slice(answerStart, cursor), answerStart);
    if (!content.length) lineError('FAQ answers cannot be empty.', index);
    items.push({ type: 'faqItem', attrs: { question }, content });
    index = cursor;
  }
  if (!items.length) lineError('An FAQ section must contain at least one h3 question and answer.', start - 1);
  return { node: { type: 'faq', content: items }, next: index };
}

function validateUnsupported(markdown: string, lines: string[]): void {
  if (markdown.length > 500_000) throw new MarkdownConversionError('Markdown exceeds the 500,000 character limit.');
  if (/`/.test(markdown)) throw new MarkdownConversionError('Inline code and fenced code blocks are unsupported.');
  if (/<(?:!--[\s\S]*?--|\/?[A-Za-z][^>]*|![A-Z][^>]*)>/i.test(markdown)) throw new MarkdownConversionError('Raw HTML is unsupported.');
  if (/\[\^[^\]]+]/.test(markdown)) throw new MarkdownConversionError('Markdown footnotes are unsupported. Use {{cite:UUID}} citations.');
  if (/^\s*\[[^\]]+]:\s*\S+/m.test(markdown)) throw new MarkdownConversionError('Reference-style links are unsupported. Use inline [label](url) links.');
  if (/^\s*```|^\s*~~~/m.test(markdown)) throw new MarkdownConversionError('Fenced code blocks are unsupported.');
  lines.forEach((line, index) => {
    if (/^#{1}\s/.test(line) || /^#{5,6}\s/.test(line)) lineError('Article headings must use h2, h3, or h4.', index);
    if (line.trim() && index + 1 < lines.length && /^\s*(?:=+|-{3,})\s*$/.test(lines[index + 1] ?? '')) {
      lineError('Setext headings are unsupported; use ##, ###, or #### headings.', index);
    }
    if (/^\s*[-+*]\s+\[[ xX]]\s/.test(line)) lineError('Task lists are unsupported.', index);
    if (/^\s*:\s+/.test(line)) lineError('Definition lists are unsupported.', index);
    if (/^\s*!\[/.test(line)) lineError('Markdown images are unsupported. Upload managed media with upload_article_media.', index);
  });
}

export function markdownToTiptap(markdown: string): TiptapDocument {
  const normalized = markdown.replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n').trim();
  if (!normalized) throw new MarkdownConversionError('Markdown content cannot be empty.');
  const lines = normalized.split('\n');
  validateUnsupported(normalized, lines);

  const content: TiptapNode[] = [];
  let sectionStart = 0;
  let index = 0;
  while (index < lines.length) {
    const heading = HEADING_RE.exec(lines[index] ?? '');
    const isFaq = heading?.[1]?.length === 2 && /^(?:faq|frequently asked questions)$/i.test(heading[2]?.trim() ?? '');
    if (!isFaq) {
      index += 1;
      continue;
    }
    content.push(...parseBlocks(lines.slice(sectionStart, index), sectionStart));
    const faq = parseFaq(lines, index + 1);
    content.push(faq.node);
    sectionStart = faq.next;
    index = faq.next;
  }
  content.push(...parseBlocks(lines.slice(sectionStart), sectionStart));
  if (!content.length) throw new MarkdownConversionError('Markdown did not produce any supported content.');
  return { type: 'doc', content };
}

export function citedSourceKeys(document: TiptapDocument): string[] {
  const found = new Set<string>();
  const visit = (node: TiptapNode): void => {
    if (node.type === 'citation') {
      const sourceKey = node.attrs?.sourceKey;
      if (typeof sourceKey === 'string' && UUID_RE.test(sourceKey)) found.add(sourceKey.toLowerCase());
    }
    node.content?.forEach(visit);
  };
  visit(document);
  return [...found];
}
