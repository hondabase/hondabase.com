// Headless round-trip harness: validates that the real TipTap pipeline
// (StarterKit + Table + tiptap-markdown) serializes Markdown losslessly/idempotently.
// Usage: node scripts/tiptap-roundtrip.mjs [--show <slug>] [--start <offset> --limit <count>]
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>');
for (const k of ['window', 'document', 'DOMParser', 'HTMLElement', 'Node', 'getComputedStyle']) {
    global[k] = dom.window[k];
}

const { Editor } = await import('@tiptap/core');
const { createEditorExtensions } = await import('../resources/js/editor-extensions.js');
const fs = await import('node:fs');
const path = await import('node:path');

const extensions = createEditorExtensions();

function toMarkdown(md) {
    const el = dom.window.document.createElement('div');
    const editor = new Editor({ element: el, extensions, content: md });
    const out = editor.storage.markdown.getMarkdown();
    editor.destroy();
    return out;
}

function stripFrontmatter(raw) {
    return raw.replace(/^---\n[\s\S]*?\n---\n/, '');
}
function textOnly(s) {
    return s.replace(/[#*`_>|\-]/g, ' ').replace(/\s+/g, ' ').trim();
}

const ROOT = '/var/www/hondabase/www/content';
function walk(dir) {
    let out = [];
    for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
        const p = path.join(dir, e.name);
        if (e.isDirectory()) out = out.concat(walk(p));
        else if (e.name.endsWith('.md') && !e.name.startsWith('_')) out.push(p);
    }
    return out;
}

const showArg = process.argv.indexOf('--show');
if (showArg !== -1) {
    const slug = process.argv[showArg + 1];
    const f = walk(ROOT).find((p) => path.basename(p) === `${slug}.md` || p.includes(`/${slug}/`));
    const body = stripFrontmatter(fs.readFileSync(f, 'utf8'));
    const once = toMarkdown(body);
    const twice = toMarkdown(once);
    console.log('=== ORIGINAL ===\n' + body.slice(0, 1400));
    console.log('\n=== AFTER 1 ROUND-TRIP ===\n' + once.slice(0, 1400));
    console.log('\n=== IDEMPOTENT? ' + (once === twice ? 'YES' : 'NO') + ' ===');
    process.exit(0);
}

const startArg = process.argv.indexOf('--start');
const limitArg = process.argv.indexOf('--limit');
const start = startArg === -1 ? 0 : Math.max(0, Number.parseInt(process.argv[startArg + 1], 10) || 0);
const limit = limitArg === -1 ? undefined : Math.max(1, Number.parseInt(process.argv[limitArg + 1], 10) || 1);
const allFiles = walk(ROOT);
const files = allFiles.slice(start, limit === undefined ? undefined : start + limit);
let tot = 0,
    idem = 0,
    textKept = 0,
    drift = [];
for (const f of files) {
    const body = stripFrontmatter(fs.readFileSync(f, 'utf8'));
    if (!body.trim()) continue;
    tot++;
    let once, twice;
    try {
        once = toMarkdown(body);
        twice = toMarkdown(once);
    } catch (e) {
        drift.push(`${path.basename(f)}  ERROR ${e.message}`);
        continue;
    }
    if (once === twice) idem++;
    if (textOnly(once) === textOnly(twice)) textKept++;
    else drift.push(path.basename(f));

    // TipTap/JSDOM leaves large detached document graphs for V8 to collect. Give long corpus
    // runs a bounded memory profile when Node is started with --expose-gc.
    if (tot % 25 === 0) global.gc?.();
}
console.log(`Total: ${tot}`);
if (start !== 0 || limit !== undefined) console.log(`Corpus range: ${start}-${start + files.length - 1} of ${allFiles.length}`);
console.log(`Idempotent (round-trip 2 == round-trip 1): ${idem} (${((idem / tot) * 100).toFixed(1)}%)`);
console.log(`Text-stable across the second round-trip:  ${textKept} (${((textKept / tot) * 100).toFixed(1)}%)`);
console.log(`\nNon-text-stable (${drift.length}):\n  ` + drift.slice(0, 20).join('\n  '));
