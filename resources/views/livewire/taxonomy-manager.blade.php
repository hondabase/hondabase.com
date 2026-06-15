<div class="taxman">
    <div class="rev-head">
        <h1>Product taxonomy</h1>
        <button type="button" class="rev-link" wire:click="rebuildIndex" wire:loading.attr="disabled">Rebuild article links →</button>
    </div>
    <p class="rev-section-note">The taxonomy lives in the database (this panel is the source of truth).
        Adding nodes and editing metadata is free; renaming or removing a node with articles filed
        under it is blocked. Article compatibility updates after a rebuild.</p>

    @if ($message)
        <div class="flash flash-ok" role="status">{{ $message }}</div>
    @endif

    {{-- Node editor --}}
    @if ($showNodeForm)
        <form class="taxman-form" wire:submit.prevent="saveNode">
            <h2 class="rev-section">{{ $nodeId ? 'Edit node' : 'Add node' }}
                @if ($nodeParentId) <span class="taxman-ctx">under #{{ $nodeParentId }}</span> @endif</h2>
            <div class="taxman-grid">
                @unless ($nodeParentId)
                    <label>Type
                        <select wire:model="nodeType">
                            @foreach ($types as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                        </select>
                    </label>
                @endunless
                <label>Kind <input type="text" wire:model="nodeKind" placeholder="make | model | generation | trim"></label>
                <label>Slug <input type="text" wire:model="nodeSlug" placeholder="eg"></label>
                <label>Name <input type="text" wire:model="nodeName" placeholder="5th Gen (EG)"></label>
                <label>Chassis codes <input type="text" wire:model="nodeChassis" placeholder="eg, eh"></label>
                <label>Start year <input type="number" wire:model="nodeStartYear" placeholder="1992"></label>
                <label>End year <input type="number" wire:model="nodeEndYear" placeholder="1995"></label>
            </div>
            @error('nodeKind') <p class="ed-error">{{ $message }}</p> @enderror
            @error('nodeSlug') <p class="ed-error">{{ $message }}</p> @enderror
            @error('nodeName') <p class="ed-error">{{ $message }}</p> @enderror
            <div class="taxman-actions">
                <button type="submit" class="rev-approve">{{ $nodeId ? 'Save' : 'Add' }}</button>
                <button type="button" class="rev-reject" wire:click="cancelNode">Cancel</button>
            </div>
        </form>
    @endif

    {{-- Tree per product line --}}
    @foreach ($nodesByType as $type => $nodes)
        <section class="taxman-tree">
            <h2 class="rev-section">{{ ucfirst($type) }}
                <button type="button" class="taxman-add" wire:click="newNode('{{ $type }}')">+ make/top node</button></h2>
            @foreach ($nodes as $node)
                @php $depth = max(0, substr_count($node->path, '/') - 1); @endphp
                <div class="taxman-node" style="padding-left: {{ $depth * 1.3 }}rem" wire:key="node-{{ $node->id }}">
                    <span class="taxman-kind">{{ $node->kind }}</span>
                    <a class="taxman-name" href="/{{ $node->path }}" target="_blank" rel="noopener">{{ $node->name }}</a>
                    <code class="taxman-slug">{{ $node->slug }}</code>
                    @if ($node->yearRange())<span class="taxman-years">{{ $node->yearRange() }}</span>@endif
                    @if ($node->chassisCodes())<span class="taxman-chassis">{{ strtoupper(implode(', ', $node->chassisCodes())) }}</span>@endif
                    <span class="taxman-rowactions">
                        <button type="button" wire:click="newNode('{{ $type }}', {{ $node->id }})">+ child</button>
                        <button type="button" wire:click="editNode({{ $node->id }})">edit</button>
                        <button type="button" class="taxman-del" wire:click="deleteNode({{ $node->id }})"
                                wire:confirm="Remove {{ $node->path }} and any sub-nodes?">del</button>
                    </span>
                </div>
            @endforeach
        </section>
    @endforeach

    {{-- Subjects --}}
    <section class="taxman-subjects">
        <h2 class="rev-section">Subjects</h2>
        <form class="taxman-subjectform" wire:submit.prevent="saveSubject">
            <input type="text" wire:model="subjectSlug" placeholder="slug (engine)">
            <input type="text" wire:model="subjectName" placeholder="Name (Engine & Drivetrain)">
            <button type="submit" class="rev-approve">{{ $subjectId ? 'Save' : 'Add' }}</button>
            @error('subjectSlug') <span class="ed-error">{{ $message }}</span> @enderror
        </form>
        <div class="taxman-subjectlist">
            @foreach ($subjects as $s)
                <span class="taxman-subject" wire:key="subj-{{ $s->id }}">
                    <code>{{ $s->slug }}</code> {{ $s->name }}
                    <button type="button" wire:click="editSubject({{ $s->id }})">edit</button>
                    <button type="button" class="taxman-del" wire:click="deleteSubject({{ $s->id }})" wire:confirm="Remove subject {{ $s->slug }}?">del</button>
                </span>
            @endforeach
        </div>
    </section>
</div>
