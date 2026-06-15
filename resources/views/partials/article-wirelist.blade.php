@php
    $rows = [];
    foreach ($wirelist['variants'] as $variant) {
        foreach ($variant['groups'] as $group) {
            foreach ($group['rows'] as $row) {
                $row['steps'] = array_map(function (string $step): array {
                    $step = trim($step);
                    preg_match('/^(.*?)(?:\s+\(([^()]*)\))?$/', $step, $parts);

                    return [
                        'connection' => trim($parts[1] ?? $step),
                        'signal' => trim($parts[2] ?? ''),
                    ];
                }, preg_split('/\s*->\s*/', $row['path']) ?: []);
                $rows[] = $row + [
                    'variant_id' => $variant['id'],
                    'variant' => $variant['label'],
                    'group' => $group['label'],
                ];
            }
        }
    }
    $searchRows = array_map(function (array $row): array {
        unset($row['steps']);

        return $row;
    }, $rows);
@endphp
<section class="wirelist"
    x-data="{
        q: '',
        variant: 'all',
        group: 'all',
        rows: @js($searchRows),
        get groups() {
            return [...new Set(this.rows.filter(row => this.variant === 'all' || row.variant_id === this.variant).map(row => row.group))];
        },
        get filtered() {
            return this.rows.filter(row => this.matches(row));
        },
        matches(row) {
            const query = this.q.trim().toLowerCase();
            return (
                (this.variant === 'all' || row.variant_id === this.variant) &&
                (this.group === 'all' || row.group === this.group) &&
                (!query || [row.variant, row.group, row.pin, row.signal, row.path, row.note].join(' ').toLowerCase().includes(query))
            );
        },
        chooseVariant() {
            if (this.group !== 'all' && !this.groups.includes(this.group)) this.group = 'all';
        },
        reset() {
            this.q = '';
            this.variant = 'all';
            this.group = 'all';
        }
    }">
    <div class="wirelist-bar">
        <div>
            <h3>{{ $wirelist['title'] }}</h3>
            <p><span x-text="filtered.length"></span> of {{ count($rows) }} connections</p>
        </div>
        <button type="button" @click="reset">Reset</button>
    </div>
    <div class="wirelist-controls">
        <label>
            <span>Search pins, signals, or paths</span>
            <input type="search" x-model="q" placeholder="e.g. ROM Pin 20, AD4, R54">
        </label>
        <label>
            <span>ECU family</span>
            <select x-model="variant" @change="chooseVariant">
                <option value="all">All ECU families</option>
                @foreach ($wirelist['variants'] as $variant)
                    <option value="{{ $variant['id'] }}">{{ $variant['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Component</span>
            <select x-model="group">
                <option value="all">All components</option>
                <template x-for="name in groups" :key="name">
                    <option :value="name" x-text="name"></option>
                </template>
            </select>
        </label>
    </div>
    <div class="wirelist-results">
        @foreach ($rows as $index => $row)
            <article class="wirelist-row" x-show="matches(rows[{{ $index }}])">
                <div class="wirelist-meta">
                    <span>{{ $row['variant'] }}</span>
                    <span>{{ $row['group'] }}</span>
                </div>
                <div class="wirelist-pin">
                    <span>Test point</span>
                    <strong>{{ $row['pin'] }}</strong>
                    <code>{{ $row['signal'] }}</code>
                </div>
                <ol class="wirelist-path" aria-label="Connection path">
                    @foreach ($row['steps'] as $step)
                        <li>
                            <strong>{{ $step['connection'] }}</strong>
                            @if ($step['signal'] !== '')
                                <code>{{ $step['signal'] }}</code>
                            @endif
                        </li>
                    @endforeach
                </ol>
                @if ($row['note'] !== '')
                    <small>{{ $row['note'] }}</small>
                @endif
            </article>
        @endforeach
        <p class="wirelist-empty" x-show="filtered.length === 0">No matching connections.</p>
    </div>
</section>
