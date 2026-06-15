{{-- Structured frontmatter fields. The user never sees raw YAML: these bind to typed Livewire
     properties (App\Livewire\Concerns\EditsFrontmatter) which are recomposed into canonical YAML and
     rejoined to the TipTap body on save. Unknown frontmatter keys are preserved untouched. --}}
<div class="ed-meta">
    <div class="ed-field">
        <label class="ed-label" for="fm-summary">{{ __('Summary') }}
            <span class="ed-opt">({{ __('one sentence; shows in search and the "applies to" panel') }})</span></label>
        <textarea id="fm-summary" rows="2" class="ed-input" wire:model.blur="fmSummary" maxlength="300"
                  placeholder="{{ __('What this is and when it matters.') }}"></textarea>
    </div>

    <div class="ed-metarow">
        <div class="ed-field">
            <label class="ed-label" for="fm-complexity">{{ __('Complexity') }}</label>
            <select id="fm-complexity" class="ed-input" wire:model.blur="fmComplexity">
                <option value="">&mdash;</option>
                <option value="beginner">{{ __('Beginner') }}</option>
                <option value="intermediate">{{ __('Intermediate') }}</option>
                <option value="advanced">{{ __('Advanced') }}</option>
            </select>
        </div>
        <div class="ed-field ed-field-grow">
            <label class="ed-label" for="fm-tags">{{ __('Tags') }} <span class="ed-opt">({{ __('comma separated') }})</span></label>
            <input id="fm-tags" type="text" class="ed-input" wire:model.blur="fmTags"
                   placeholder="ecu, obd1, diagnostics" autocomplete="off">
        </div>
    </div>

    <details class="ed-disclosure">
        <summary>{{ __('Applies to') }} <span class="ed-opt">({{ __('vehicles, engines, ECUs this covers') }})</span></summary>
        <div class="ed-disclosure-body">
            @forelse ($fmAppliesTo as $i => $row)
                <div class="ed-applies-row" wire:key="applies-{{ $i }}">
                    <input type="text" class="ed-input ed-applies-key" list="applies-fields"
                           wire:model.blur="fmAppliesTo.{{ $i }}.key" placeholder="{{ __('field') }}" aria-label="{{ __('Field name') }}">
                    <input type="text" class="ed-input ed-applies-val"
                           wire:model.blur="fmAppliesTo.{{ $i }}.value" placeholder="{{ __('values, comma separated') }}" aria-label="{{ __('Values') }}">
                    <button type="button" class="ed-rm" wire:click="removeAppliesTo({{ $i }})" title="{{ __('Remove') }}" aria-label="{{ __('Remove field') }}">&times;</button>
                </div>
            @empty
                <p class="ed-opt">{{ __('None yet.') }}</p>
            @endforelse
            <datalist id="applies-fields">
                <option value="engines"></option>
                <option value="ecus"></option>
                <option value="obd"></option>
                <option value="chassis"></option>
                <option value="models"></option>
                <option value="years"></option>
                <option value="brand"></option>
                <option value="systems"></option>
                <option value="scope"></option>
            </datalist>
            <button type="button" class="ed-add" wire:click="addAppliesTo">+ {{ __('Add field') }}</button>
        </div>
    </details>

    <details class="ed-disclosure">
        <summary>{{ __('Sources') }} <span class="ed-opt">({{ __('provenance for adapted or imported material') }})</span></summary>
        <div class="ed-disclosure-body">
            @forelse ($fmSources as $i => $src)
                <div class="ed-source" wire:key="source-{{ $i }}">
                    <div class="ed-source-grid">
                        <input type="text" class="ed-input" wire:model.blur="fmSources.{{ $i }}.name" placeholder="{{ __('Source name (e.g. pgmfi.org wiki)') }}" aria-label="{{ __('Source name') }}">
                        <input type="text" class="ed-input" wire:model.blur="fmSources.{{ $i }}.title" placeholder="{{ __('Page title') }}" aria-label="{{ __('Source page title') }}">
                        <input type="text" class="ed-input" wire:model.blur="fmSources.{{ $i }}.url" placeholder="{{ __('URL') }}" aria-label="{{ __('Source URL') }}">
                        <input type="text" class="ed-input" wire:model.blur="fmSources.{{ $i }}.license" placeholder="{{ __('License (e.g. CC BY-NC-SA 1.0)') }}" aria-label="{{ __('License') }}">
                        <input type="text" class="ed-input" wire:model.blur="fmSources.{{ $i }}.license_url" placeholder="{{ __('License URL') }}" aria-label="{{ __('License URL') }}">
                    </div>
                    <div class="ed-source-foot">
                        <label class="ed-checkline"><input type="checkbox" wire:model="fmSources.{{ $i }}.adapted"> {{ __('Adapted from this source') }}</label>
                        <button type="button" class="ed-rm ed-rm-text" wire:click="removeSource({{ $i }})">{{ __('Remove source') }}</button>
                    </div>
                </div>
            @empty
                <p class="ed-opt">{{ __('No sources. Add one for any adapted or imported material.') }}</p>
            @endforelse
            <button type="button" class="ed-add" wire:click="addSource">+ {{ __('Add source') }}</button>
        </div>
    </details>
</div>
