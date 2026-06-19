@php
    $localeAuthors = $art['authors'];
    $enAuthors     = $art['en_authors'] ?? collect();
    $isTranslation = ! \App\Support\Locales::isDefault($art['locale']) && $enAuthors->isNotEmpty();
    $hasAny        = $localeAuthors->isNotEmpty() || $enAuthors->isNotEmpty();

    $roleLabel = fn ($credit) => $credit->is_original || (! $credit->is_contributor) ? __('Author') : __('Contributor');
@endphp

@if ($hasAny)
    <div class="article-authors">
        @if ($isTranslation && $localeAuthors->isNotEmpty())
            <div class="author-group">
                <span class="author-group-label">{{ __('Translation') }}</span>
                <div class="author-cards">
                    @foreach ($localeAuthors as $credit)
                        <div class="author-card">
                            @if ($credit->user->avatarUrl())
                            <img class="author-avatar" src="{{ $credit->user->avatarUrl() }}" alt="" width="28" height="28" loading="lazy">
                        @else
                            <span class="author-avatar author-avatar--initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($credit->user->displayName(), 0, 1)) }}</span>
                        @endif
                            <span class="author-name">{{ $credit->user->displayName() }}</span>
                            <span class="author-role">{{ $roleLabel($credit) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="author-group">
                <span class="author-group-label">{{ __('Original') }}</span>
                <div class="author-cards">
                    @foreach ($enAuthors as $credit)
                        <div class="author-card">
                            @if ($credit->user->avatarUrl())
                            <img class="author-avatar" src="{{ $credit->user->avatarUrl() }}" alt="" width="28" height="28" loading="lazy">
                        @else
                            <span class="author-avatar author-avatar--initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($credit->user->displayName(), 0, 1)) }}</span>
                        @endif
                            <span class="author-name">{{ $credit->user->displayName() }}</span>
                            <span class="author-role">{{ $roleLabel($credit) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="author-cards">
                @foreach ($localeAuthors->isNotEmpty() ? $localeAuthors : $enAuthors as $credit)
                    <div class="author-card">
                        @if ($credit->user->avatarUrl())
                            <img class="author-avatar" src="{{ $credit->user->avatarUrl() }}" alt="" width="28" height="28" loading="lazy">
                        @else
                            <span class="author-avatar author-avatar--initials" aria-hidden="true">{{ mb_strtoupper(mb_substr($credit->user->displayName(), 0, 1)) }}</span>
                        @endif
                        <span class="author-name">{{ $credit->user->displayName() }}</span>
                        <span class="author-role">{{ $roleLabel($credit) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
