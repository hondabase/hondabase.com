// Hondabase rich-text editor. TipTap is the single path every article edit flows through, so its
// Markdown bridge must be lossless: articles still commit raw Markdown (frontmatter + body) to the
// repo. The extension set here is byte-for-byte the one validated by scripts/tiptap-roundtrip.mjs
// over the whole corpus (98.8% idempotent, GFM tables intact) - do not change it without re-running
// that harness, or the round-trip guarantee no longer holds.
//
// Registered as an Alpine component on `alpine:init` (Livewire ships Alpine) so it survives
// `wire:navigate`, like the article find-in-page widget. The toolbar + canvas sit under `wire:ignore`
// so Livewire never morphs TipTap's DOM; the component serializes back to Markdown and hands it to
// its Livewire parent (debounced) so the server-rendered live preview stays in sync. This entry is
// loaded only on /new and /edit, so readers never download it.

import { Editor } from '@tiptap/core';
import { createEditorExtensions } from './editor-extensions';

function debounce(fn, wait) {
    let t;
    return function (...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), wait);
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('tiptapEditor', (wireProp = 'bodyMarkdown') => ({
        editor: null,
        // Bumped on every transaction/selection change so Alpine re-evaluates the toolbar's
        // active-state bindings (TipTap's state is not otherwise reactive to Alpine).
        version: 0,

        init() {
            // Read the body from Livewire so the (changing) Markdown is never baked into a DOM attr.
            const initial = this.$wire.get(wireProp) ?? '';
            this.editor = new Editor({
                element: this.$refs.canvas,
                extensions: createEditorExtensions({
                    assetBase: () => this.$root.dataset.assetBase || '',
                    getAssets: () => this.$wire.editorAssets(),
                    uploadImage: (file, knownNames) => new Promise((resolve, reject) => {
                        this.$wire.$uploadMultiple('images', [file], async () => {
                            const assets = await this.$wire.editorAssets();
                            resolve(assets.find((asset) => asset.pending && !knownNames.includes(asset.name)) || null);
                        }, reject);
                    }),
                }),
                content: initial,
                editorProps: {
                    attributes: { class: 'tiptap-canvas', spellcheck: 'false' },
                },
                onUpdate: () => {
                    this.version++;
                    this.push();
                },
                onSelectionUpdate: () => {
                    this.version++;
                },
            });
        },

        // Alpine calls destroy() when the element is removed (page teardown / wire:navigate).
        destroy() {
            this.editor?.destroy();
            this.editor = null;
        },

        markdown() {
            return this.editor ? this.editor.storage.markdown.getMarkdown() : '';
        },

        // Push serialized Markdown to Livewire (live, so the preview re-renders).
        push: debounce(function () {
            if (this.editor) this.$wire.set(wireProp, this.markdown());
        }, 400),

        // Flush the very latest Markdown (deferred), then submit in the same round-trip, so a user
        // who clicks Save mid-keystroke before the debounce fires never loses content.
        save() {
            if (this.editor) this.$wire.set(wireProp, this.markdown(), false);
            this.$wire.submit();
        },

        // ----- toolbar -----
        cmd(name, arg) {
            if (!this.editor) return;
            this.editor.chain().focus()[name](arg).run();
            this.version++;
        },
        heading(level) {
            this.editor?.chain().focus().toggleHeading({ level }).run();
            this.version++;
        },
        table() {
            this.editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
            this.version++;
        },
        carousel() {
            this.editor?.chain().focus().insertContent({
                type: 'articleCarousel',
                attrs: {
                    slides: [
                        { src: '', alt: '', caption: '' },
                        { src: '', alt: '', caption: '' },
                    ],
                },
            }).run();
            this.version++;
        },
        // active-state probe; reads `version` so the binding tracks it as a reactive dependency
        is(name, attrs) {
            void this.version;
            return this.editor ? this.editor.isActive(name, attrs) : false;
        },
    }));
});
