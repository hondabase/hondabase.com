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
    // The Editor lives in the factory closure, NOT on the Alpine data object: Alpine would wrap it
    // in a reactive proxy, and ProseMirror's state identity checks then fail on every dispatch
    // ("Applying a mismatched transaction").
    window.Alpine.data('tiptapEditor', (wireProp = 'bodyMarkdown') => {
        let editor = null;

        return {
        // Bumped on every transaction/selection change so Alpine re-evaluates the toolbar's
        // active-state bindings (TipTap's state is not otherwise reactive to Alpine).
        version: 0,

        init() {
            // Read the body from Livewire so the (changing) Markdown is never baked into a DOM attr.
            const initial = this.$wire.get(wireProp) ?? '';
            editor = new Editor({
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
            editor?.destroy();
            editor = null;
        },

        markdown() {
            return editor ? editor.storage.markdown.getMarkdown() : '';
        },

        // Push serialized Markdown to Livewire (live, so the preview re-renders).
        push: debounce(function () {
            if (editor) this.$wire.set(wireProp, this.markdown());
        }, 400),

        // Flush the very latest Markdown (deferred), then submit in the same round-trip, so a user
        // who clicks Save mid-keystroke before the debounce fires never loses content.
        save() {
            if (editor) this.$wire.set(wireProp, this.markdown(), false);
            this.$wire.submit();
        },

        saveDraft() {
            if (editor) this.$wire.set(wireProp, this.markdown(), false);
            this.$wire.saveDraft();
        },

        // ----- toolbar -----
        cmd(name, arg) {
            if (!editor) return;
            editor.chain().focus()[name](arg).run();
            this.version++;
        },
        heading(level) {
            editor?.chain().focus().toggleHeading({ level }).run();
            this.version++;
        },
        table() {
            editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
            this.version++;
        },
        image() {
            editor?.chain().focus().insertContent({ type: 'image', attrs: { src: '', alt: '' } }).run();
            this.version++;
        },
        carousel() {
            editor?.chain().focus().insertContent({
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
        wirelist() {
            editor?.chain().focus().insertContent({
                type: 'articleWirelist',
                attrs: {
                    wirelist: {
                        title: 'ECU connection wirelist',
                        variants: [{
                            id: 'ecu-family',
                            label: 'ECU family',
                            groups: [{
                                label: 'Component',
                                rows: [{ pin: 'Pin 1', signal: 'Signal', path: 'Connection path', note: '' }],
                            }],
                        }],
                    },
                },
            }).run();
            this.version++;
        },
        // active-state probe; reads `version` so the binding tracks it as a reactive dependency
        is(name, attrs) {
            void this.version;
            return editor ? editor.isActive(name, attrs) : false;
        },
        };
    });
});
