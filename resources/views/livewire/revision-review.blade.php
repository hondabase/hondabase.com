<div class="review">
    <div class="rev-head">
        <h1>Edit review queue</h1>
        <a class="rev-link" href="/admin/history">Recent applied changes →</a>
    </div>

    @if ($message)
        <div class="flash flash-ok" role="status">{{ $message }}</div>
    @endif

    @if ($unpushed > 0)
        <div class="rev-unpushed" role="alert">
            {{ $unpushed }} approved edit{{ $unpushed === 1 ? '' : 's' }} committed locally but not yet
            pushed to origin. Configure the deploy key and set <code>HONDABASE_GIT_PUSH=true</code> to sync.
        </div>
    @endif

    @if ($conflicted->isNotEmpty())
        <section class="rev-conflicts">
            <h2 class="rev-section">Conflicts needing re-review ({{ $conflicted->count() }})</h2>
            <p class="rev-section-note">These edits could not be applied because the article changed
                underneath them. Re-base to diff against the current article and re-commit, or reject.</p>

            @foreach ($conflicted as $rev)
                <article class="rev-card rev-card-conflict" wire:key="conf-{{ $rev->id }}">
                    <header class="rev-cardhead">
                        <h2>{{ $rev->title }} <span class="rev-state rev-state-conflicted">conflicted</span></h2>
                        <span class="rev-id">#{{ $rev->id }} · {{ $rev->repo_path }}</span>
                        <span class="rev-by">
                            by <b>{{ optional($rev->author)->displayName() ?? 'unknown' }}</b>
                            {{ $rev->created_at?->diffForHumans() }}
                        </span>
                    </header>

                    @if ($rev->summary)
                        <p class="rev-summary"><span>Editor's note</span>{{ $rev->summary }}</p>
                    @endif
                    @include('livewire.partials.revision-assets', ['rev' => $rev])

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
                        <button type="button" class="rev-approve" wire:click="rebase({{ $rev->id }})"
                                wire:confirm="Re-base edit #{{ $rev->id }} onto the current article and commit it?"
                                wire:loading.attr="disabled">Re-base &amp; commit</button>
                        <input type="text" placeholder="Note (optional)"
                               wire:model="notes.{{ $rev->id }}" maxlength="500">
                        <button type="button" class="rev-reject" wire:click="reject({{ $rev->id }})"
                                wire:confirm="Reject conflicted edit #{{ $rev->id }}?"
                                wire:loading.attr="disabled">Reject</button>
                        <a class="rev-link" href="/{{ $rev->type }}/{{ $rev->category }}/{{ $rev->slug }}" target="_blank" rel="noopener">View live →</a>
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    @forelse ($pending as $rev)
        <article class="rev-card" wire:key="rev-{{ $rev->id }}">
            <header class="rev-cardhead">
                <h2>{{ $rev->title }}</h2>
                <span class="rev-id">#{{ $rev->id }} · {{ $rev->repo_path }}</span>
                <span class="rev-by">
                    by <b>{{ optional($rev->author)->displayName() ?? 'unknown' }}</b>
                    {{ $rev->created_at?->diffForHumans() }}
                </span>
            </header>

            @if ($rev->summary)
                <p class="rev-summary"><span>Editor's note</span>{{ $rev->summary }}</p>
            @endif
            @include('livewire.partials.revision-assets', ['rev' => $rev])

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
                <button type="button" class="rev-approve" wire:click="approve({{ $rev->id }})"
                        wire:confirm="Approve edit #{{ $rev->id }}? This commits it to the articles repo."
                        wire:loading.attr="disabled">Approve &amp; commit</button>
                <input type="text" placeholder="Note (optional, saved on reject/approve)"
                       wire:model="notes.{{ $rev->id }}" maxlength="500">
                <button type="button" class="rev-reject" wire:click="reject({{ $rev->id }})"
                        wire:confirm="Reject edit #{{ $rev->id }}?"
                        wire:loading.attr="disabled">Reject</button>
                <a class="rev-link" href="/{{ $rev->type }}/{{ $rev->category }}/{{ $rev->slug }}" target="_blank" rel="noopener">View live →</a>
            </div>
        </article>
    @empty
        <p class="rev-empty">Nothing waiting for review. Approved edits are committed to
            <code>hondabase/articles</code> with attribution.</p>
    @endforelse
</div>
