import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
for (const key of ['window', 'document', 'DOMParser', 'HTMLElement', 'Node', 'getComputedStyle']) {
    global[key] = dom.window[key];
}

const { Editor } = await import('@tiptap/core');
const { createEditorExtensions, parseCarouselBody } = await import('../resources/js/editor-extensions.js');

const source = [
    'Before',
    '',
    '![Ordinary image](ordinary.jpg)',
    '',
    '```carousel',
    '![Front](front.jpg)',
    '*Front caption*',
    '<!-- slide -->',
    '![Rear](rear.jpg)',
    '```',
    '',
    'After',
].join('\n');

const editor = new Editor({
    element: document.createElement('div'),
    extensions: createEditorExtensions(),
    content: source,
});

const json = editor.getJSON();
assert.equal(json.content[1].content[0].type, 'image', 'ordinary Markdown images must survive');
assert.equal(json.content[2].type, 'articleCarousel', 'valid carousel fences become visual nodes');
assert.deepEqual(json.content[2].attrs.slides, [
    { src: 'front.jpg', alt: 'Front', caption: 'Front caption' },
    { src: 'rear.jpg', alt: 'Rear', caption: '' },
]);
assert.equal(editor.storage.markdown.getMarkdown(), source, 'valid content round-trips canonically');

editor.commands.setContent({
    type: 'doc',
    content: [{
        type: 'articleCarousel',
        attrs: {
            slides: [
                { src: 'rear.jpg', alt: 'Rear', caption: '' },
                { src: 'front.jpg', alt: 'Front', caption: 'Moved second' },
            ],
        },
    }],
});
assert.equal(editor.storage.markdown.getMarkdown(), [
    '```carousel',
    '![Rear](rear.jpg)',
    '<!-- slide -->',
    '![Front](front.jpg)',
    '*Moved second*',
    '```',
].join('\n'), 'edited and reordered slide attributes serialize to Markdown');
editor.destroy();

const malformed = parseCarouselBody('![](front.jpg)\n<!-- slide -->\n![Remote](https://example.com/rear.jpg)');
assert.equal(malformed, null, 'malformed or remote-image carousels are rejected');

console.log('Editor image/carousel tests passed.');
