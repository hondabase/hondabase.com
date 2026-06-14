{{-- TipTap rich-text canvas + toolbar. Rendered inside an x-data="tiptapEditor()" scope. The whole
     widget is wire:ignore so Livewire never morphs ProseMirror's DOM; the JS (resources/js/editor.js)
     serializes the doc to Markdown and pushes it to the $bodyMarkdown property (debounced), which
     drives the server-rendered live preview. Buttons toggle marks/nodes via the editor commands;
     active state reads the reactive `version` tick. --}}
<div wire:ignore class="ed-canvas-wrap">
    <div class="ed-toolbar" role="toolbar" aria-label="Formatting">
        <button type="button" class="ed-tool" :class="is('heading', { level: 2 }) && 'is-on'" @click="heading(2)" title="Heading">H2</button>
        <button type="button" class="ed-tool" :class="is('heading', { level: 3 }) && 'is-on'" @click="heading(3)" title="Subheading">H3</button>
        <span class="ed-tool-sep" aria-hidden="true"></span>
        <button type="button" class="ed-tool" :class="is('bold') && 'is-on'" @click="cmd('toggleBold')" title="Bold"><b>B</b></button>
        <button type="button" class="ed-tool" :class="is('italic') && 'is-on'" @click="cmd('toggleItalic')" title="Italic"><i>I</i></button>
        <button type="button" class="ed-tool" :class="is('strike') && 'is-on'" @click="cmd('toggleStrike')" title="Strikethrough"><s>S</s></button>
        <button type="button" class="ed-tool ed-tool-code" :class="is('code') && 'is-on'" @click="cmd('toggleCode')" title="Inline code">&lt;/&gt;</button>
        <span class="ed-tool-sep" aria-hidden="true"></span>
        <button type="button" class="ed-tool" :class="is('bulletList') && 'is-on'" @click="cmd('toggleBulletList')" title="Bullet list">&bull; List</button>
        <button type="button" class="ed-tool" :class="is('orderedList') && 'is-on'" @click="cmd('toggleOrderedList')" title="Numbered list">1. List</button>
        <button type="button" class="ed-tool" :class="is('blockquote') && 'is-on'" @click="cmd('toggleBlockquote')" title="Quote">&ldquo; &rdquo;</button>
        <button type="button" class="ed-tool" :class="is('codeBlock') && 'is-on'" @click="cmd('toggleCodeBlock')" title="Code block">Code</button>
        <span class="ed-tool-sep" aria-hidden="true"></span>
        <button type="button" class="ed-tool" @click="table()" title="Insert table">Table</button>
        <button type="button" class="ed-tool" @click="carousel()" title="Insert image carousel">Carousel</button>
        <button type="button" class="ed-tool" @click="cmd('setHorizontalRule')" title="Divider">&horbar;</button>
    </div>
    <div x-ref="canvas" class="ed-canvas"></div>
</div>
