import { describe, expect, it } from 'vitest';

import { citedSourceKeys, markdownToTiptap } from '../src/markdown.js';

const UUID = '123e4567-e89b-42d3-a456-426614174000';

describe('markdownToTiptap', () => {
  it('converts supported blocks, marks, links, citations, and a CTA deterministically', () => {
    const markdown = [
      '## A useful guide',
      '',
      `A **bold**, *gentle*, __clear__, ~~old~~ [safe link](https://example.test) with evidence {{cite:${UUID}|1}}.`,
      '',
      '- First item',
      '- Second item',
      '',
      '3. Third',
      '4. Fourth',
      '',
      '> A short quote',
      '',
      '---',
      '',
      ':::cta label="Find care" url="/register" variant="secondary"',
    ].join('\n');
    const first = markdownToTiptap(markdown);
    expect(markdownToTiptap(markdown)).toEqual(first);
    expect(first.content.map((node) => node.type)).toEqual([
      'heading', 'paragraph', 'bulletList', 'orderedList', 'blockquote', 'horizontalRule', 'cta',
    ]);
    expect(first.content[3]?.attrs).toEqual({ start: 3 });
    expect(first.content[6]?.attrs).toEqual({ label: 'Find care', url: '/register', variant: 'secondary' });
    expect(citedSourceKeys(first)).toEqual([UUID]);
  });

  it('converts callouts, tables, and FAQs to custom Tiptap nodes', () => {
    const document = markdownToTiptap([
      '> [!IMPORTANT] Safety first',
      '> Keep the plan visible.',
      '>',
      '> Call for help when needed.',
      '',
      '| Need | Action |',
      '| --- | --- |',
      '| Food | Prepare |',
      '',
      '## Frequently asked questions',
      '',
      '### Can I make changes?',
      'Yes, before submission.',
      '',
      '### Who reviews it?',
      'An independent reviewer.',
    ].join('\n'));
    expect(document.content.map((node) => node.type)).toEqual(['callout', 'table', 'faq']);
    expect(document.content[0]?.attrs).toEqual({ kind: 'important', title: 'Safety first' });
    expect(document.content[1]?.content).toHaveLength(2);
    expect(document.content[2]?.content).toHaveLength(2);
    expect(document.content[2]?.content?.[0]?.attrs).toEqual({ question: 'Can I make changes?' });
  });

  it.each([
    ['# H1', 'h2, h3, or h4'],
    ['##### H5', 'h2, h3, or h4'],
    ['Setext title\n---', 'Setext headings'],
    ['Text with `code`.', 'code'],
    ['<script>alert(1)</script>', 'Raw HTML'],
    ['<!-- hidden -->', 'Raw HTML'],
    ['A footnote[^1].\n\n[^1]: nope', 'footnotes'],
    ['![remote](https://example.test/a.png)', 'images'],
    ['- [ ] task', 'Task lists'],
    ['Term\n: Definition', 'Definition lists'],
    ['- root\n  - child', 'Nested'],
    ['[bad](javascript:alert(1))', 'Unsafe'],
    ['{{cite:not-a-uuid}}', 'Invalid citation'],
    [':::unknown value="x"', 'Unsupported directive'],
  ])('rejects unsupported input %#', (markdown, expected) => {
    expect(() => markdownToTiptap(markdown)).toThrow(expected);
  });

  it('rejects malformed tables and FAQs rather than dropping them', () => {
    expect(() => markdownToTiptap('| A | B |\n| --- | --- |')).toThrow('body row');
    expect(() => markdownToTiptap('## FAQ\n\nAnswer without a question')).toThrow('h3 question');
  });
});
