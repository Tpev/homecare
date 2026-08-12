import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Underline from '@tiptap/extension-underline';
import { TableKit } from '@tiptap/extension-table';

const ManagedImage = Node.create({
    name: 'image',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes() {
        return {
            assetId: { default: null },
            src: { default: '' },
            alt: { default: '' },
            title: { default: '' },
            caption: { default: '' },
        };
    },
    parseHTML() {
        return [{ tag: 'figure[data-media-asset]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['figure', { 'data-media-asset': HTMLAttributes.assetId, class: 'content-editor-image' },
            ['img', {
                src: HTMLAttributes.src,
                alt: HTMLAttributes.alt,
                title: HTMLAttributes.title || null,
                draggable: 'false',
            }],
            ...(HTMLAttributes.caption ? [['figcaption', {}, HTMLAttributes.caption]] : []),
        ];
    },
});

const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes() {
        return { kind: { default: 'note' }, title: { default: '' } };
    },
    parseHTML() {
        return [{ tag: 'aside[data-callout]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['aside', mergeAttributes(HTMLAttributes, { 'data-callout': HTMLAttributes.kind, class: 'content-editor-callout' }), 0];
    },
});

const ContentCta = Node.create({
    name: 'cta',
    group: 'block',
    atom: true,
    addAttributes() {
        return { label: { default: 'Learn more' }, url: { default: '/' }, variant: { default: 'primary' } };
    },
    parseHTML() {
        return [{ tag: 'div[data-content-cta]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['div', { 'data-content-cta': '', class: 'content-editor-cta' },
            ['a', { href: HTMLAttributes.url }, HTMLAttributes.label],
        ];
    },
});

const Citation = Node.create({
    name: 'citation',
    group: 'inline',
    inline: true,
    atom: true,
    addAttributes() {
        return { sourceKey: { default: '' }, sourceId: { default: null }, label: { default: '1' } };
    },
    parseHTML() {
        return [{ tag: 'sup[data-source-id]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['sup', {
            'data-source-key': HTMLAttributes.sourceKey || null,
            'data-source-id': HTMLAttributes.sourceId || null,
            class: 'content-editor-citation',
        }, `[${HTMLAttributes.label}]`];
    },
});

const FaqItem = Node.create({
    name: 'faqItem',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes() {
        return { question: { default: 'Question' } };
    },
    parseHTML() {
        return [{ tag: 'details[data-faq-item]' }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['details', { 'data-faq-item': '', class: 'content-editor-faq-item', open: 'open' },
            ['summary', {}, HTMLAttributes.question],
            ['div', {}, 0],
        ];
    },
});

const Faq = Node.create({
    name: 'faq',
    group: 'block',
    content: 'faqItem+',
    defining: true,
    parseHTML() {
        return [{ tag: 'section[data-faq]' }];
    },
    renderHTML() {
        return ['section', { 'data-faq': '', class: 'content-editor-faq' }, 0];
    },
});

export function createContentEditorController({ initialDocument }) {
        let editor = null;
        let autosaveTimer = null;
        let insertImageListener = null;
        let loadListener = null;

        return {
            ready: false,
            saveState: 'Saved',

            initialize() {
                editor = new Editor({
                    element: this.$refs.surface,
                    content: initialDocument,
                    autofocus: false,
                    enableContentCheck: true,
                    extensions: [
                        StarterKit.configure({
                            heading: { levels: [2, 3, 4] },
                            link: false,
                            underline: false,
                            code: false,
                            codeBlock: false,
                        }),
                        Link.configure({
                            openOnClick: false,
                            autolink: true,
                            defaultProtocol: 'https',
                            protocols: ['mailto', 'tel'],
                        }),
                        Underline,
                        ManagedImage,
                        TableKit.configure({ table: { resizable: true } }),
                        Callout,
                        ContentCta,
                        Citation,
                        Faq,
                        FaqItem,
                    ],
                    editorProps: {
                        attributes: {
                            class: 'content-editor-prose',
                            spellcheck: 'true',
                            'aria-label': 'Article body editor',
                        },
                    },
                    onCreate: () => {
                        this.ready = true;
                    },
                    onUpdate: ({ editor: currentEditor }) => {
                        this.saveState = 'Unsaved changes';
                        this.$wire.set('form.content_json', currentEditor.getJSON(), false);
                        window.clearTimeout(autosaveTimer);
                        autosaveTimer = window.setTimeout(async () => {
                            this.saveState = 'Autosaving…';
                            try {
                                await this.$wire.autosave();
                                this.saveState = 'Saved';
                            } catch {
                                this.saveState = 'Save failed';
                            }
                        }, 3500);
                    },
                    onContentError: () => {
                        this.saveState = 'Unsupported pasted content removed';
                    },
                });

                insertImageListener = (event) => {
                    const asset = event.detail;
                    editor?.chain().focus().insertContent({
                        type: 'image',
                        attrs: {
                            assetId: Number(asset.id),
                            src: asset.url,
                            alt: asset.alt ?? '',
                            title: '',
                            caption: asset.caption ?? '',
                        },
                    }).run();
                };
                loadListener = (event) => {
                    const document = event.detail?.document ?? event.detail?.[0]?.document;
                    if (document && editor) {
                        editor.commands.setContent(document, { emitUpdate: false });
                    }
                };
                window.addEventListener('content-editor-insert-image', insertImageListener);
                window.addEventListener('content-editor-load', loadListener);

                this.$cleanup(() => {
                    window.clearTimeout(autosaveTimer);
                    window.removeEventListener('content-editor-insert-image', insertImageListener);
                    window.removeEventListener('content-editor-load', loadListener);
                    editor?.destroy();
                });
            },

            active(name, attributes = {}) {
                return editor?.isActive(name, attributes) ?? false;
            },

            command(name, value = null) {
                if (!editor) return;
                const chain = editor.chain().focus();
                const commands = {
                    bold: () => chain.toggleBold().run(),
                    italic: () => chain.toggleItalic().run(),
                    underline: () => chain.toggleUnderline().run(),
                    strike: () => chain.toggleStrike().run(),
                    paragraph: () => chain.setParagraph().run(),
                    heading2: () => chain.toggleHeading({ level: 2 }).run(),
                    heading3: () => chain.toggleHeading({ level: 3 }).run(),
                    heading4: () => chain.toggleHeading({ level: 4 }).run(),
                    bulletList: () => chain.toggleBulletList().run(),
                    orderedList: () => chain.toggleOrderedList().run(),
                    blockquote: () => chain.toggleBlockquote().run(),
                    horizontalRule: () => chain.setHorizontalRule().run(),
                    undo: () => chain.undo().run(),
                    redo: () => chain.redo().run(),
                    table: () => chain.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
                    addRow: () => chain.addRowAfter().run(),
                    addColumn: () => chain.addColumnAfter().run(),
                    deleteTable: () => chain.deleteTable().run(),
                };
                commands[name]?.(value);
            },

            setLink() {
                if (!editor) return;
                const previous = editor.getAttributes('link').href ?? '';
                const href = window.prompt('Link URL', previous);
                if (href === null) return;
                if (href.trim() === '') {
                    editor.chain().focus().extendMarkRange('link').unsetLink().run();
                    return;
                }
                editor.chain().focus().extendMarkRange('link').setLink({ href: href.trim() }).run();
            },

            insertCallout() {
                const title = window.prompt('Callout title', 'Important') ?? 'Important';
                editor?.chain().focus().insertContent({
                    type: 'callout',
                    attrs: { kind: 'important', title },
                    content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Add the supporting detail here.' }] }],
                }).run();
            },

            insertCta() {
                const label = window.prompt('Button label', 'Find care with LoLo') ?? 'Find care with LoLo';
                const url = window.prompt('Destination URL', '/register') ?? '/register';
                editor?.chain().focus().insertContent({ type: 'cta', attrs: { label, url, variant: 'primary' } }).run();
            },

            insertCitation() {
                const sourceNumber = Number(window.prompt('Source number', '1') ?? 1);
                const sources = [...document.querySelectorAll('[data-editor-source-key]')];
                const sourceKey = sources[sourceNumber - 1]?.dataset.editorSourceKey ?? '';
                if (!Number.isInteger(sourceNumber) || sourceNumber < 1 || !sourceKey) {
                    this.saveState = 'Choose an existing source number';
                    return;
                }
                editor?.chain().focus().insertContent({
                    type: 'citation',
                    attrs: { sourceKey, sourceId: null, label: String(sourceNumber) },
                }).run();
            },

            insertFaq() {
                const question = window.prompt('FAQ question', 'What should families know?') ?? 'What should families know?';
                editor?.chain().focus().insertContent({
                    type: 'faq',
                    content: [{
                        type: 'faqItem',
                        attrs: { question },
                        content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Write a concise, direct answer.' }] }],
                    }],
                }).run();
            },
        };
}
