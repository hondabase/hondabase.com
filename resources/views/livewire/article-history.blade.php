<div class="review">
    @if ($this->scoped())
        <nav class="crumbs">
            <a href="/admin/reviews">Review queue</a>
            <span class="sep">/</span>
            <a href="/{{ $type }}/{{ $category }}/{{ $slug }}">{{ $articleTitle }}</a>
            <span class="sep">/</span>
            <span class="current">History</span>
        </nav>
        <h1>History · {{ $articleTitle }}</h1>
    @else
        <h1>Recent applied changes</h1>
        <p class="ed-note">Every committed edit across the knowledgebase, newest first. Open an
            article's own history for its full trail (including pending and rejected edits).</p>
    @endif

    @if ($message)
        <div class="flash flash-ok" role="status">{{ $message }}</div>
    @endif

    @if ($unpushed > 0)
        <div class="rev-unpushed" role="alert">
            {{ $unpushed }} committed edit{{ $unpushed === 1 ? '' : 's' }} not yet pushed to origin.
            Set <code>HONDABASE_GIT_PUSH=true</code> with a deploy key configured to sync.
        </div>
    @endif

    @forelse ($revisions as $rev)
        <article class="rev-card" wire:key="hist-{{ $rev->id }}">
            <header class="rev-cardhead">
                <h2>{{ $rev->title }}</h2>
                <span class="rev-id">#{{ $rev->id }} · {{ $rev->repo_path }}</span>
                <span class="rev-state rev-state-{{ $rev->status }}">{{ $rev->status }}</span>
                <span class="rev-by">
                    by <b>{{ optional($rev->author)->displayName() ?? 'unknown' }}</b>
                    {{ $rev->created_at?->diffForHumans() }}
                </span>
            </header>

            <div class="rev-trail">
                @if ($rev->isApplied())
                    <span class="rev-tag">committed {{ \Illuminate\Support\Str::limit($rev->commit_sha, 10, '') }}</span>
                    <span class="rev-tag {{ $rev->pushed ? 'rev-tag-ok' : 'rev-tag-warn' }}">{{ $rev->pushed ? 'pushed' : 'local only' }}</span>
                @endif
                @if ($rev->reviewer)
                    <span class="rev-tag">{{ $rev->user_id === $rev->reviewer_id ? 'self-approved' : 'reviewed' }} by {{ $rev->reviewer->displayName() }}</span>
                @endif
                @if ($rev->reverts_revision_id)
                    <span class="rev-tag rev-tag-revert">reverts #{{ $rev->reverts_revision_id }}</span>
                @endif
            </div>

            @if ($rev->summary)
                <p class="rev-summary"><span>Note</span>{{ $rev->summary }}</p>
            @endif

            <div class="rev-diff">
                @foreach ($rev->compactDiff() as $line)
                    @if ($line['kind'] === 'gap')
                        <span class="d d-gap">{{ $line['text'] }}</span>
                    @else
                        <span class="d d-{{ $line['kind'] }}">{{ $line['text'] === '' ? ' ' : $line['text'] }}</span>
                    @endif
                @endforeach
            </div>

            <div class="rev-actions">
                @if ($rev->isApplied())
                    <button type="button" class="rev-reject" wire:click="revert({{ $rev->id }})"
                            wire:confirm="Revert edit #{{ $rev->id }}? This restores the article to the state before that edit as a new commit."
                            wire:loading.attr="disabled">Revert this edit</button>
                @endif
                @unless ($this->scoped())
                    <a class="rev-link" href="/admin/history/{{ $rev->type }}/{{ $rev->category }}/{{ $rev->slug }}">Full history →</a>
                @endunless
                <a class="rev-link" href="/{{ $rev->type }}/{{ $rev->category }}/{{ $rev->slug }}" target="_blank" rel="noopener">View live →</a>
            </div>
        </article>
    @empty
        <p class="rev-empty">No changes recorded yet.</p>
    @endforelse
</div>
