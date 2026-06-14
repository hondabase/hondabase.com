<div class="editor" x-data="{ tab: 'edit' }">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <span class="sep">/</span>
        <span class="current">New article</span>
    </nav>

    <header class="ed-head">
        <div>
            <div class="kicker">Creating a new article</div>
            <h1>New article</h1>
        </div>
        <a class="ed-cancel" href="/" wire:navigate>Cancel</a>
    </header>

    <p class="ed-note">
        Choose where the article lives, then write it with the rich-text editor. The first heading is
        the title.
        @if ($canManage)
            As staff, your new article is <strong>published immediately</strong> (tracked and revertible from history).
        @else
            Your new article is <strong>reviewed before it goes live</strong>.
        @endif
    </p>

    {{-- Tabs: phone-first. On a wide screen both panes show side by side and these hide. --}}
    <div class="ed-tabs" role="tablist">
        <button type="button" class="ed-tab" :class="{ 'is-on': tab === 'edit' }" @click="tab = 'edit'">Write</button>
        <button type="button" class="ed-tab" :class="{ 'is-on': tab === 'preview' }" @click="tab = 'preview'">Preview</button>
    </div>

    <div class="ed-grid">
        <section class="ed-pane ed-editpane" :class="{ 'is-hidden': tab !== 'edit' }" x-data="tiptapEditor()">
            <div class="ed-locrow">
                <div class="ed-locfield">
                    <label class="ed-label" for="ed-type">Type</label>
                    <select id="ed-type" class="ed-input" wire:model.live="type">
                        @foreach (app(\App\Services\ArticleService::class)->types() as $t)
                            <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ed-locfield">
                    <label class="ed-label" for="ed-category">Category</label>
                    <input id="ed-category" type="text" class="ed-input" wire:model.blur="category"
                           list="ed-categories" placeholder="e.g. electronics" autocomplete="off">
                    <datalist id="ed-categories">
                        @foreach ($this->categoryOptions as $c)
                            <option value="{{ $c }}"></option>
                        @endforeach
                    </datalist>
                    @error('category') <p class="ed-error">{{ $message }}</p> @enderror
                </div>
                <div class="ed-locfield">
                    <label class="ed-label" for="ed-slug">URL slug</label>
                    <input id="ed-slug" type="text" class="ed-input" wire:model.blur="slug"
                           placeholder="e.g. knock-sensor" autocomplete="off">
                    @error('slug') <p class="ed-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="ed-pathpreview">URL: <code>/{{ $type }}/{{ $category ?: '…' }}/{{ $slug ?: '…' }}</code></p>

            @include('livewire.partials.frontmatter-fields')

            <label class="ed-label">Article body</label>
            @include('livewire.partials.editor-canvas')
            @error('bodyMarkdown') <p class="ed-error">{{ $message }}</p> @enderror

            <label class="ed-label">Images <span class="ed-opt">(optional, co-located with the article)</span></label>
            <input type="file" class="ed-input ed-file" wire:model="images" multiple accept="image/png,image/jpeg,image/gif,image/webp">
            <div wire:loading wire:target="images" class="ed-rendering">Uploading…</div>
            @error('images.*') <p class="ed-error">{{ $message }}</p> @enderror
            @if (count($this->assetNames))
                <ul class="ed-assets">
                    @foreach ($this->assetNames as $i => $name)
                        <li>
                            <code>{{ $name }}</code>
                            <span class="ed-asset-snip">add to the body as an image named <code>{{ $name }}</code></span>
                            <button type="button" class="ed-asset-rm" wire:click="removeImage({{ $i }})" aria-label="Remove image">&times;</button>
                        </li>
                    @endforeach
                </ul>
                <p class="ed-opt">Images are committed with the article once it is published.</p>
            @endif

            <label class="ed-label" for="ed-note">Note for the reviewer <span class="ed-opt">(optional)</span></label>
            <input id="ed-note" type="text" class="ed-input" wire:model="note" maxlength="500"
                   placeholder="e.g. New article documenting the knock sensor circuit">
            @error('note') <p class="ed-error">{{ $message }}</p> @enderror

            <div class="ed-actions">
                <button type="button" class="btn ed-submit" @click="save()" wire:loading.attr="disabled" wire:target="submit,images">
                    <span wire:loading.remove wire:target="submit">{{ $canManage ? 'Publish article' : 'Submit for review' }}</span>
                    <span wire:loading wire:target="submit">{{ $canManage ? 'Publishing...' : 'Submitting...' }}</span>
                </button>
                <a class="ed-cancel-link" href="/" wire:navigate>Discard</a>
            </div>
        </section>

        <section class="ed-pane ed-previewpane" :class="{ 'is-hidden': tab !== 'preview' }" aria-live="polite">
            <div class="ed-previewbar">
                <span>Live preview</span>
                <span class="ed-rendering" wire:loading wire:target="bodyMarkdown">rendering...</span>
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
    </div>
</div>
