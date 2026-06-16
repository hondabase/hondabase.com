<div class="review taxman" x-data="{
    currentTab: '{{ $types[0] ?? 'subjects' }}',
    searchQuery: '',
    collapsed: {},
    init() {
        // Collapsed state initialization can go here if needed.
    },
    isCollapsed(path) {
        for (let p in this.collapsed) {
            if (this.collapsed[p] && path.startsWith(p + '/')) {
                return true;
            }
        }
        return false;
    },
    toggleCollapse(path) {
        this.collapsed[path] = !this.collapsed[path];
        this.collapsed = { ...this.collapsed }; // trigger reactivity
    },
    matchesSearch(name, slug, chassis, kind, path) {
        if (!this.searchQuery) return true;
        const q = this.searchQuery.toLowerCase();
        return name.toLowerCase().includes(q)
            || slug.toLowerCase().includes(q)
            || chassis.toLowerCase().includes(q)
            || kind.toLowerCase().includes(q)
            || path.toLowerCase().includes(q);
    }
}" x-cloak>
    <div class="rev-head">
        <h1>Product taxonomy</h1>
        <button type="button" class="rev-link" wire:click="rebuildIndex" wire:loading.attr="disabled">
            <span wire:loading.remove>Rebuild article links →</span>
            <span wire:loading>Rebuilding...</span>
        </button>
    </div>

    <p class="rev-section-note">
        The taxonomy lives in the database (this panel is the source of truth).
        Adding nodes and editing metadata is free; renaming or removing a node with articles filed
        under it is blocked. Article compatibility updates after a rebuild.
    </p>

    @if ($message)
        <div class="flash flash-ok taxman-flash" role="status">{{ $message }}</div>
    @endif

    {{-- Stats Bar --}}
    <div class="rev-trail taxman-stats" aria-label="Taxonomy totals">
        <span class="rev-tag taxman-stat"><b>{{ $stats['nodes'] }}</b> Taxonomy Nodes</span>
        <span class="rev-tag taxman-stat"><b>{{ $stats['subjects'] }}</b> Subjects</span>
        <span class="rev-tag taxman-stat"><b>{{ $stats['articles'] }}</b> Total Articles</span>
    </div>

    {{-- Navigation Tabs --}}
    <div class="taxman-tabs">
        @foreach ($types as $type)
            <button type="button"
                    class="taxman-tab"
                    :class="{ 'active': currentTab === '{{ $type }}' }"
                    @click="currentTab = '{{ $type }}'; searchQuery = '';">
                {{ ucfirst($type) }}
            </button>
        @endforeach
        <button type="button"
                class="taxman-tab"
                :class="{ 'active': currentTab === 'subjects' }"
                @click="currentTab = 'subjects'; searchQuery = '';">
            Subjects
        </button>
    </div>

    {{-- Search and Toolbar (for nodes) --}}
    <div class="taxman-toolbar" x-show="currentTab !== 'subjects'">
        <div class="taxman-search-wrap">
            <span class="taxman-search-icon">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                </svg>
            </span>
            <input type="text"
                   class="taxman-search-input"
                   x-model="searchQuery"
                   placeholder="Search nodes by name, chassis, slug or kind..."
                   aria-label="Search taxonomy nodes">
            <button type="button" class="taxman-search-clear" x-show="searchQuery" @click="searchQuery = ''" aria-label="Clear search">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div>
            <button type="button" class="btn taxman-toolbar-primary" @click="$wire.newNode(currentTab)">
                + Add Top Node
            </button>
        </div>
    </div>

    <div x-show="searchQuery && currentTab !== 'subjects'" class="rev-section-note taxman-search-note">
        Showing matching results. Tree hierarchy view is flattened during search.
    </div>

    {{-- Trees --}}
    @foreach ($types as $type)
        @php
            $nodes = $nodesByType->get($type, collect());
        @endphp
        <div x-show="currentTab === '{{ $type }}'" wire:key="tree-{{ $type }}">
            <div class="taxman-tree">
                <div class="taxman-tree-header">
                    <h2>{{ ucfirst($type) }} Hierarchy</h2>
                    <span class="taxman-ctx">{{ count($nodes) }} nodes</span>
                </div>
                <div class="taxman-tree-content">
                    @php
                        // Precalculate parent paths that have children to render caret toggles.
                        $pathsWithChildren = [];
                        foreach ($nodes as $n) {
                            $parentPath = dirname($n->path);
                            if ($parentPath && $parentPath !== '.') {
                                $pathsWithChildren[$parentPath] = true;
                            }
                        }
                    @endphp

                    @forelse ($nodes as $node)
                        @php
                            $depth = max(0, substr_count($node->path, '/') - 1);
                            $hasChildren = isset($pathsWithChildren[$node->path]);
                        @endphp
                        <div class="taxman-node"
                             x-data="{ 
                                name: '{{ $node->name }}',
                                slug: '{{ $node->slug }}',
                                chassis: '{{ strtoupper(implode(', ', $node->chassisCodes())) }}',
                                kind: '{{ $node->kind }}',
                                path: '{{ $node->path }}'
                             }"
                             x-show="searchQuery ? matchesSearch(name, slug, chassis, kind, path) : !isCollapsed(path)"
                             wire:key="node-{{ $node->id }}">

                            {{-- Visual Nesting Connectors --}}
                            <div class="taxman-indent-spacer" x-show="!searchQuery">
                                @foreach (range(1, $depth) as $i)
                                    @if ($depth > 0)
                                        <div class="taxman-indent-col"></div>
                                    @endif
                                @endforeach
                            </div>

                            <div class="taxman-node-body">
                                <div class="taxman-node-details">
                                    {{-- Expand/Collapse button or bullet --}}
                                    @if ($hasChildren)
                                        <button type="button" class="taxman-toggle"
                                                :class="{ 'is-collapsed': collapsed['{{ $node->path }}'] }"
                                                @click="toggleCollapse('{{ $node->path }}')"
                                                x-show="!searchQuery"
                                                title="Toggle sub-nodes">
                                            <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                <path d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                    @else
                                        <span class="taxman-toggle taxman-toggle-placeholder" x-show="!searchQuery">
                                            <svg width="6" height="6" fill="currentColor" viewBox="0 0 24 24">
                                                <circle cx="12" cy="12" r="10"/>
                                            </svg>
                                        </span>
                                    @endif

                                    {{-- Node Kind Badge --}}
                                    <span class="taxman-badge taxman-badge-{{ in_array($node->kind, ['make', 'model', 'generation', 'trim']) ? $node->kind : 'generic' }}">
                                        {{ $node->kind }}
                                    </span>

                                    {{-- Node Name & Slug --}}
                                    <a class="taxman-name" href="{{ $node->url() }}" target="_blank" rel="noopener" title="View landing page">
                                        {{ $node->name }}
                                    </a>
                                    <span class="taxman-slug">{{ $node->slug }}</span>
                                </div>

                                {{-- Node Meta --}}
                                @if ($node->yearRange() || $node->chassisCodes())
                                    <div class="taxman-node-meta">
                                        {{-- Year range badge --}}
                                        @if ($node->yearRange())
                                            <span class="taxman-years">{{ $node->yearRange() }}</span>
                                        @endif

                                        {{-- Chassis Codes --}}
                                        @if ($node->chassisCodes())
                                            <span class="taxman-chassis">{{ strtoupper(implode(', ', $node->chassisCodes())) }}</span>
                                        @endif
                                    </div>
                                @endif

                                {{-- Row Actions --}}
                                <div class="taxman-rowactions">
                                    <button type="button" wire:click="newNode('{{ $type }}', {{ $node->id }})" title="Add child under this node">
                                        + Child
                                    </button>
                                    <button type="button" wire:click="editNode({{ $node->id }})">
                                        Edit
                                    </button>
                                    <button type="button" class="taxman-del" wire:click="deleteNode({{ $node->id }})"
                                            wire:confirm="Remove {{ $node->path }} and all sub-nodes?">
                                        Del
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="ex-noresults taxman-empty">No taxonomy nodes defined for {{ $type }}.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endforeach

    {{-- Subjects Tab --}}
    <div x-show="currentTab === 'subjects'">
        <div class="taxman-subjects-layout">
            {{-- Toolbar search --}}
            <div class="taxman-toolbar">
                <div class="taxman-search-wrap">
                    <span class="taxman-search-icon">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21l-4.35-4.35"/>
                        </svg>
                    </span>
                    <input type="text"
                           class="taxman-search-input"
                           x-model="searchQuery"
                           placeholder="Search subjects by name or slug..."
                           aria-label="Search subjects">
                    <button type="button" class="taxman-search-clear" x-show="searchQuery" @click="searchQuery = ''" aria-label="Clear search">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Subject Creator / Editor --}}
            <div class="taxman-subjects-form-card">
                <h3 class="rev-section taxman-section-title">
                    {{ $subjectId ? 'Edit Subject' : 'Add New Subject' }}
                </h3>
                <form class="taxman-subjectform" wire:submit.prevent="saveSubject">
                    <div class="taxman-form-group">
                        <label class="taxman-form-label">Subject Slug</label>
                        <input type="text" class="taxman-input" wire:model="subjectSlug" placeholder="eg: engine">
                    </div>
                    <div class="taxman-form-group">
                        <label class="taxman-form-label">Subject Name</label>
                        <input type="text" class="taxman-input" wire:model="subjectName" placeholder="eg: Engine & Drivetrain">
                    </div>
                    <div class="taxman-form-actions">
                        <button type="submit" class="rev-approve">
                            {{ $subjectId ? 'Save Subject' : 'Add Subject' }}
                        </button>
                        @if ($subjectId)
                            <button type="button" class="rev-reject taxman-form-button" wire:click="cancelSubject">
                                Cancel
                            </button>
                        @endif
                    </div>
                </form>
                @error('subjectSlug') <p class="ed-error taxman-field-error">{{ $message }}</p> @enderror
                @error('subjectName') <p class="ed-error taxman-field-error">{{ $message }}</p> @enderror
            </div>

            {{-- Subjects Grid --}}
            <div>
                <h3 class="rev-section taxman-section-title">All Subjects</h3>
                <div class="taxman-subjectlist">
                    @forelse ($subjects as $s)
                        <div class="taxman-subject"
                             data-name="{{ $s->name }}"
                             data-slug="{{ $s->slug }}"
                             x-show="searchQuery ? ($el.dataset.name.toLowerCase().includes(searchQuery.toLowerCase()) || $el.dataset.slug.toLowerCase().includes(searchQuery.toLowerCase())) : true;"
                             wire:key="subj-{{ $s->id }}">
                            <code>{{ $s->slug }}</code>
                            <span class="taxman-subject-name">{{ $s->name }}</span>
                            <div class="taxman-subject-actions">
                                <button type="button" wire:click="editSubject({{ $s->id }})">Edit</button>
                                <button type="button" class="taxman-del" wire:click="deleteSubject({{ $s->id }})"
                                        wire:confirm="Remove subject {{ $s->slug }}?">
                                    Del
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="ex-noresults taxman-empty taxman-empty-grid">No subjects found.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Node Editor Drawer --}}
    <div x-data="{ show: @entangle('showNodeForm') }" x-show="show" x-cloak>
        {{-- Backdrop --}}
        <div class="taxman-drawer-backdrop" x-show="show" x-transition.opacity @click="$wire.cancelNode()"></div>

        {{-- Drawer Panel --}}
        <div class="taxman-drawer"
             x-show="show"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             @keydown.escape.window="$wire.cancelNode()">

            <div class="taxman-drawer-header">
                <h2>{{ $nodeId ? 'Edit Node' : 'Add Node' }}</h2>
                <button type="button" class="taxman-drawer-close" @click="$wire.cancelNode()" aria-label="Close drawer">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="saveNode" class="taxman-drawer-form">
                <div class="taxman-drawer-body">
                    @if ($nodeParentId)
                        <div class="taxman-form-group">
                            <span class="taxman-form-label">Parent Node</span>
                            <span class="taxman-ctx">#{{ $nodeParentId }} ({{ $nodeParentPath ?? 'unknown parent' }})</span>
                        </div>
                    @else
                        <div class="taxman-form-group">
                            <label class="taxman-form-label">Type</label>
                            <select class="taxman-select" wire:model="nodeType">
                                @foreach ($types as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="taxman-form-group">
                        <label class="taxman-form-label">Kind</label>
                        <select class="taxman-select" wire:model="nodeKind">
                            <option value="make">make</option>
                            <option value="model">model</option>
                            <option value="generation">generation</option>
                            <option value="family">family</option>
                            <option value="trim">trim</option>
                        </select>
                        <p class="taxman-form-help">Select the taxonomy rank / kind of node.</p>
                        @error('nodeKind') <p class="ed-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="taxman-form-group">
                        <label class="taxman-form-label">Slug</label>
                        <input type="text" class="taxman-input" wire:model="nodeSlug" placeholder="eg: civic">
                        <p class="taxman-form-help">URL-safe unique segment. Lowercase, numbers and hyphens only.</p>
                        @error('nodeSlug') <p class="ed-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="taxman-form-group">
                        <label class="taxman-form-label">Name</label>
                        <input type="text" class="taxman-input" wire:model="nodeName" placeholder="eg: Civic">
                        <p class="taxman-form-help">Clean display name.</p>
                        @error('nodeName') <p class="ed-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="taxman-form-group">
                        <label class="taxman-form-label">Chassis Codes</label>
                        <input type="text" class="taxman-input" wire:model="nodeChassis" placeholder="eg: eg, eh, ej">
                        <p class="taxman-form-help">Comma-separated chassis codes (optional).</p>
                    </div>

                    <div class="taxman-year-grid">
                        <div class="taxman-form-group">
                            <label class="taxman-form-label">Start Year</label>
                            <input type="number" class="taxman-input" wire:model="nodeStartYear" placeholder="eg: 1992">
                            @error('nodeStartYear') <p class="ed-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="taxman-form-group">
                            <label class="taxman-form-label">End Year</label>
                            <input type="number" class="taxman-input" wire:model="nodeEndYear" placeholder="eg: 1995">
                            @error('nodeEndYear') <p class="ed-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="taxman-drawer-footer">
                    <button type="button" class="rev-reject" @click="$wire.cancelNode()">Cancel</button>
                    <button type="submit" class="rev-approve">{{ $nodeId ? 'Save Changes' : 'Create Node' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
