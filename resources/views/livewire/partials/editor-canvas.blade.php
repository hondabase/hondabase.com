{{-- TipTap rich-text canvas + toolbar. Rendered inside an x-data="tiptapEditor()" scope. The whole
     widget is wire:ignore so Livewire never morphs ProseMirror's DOM; the JS (resources/js/editor.js)
     serializes the doc to Markdown and pushes it to the $bodyMarkdown property (debounced), which
     drives the server-rendered live preview. Buttons toggle marks/nodes via the editor commands;
     active state reads the reactive `version` tick. --}}
<div wire:ignore class="ed-canvas-wrap">
    <div class="ed-toolbar" role="toolbar" aria-label="{{ __('Formatting') }}">
        <button type="button" class="ed-tool" :class="is('heading', { level: 2 }) && 'is-on'" @click="heading(2)" title="{{ __('Heading') }}">H2</button>
        <button type="button" class="ed-tool" :class="is('heading', { level: 3 }) && 'is-on'" @click="heading(3)" title="{{ __('Subheading') }}">H3</button>
        <span class="ed-tool-sep" aria-hidden="true"></span>
        <button type="button" class="ed-tool" :class="is('bold') && 'is-on'" @click="cmd('toggleBold')" title="{{ __('Bold') }}"><b>B</b></button>
        <button type="button" class="ed-tool" :class="is('italic') && 'is-on'" @click="cmd('toggleItalic')" title="{{ __('Italic') }}"><i>I</i></button>
        <button type="button" class="ed-tool" :class="is('strike') && 'is-on'" @click="cmd('toggleStrike')" title="{{ __('Strikethrough') }}"><s>S</s></button>
        <button type="button" class="ed-tool ed-tool-code" :class="is('code') && 'is-on'" @click="cmd('toggleCode')" title="{{ __('Inline code') }}">&lt;/&gt;</button>
        <span class="ed-tool-sep" aria-hidden="true"></span>
        <button type="button" class="ed-tool" :class="is('bulletList') && 'is-on'" @click="cmd('toggleBulletList')" title="{{ __('Bullet list') }}">&bull; {{ __('List') }}</button>
        <button type="button" class="ed-tool" :class="is('orderedList') && 'is-on'" @click="cmd('toggleOrderedList')" title="{{ __('Numbered list') }}">1. {{ __('List') }}</button>
        <button type="button" class="ed-tool" :class="is('blockquote') && 'is-on'" @click="cmd('toggleBlockquote')" title="{{ __('Quote') }}">&ldquo; &rdquo;</button>
        <button type="button" class="ed-tool" :class="is('codeBlock') && 'is-on'" @click="cmd('toggleCodeBlock')" title="{{ __('Code block') }}">{{ __('Code') }}</button>
        <span class="ed-tool-sep" aria-hidden="true"></span>
        <button type="button" class="ed-tool" @click="table()" title="{{ __('Insert table') }}">{{ __('Table') }}</button>
        <button type="button" class="ed-tool" @click="carousel()" title="{{ __('Insert image carousel') }}">{{ __('Carousel') }}</button>
        <button type="button" class="ed-tool" @click="wirelist()" title="{{ __('Insert searchable wirelist') }}">{{ __('Wirelist') }}</button>
        <button type="button" class="ed-tool" @click="cmd('setHorizontalRule')" title="{{ __('Divider') }}">&horbar;</button>
    </div>
    <div x-ref="canvas" class="ed-canvas"></div>
</div>
