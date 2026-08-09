<div class="editor" x-data="{ tab: 'edit' }">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="/">{{ __('Home') }}</a>
        <span class="sep">/</span>
        <span class="current">{{ __('New article') }}</span>
    </nav>

    <header class="ed-head">
        <div>
            <div class="kicker">{{ __('Creating a new article') }}</div>
            <h1>{{ __('New article') }}</h1>
        </div>
        <a class="ed-cancel" href="/" wire:navigate>{{ __('Cancel') }}</a>
    </header>

    <p class="ed-note">
        {{ __('Choose where the article lives, then write it with the rich-text editor. The first heading is the title.') }}
        @if ($canManage)
            {!! __('As staff, your new article is :published (tracked and revertible from history).', ['published' => '<strong>'.e(__('published immediately')).'</strong>']) !!}
        @else
            {!! __('Your new article is :reviewed.', ['reviewed' => '<strong>'.e(__('reviewed before it goes live')).'</strong>']) !!}
        @endif
    </p>

    @if ($this->drafts->isNotEmpty())
        <details class="mb-6 border border-border bg-panel px-4 py-3" @if ($draftId === null) open @endif>
            <summary class="cursor-pointer font-mono text-xs uppercase tracking-wider text-dim">
                {{ trans_choice('{1} Your draft|[2,*] Your drafts', $this->drafts->count()) }}
                <span class="text-amber">({{ $this->drafts->count() }})</span>
            </summary>
            <div class="mt-3 divide-y divide-border border-t border-border">
                @foreach ($this->drafts as $draft)
                    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <a class="block truncate font-display text-head hover:text-amber"
                               href="{{ route('article.new.draft', $draft) }}" wire:navigate
                               @if ($draft->id === $draftId) aria-current="page" @endif>
                                {{ $draft->title }}
                                @if ($draft->id === $draftId)
                                    <span class="font-mono text-xs text-amber">{{ __('editing') }}</span>
                                @endif
                            </a>
                            <p class="mt-1 truncate font-mono text-xs text-muted">
                                /{{ $draft->type }}/{{ $draft->category ?: '…' }}/{{ $draft->slug ?: '…' }}
                                · {{ __('Saved :time', ['time' => $draft->updated_at->diffForHumans()]) }}
                            </p>
                        </div>
                        <button type="button" class="self-start font-mono text-xs uppercase text-muted hover:text-red sm:self-auto"
                                wire:click="deleteDraft({{ $draft->id }})"
                                wire:confirm="{{ __('Delete this draft and its saved images?') }}">
                            {{ __('Delete') }}
                        </button>
                    </div>
                @endforeach
            </div>
        </details>
    @endif

    {{-- Tabs: one pane at a time at every width, so the editor gets the full page. --}}
    <div class="ed-tabs" role="tablist">
        <button type="button" class="ed-tab" :class="{ 'is-on': tab === 'edit' }" @click="tab = 'edit'">{{ __('Write') }}</button>
        <button type="button" class="ed-tab" :class="{ 'is-on': tab === 'preview' }" @click="tab = 'preview'">{{ __('Preview') }}</button>
    </div>

    <div class="ed-grid">
        <section class="ed-pane ed-editpane" :class="{ 'is-hidden': tab !== 'edit' }" x-data="tiptapEditor()"
            data-asset-base="/{{ $type }}/{{ $category }}/{{ $slug }}">
            <div class="ed-locrow">
                <div class="ed-locfield">
                    <label class="ed-label" for="ed-type">{{ __('Type') }}</label>
                    <select id="ed-type" class="ed-input" wire:model.live="type">
                        @foreach (app(\App\Services\ArticleService::class)->types() as $t)
                            <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ed-locfield">
                    <label class="ed-label" for="ed-category">{{ __('Category') }}</label>
                    <input id="ed-category" type="text" class="ed-input" wire:model.blur="category"
                           list="ed-categories" placeholder="{{ __('e.g. electronics') }}" autocomplete="off">
                    <datalist id="ed-categories">
                        @foreach ($this->categoryOptions as $c)
                            <option value="{{ $c }}"></option>
                        @endforeach
                    </datalist>
                    @error('category') <p class="ed-error">{{ $message }}</p> @enderror
                </div>
                <div class="ed-locfield">
                    <label class="ed-label" for="ed-slug">{{ __('URL slug') }}</label>
                    <input id="ed-slug" type="text" class="ed-input" wire:model.blur="slug"
                           placeholder="{{ __('e.g. knock-sensor') }}" autocomplete="off">
                    @error('slug') <p class="ed-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="ed-pathpreview">{{ __('URL') }}: <code>/{{ $type }}/{{ $category ?: '…' }}/{{ $slug ?: '…' }}</code></p>

            @include('livewire.partials.frontmatter-fields')

            <label class="ed-label">{{ __('Article body') }}</label>
            @include('livewire.partials.editor-canvas')
            @error('bodyMarkdown') <p class="ed-error">{{ $message }}</p> @enderror

            <label class="ed-label">{{ __('Images') }} <span class="ed-opt">({{ __('optional, co-located with the article') }})</span></label>
            <input type="file" class="ed-input ed-file" wire:model="images" multiple accept="image/png,image/jpeg,image/gif,image/webp">
            <div wire:loading wire:target="images" class="ed-rendering">{{ __('Uploading…') }}</div>
            @error('images.*') <p class="ed-error">{{ $message }}</p> @enderror
            @if (count($this->savedDraftAssets))
                <ul class="ed-assets">
                    @foreach ($this->savedDraftAssets as $i => $asset)
                        <li>
                            <code>{{ $asset['name'] }}</code>
                            <span class="ed-asset-snip">{{ __('saved with this draft') }}</span>
                            <button type="button" class="ed-asset-rm" wire:click="removeDraftAsset({{ $i }})"
                                    wire:confirm="{{ __('Remove this image from the draft?') }}"
                                    aria-label="{{ __('Remove image') }}">&times;</button>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if (count($this->assetNames))
                <ul class="ed-assets">
                    @foreach ($this->assetNames as $i => $name)
                        <li>
                            <code>{{ $name }}</code>
                            <span class="ed-asset-snip">{!! __('add to the body as an image named :name', ['name' => '<code>'.e($name).'</code>']) !!}</span>
                            <button type="button" class="ed-asset-rm" wire:click="removeImage({{ $i }})" aria-label="{{ __('Remove image') }}">&times;</button>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if (count($this->savedDraftAssets) || count($this->assetNames))
                <p class="ed-opt">{{ __('Images are saved privately with a draft and committed with the article once it is published.') }}</p>
            @endif

            <label class="ed-label" for="ed-note">{{ __('Note for the reviewer') }} <span class="ed-opt">({{ __('optional') }})</span></label>
            <input id="ed-note" type="text" class="ed-input" wire:model="note" maxlength="500"
                   placeholder="{{ __('e.g. New article documenting the knock sensor circuit') }}">
            @error('note') <p class="ed-error">{{ $message }}</p> @enderror

            <div class="ed-actions flex-wrap">
                <button type="button" class="ed-add mt-0" @click="saveDraft()" wire:loading.attr="disabled" wire:target="saveDraft,images">
                    <span wire:loading.remove wire:target="saveDraft">{{ __('Save draft') }}</span>
                    <span wire:loading wire:target="saveDraft">{{ __('Saving draft...') }}</span>
                </button>
                <button type="button" class="btn ed-submit" @click="save()" wire:loading.attr="disabled" wire:target="submit,saveDraft,images">
                    <span wire:loading.remove wire:target="submit">{{ $canManage ? __('Publish article') : __('Submit for review') }}</span>
                    <span wire:loading wire:target="submit">{{ $canManage ? __('Publishing...') : __('Submitting...') }}</span>
                </button>
                <a class="ed-cancel-link" href="/" wire:navigate>{{ $draftId === null ? __('Discard') : __('Close') }}</a>
            </div>
        </section>

        <section class="ed-pane ed-previewpane" :class="{ 'is-hidden': tab !== 'preview' }" aria-live="polite">
            <div class="ed-previewbar">
                <span>{{ __('Live preview') }}</span>
                <span class="ed-rendering" wire:loading wire:target="bodyMarkdown">{{ __('rendering...') }}</span>
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
