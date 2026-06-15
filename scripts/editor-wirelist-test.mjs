import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
for (const key of ['window', 'document', 'DOMParser', 'HTMLElement', 'Node', 'getComputedStyle']) {
    global[key] = dom.window[key];
}

const { Editor } = await import('@tiptap/core');
const { createEditorExtensions, parseWirelistBody } = await import('../resources/js/editor-extensions.js');

const data = {
    title: 'ECU connections',
    variants: [{
        id: 'p28',
        label: 'USDM P28',
        groups: [{
            label: 'ROM socket',
            rows: [{ pin: 'Pin 1', signal: 'VCC', path: 'ROM Pin 28', note: '' }],
        }],
    }],
};
const source = `Before\n\n\`\`\`wirelist\n${JSON.stringify(data, null, 2)}\n\`\`\`\n\nAfter`;
const editor = new Editor({
    element: document.createElement('div'),
    extensions: createEditorExtensions(),
    content: source,
});

assert.equal(editor.getJSON().content[1].type, 'articleWirelist');
assert.deepEqual(editor.getJSON().content[1].attrs.wirelist, data);
assert.equal(editor.storage.markdown.getMarkdown(), source);
assert.equal(parseWirelistBody('{"title":"Broken"}'), null);
editor.destroy();

console.log('Editor wirelist tests passed.');
