@php $articleUrl = ($isTranslation ? "/{$locale}" : '')."/{$type}/{$category}/{$slug}"; @endphp
<div class="editor" x-data="{ tab: 'edit' }">
    <nav class="crumbs" aria-label="Breadcrumb">
        <a href="/">{{ __('Home') }}</a>
        <span class="sep">/</span>
        <a href="/{{ $type }}/{{ $category }}">{{ \Illuminate\Support\Str::headline($category) }}</a>
        <span class="sep">/</span>
        <a href="{{ $articleUrl }}">{{ $articleTitle }}</a>
        <span class="sep">/</span>
        <span class="current">{{ $isTranslation ? __('Translate') : __('Edit') }}</span>
    </nav>

    <header class="ed-head">
        <div>
            <div class="kicker">
                @if ($isTranslation)
                    {{ __('Translating to :language', ['language' => \App\Support\Locales::all()[$locale]['native']]) }}
                @else
                    {{ __('Suggesting an edit') }}
                @endif
            </div>
            <h1>{{ $articleTitle }}</h1>
        </div>
        <a class="ed-cancel" href="{{ $articleUrl }}" wire:navigate>{{ __('Cancel') }}</a>
    </header>

    @if ($isTranslation)
        <p class="ed-note">
            @if ($isNewTranslation)
                {!! __('No :language translation exists yet. The body below is pre-filled with the English text; replace it with your translation. Open the :source in another tab to follow along.', ['language' => e(\App\Support\Locales::all()[$locale]['native']), 'source' => '<a href="/'.$type.'/'.$category.'/'.$slug.'" target="_blank" rel="noopener">'.e(__('English version')).'</a>']) !!}
            @else
                {!! __('Editing the :language translation. Open the :source in another tab to compare.', ['language' => e(\App\Support\Locales::all()[$locale]['native']), 'source' => '<a href="/'.$type.'/'.$category.'/'.$slug.'" target="_blank" rel="noopener">'.e(__('English version')).'</a>']) !!}
            @endif
        </p>
    @endif

    <p class="ed-note">
        {{ __('Edit the article below with the rich-text editor; its metadata (tags, summary, what it applies to) is set in the fields above the body.') }}
        @if ($canManage)
            {!! __('As staff, your change is :applied (tracked and revertible from history).', ['applied' => '<strong>'.e(__('applied immediately')).'</strong>']) !!}
        @else
            {!! __('Your change is :reviewed.', ['reviewed' => '<strong>'.e(__('reviewed before it goes live')).'</strong>']) !!}
        @endif
        {{ __('The preview renders exactly like the published page.') }}
    </p>

    {{-- Tabs: phone-first. On a wide screen both panes show side by side and these hide. --}}
    <div class="ed-tabs" role="tablist">
        <button type="button" class="ed-tab" :class="{ 'is-on': tab === 'edit' }" @click="tab = 'edit'">{{ __('Write') }}</button>
        <button type="button" class="ed-tab" :class="{ 'is-on': tab === 'preview' }" @click="tab = 'preview'">
            {{ __('Preview') }} <span class="ed-rendering" wire:loading wire:target="bodyMarkdown">&middot;</span>
        </button>
    </div>

    <div class="ed-grid">
        <section class="ed-pane ed-editpane" :class="{ 'is-hidden': tab !== 'edit' }" x-data="tiptapEditor()"
            data-asset-base="/{{ $type }}/{{ $category }}/{{ $slug }}">
            @include('livewire.partials.frontmatter-fields')

            <label class="ed-label">{{ __('Article body') }}</label>
            @include('livewire.partials.editor-canvas')
            @error('bodyMarkdown') <p class="ed-error">{{ $message }}</p> @enderror

            <label class="ed-label" for="ed-note">{{ __('What did you change?') }}
                <span class="ed-opt">({{ __('optional, helps the reviewer') }})</span></label>
            <input id="ed-note" type="text" class="ed-input" wire:model="note" maxlength="500"
                   placeholder="{{ __('e.g. Fixed the resistor value and added the OBD2 pinout') }}">
            @error('note') <p class="ed-error">{{ $message }}</p> @enderror

            <div class="ed-actions">
                <button type="button" class="btn ed-submit" @click="save()" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">{{ $canManage ? __('Publish changes') : __('Submit for review') }}</span>
                    <span wire:loading wire:target="submit">{{ $canManage ? __('Publishing...') : __('Submitting...') }}</span>
                </button>
                <a class="ed-cancel-link" href="{{ $articleUrl }}" wire:navigate>{{ __('Discard') }}</a>
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
