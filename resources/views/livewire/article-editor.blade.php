<div class="editor" x-data="{ tab: 'edit' }">
    <nav class="crumbs">
        <a href="/">Home</a>
        <span class="sep">/</span>
        <a href="/{{ $type }}/{{ $category }}">{{ \Illuminate\Support\Str::headline($category) }}</a>
        <span class="sep">/</span>
        <a href="/{{ $type }}/{{ $category }}/{{ $slug }}">{{ $articleTitle }}</a>
        <span class="sep">/</span>
        <span class="current">Edit</span>
    </nav>

    <header class="ed-head">
        <div>
            <div class="kicker">Suggesting an edit</div>
            <h1>{{ $articleTitle }}</h1>
        </div>
        <a class="ed-cancel" href="/{{ $type }}/{{ $category }}/{{ $slug }}" wire:navigate>Cancel</a>
    </header>

    <p class="ed-note">
        Edit the markdown below. The block between the <code>---</code> lines is frontmatter
        (metadata like tags and complexity); everything after is the article.
        @if ($canManage)
            As staff, your change is <strong>applied immediately</strong> (tracked and
            revertible from history).
        @else
            Your change is <strong>reviewed before it goes live</strong>.
        @endif
        The preview renders exactly like the published page.
    </p>

    {{-- Tabs: phone-first. On a wide screen both panes show side by side and these hide. --}}
    <div class="ed-tabs" role="tablist">
        <button type="button" class="ed-tab" :class="{ 'is-on': tab === 'edit' }" @click="tab = 'edit'">Edit</button>
        <button type="button" class="ed-tab" :class="{ 'is-on': tab === 'preview' }" @click="tab = 'preview'">
            Preview <span class="ed-rendering" wire:loading wire:target="body">·</span>
        </button>
    </div>

    <form wire:submit="submit" class="ed-grid">
        <section class="ed-pane ed-editpane" :class="{ 'is-hidden': tab !== 'edit' }">
            <label class="ed-label" for="ed-body">Article markdown</label>
            <textarea id="ed-body" class="ed-textarea" wire:model.live.debounce.500ms="body"
                      spellcheck="false" autocapitalize="off" autocomplete="off"></textarea>
            @error('body') <p class="ed-error">{{ $message }}</p> @enderror

            <label class="ed-label" for="ed-summary">What did you change? <span class="ed-opt">(optional, helps the reviewer)</span></label>
            <input id="ed-summary" type="text" class="ed-input" wire:model="summary" maxlength="500"
                   placeholder="e.g. Fixed the resistor value and added the OBD2 pinout">
            @error('summary') <p class="ed-error">{{ $message }}</p> @enderror

            <div class="ed-actions">
                <button type="submit" class="btn ed-submit" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">{{ $canManage ? 'Publish changes' : 'Submit for review' }}</span>
                    <span wire:loading wire:target="submit">{{ $canManage ? 'Publishing...' : 'Submitting...' }}</span>
                </button>
                <a class="ed-cancel-link" href="/{{ $type }}/{{ $category }}/{{ $slug }}" wire:navigate>Discard</a>
            </div>
        </section>

        <section class="ed-pane ed-previewpane" :class="{ 'is-hidden': tab !== 'preview' }" aria-live="polite">
            <div class="ed-previewbar">
                <span>Live preview</span>
                <span class="ed-rendering" wire:loading wire:target="body">rendering...</span>
            </div>
            <article class="article ed-previewbody">
                <header class="article-head">
                    <h1>{{ $this->preview['title'] }}</h1>
                </header>
                <div class="prose-article">
                    {!! $this->preview['html'] !!}
                </div>
            </article>
        </section>
    </form>
</div>
