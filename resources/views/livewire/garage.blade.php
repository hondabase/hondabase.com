<div class="garage">
    @if (session('garage_status'))
        <div class="flash flash-ok" role="status">{{ session('garage_status') }}</div>
    @endif

    {{-- ---------- Products ---------- --}}
    <section class="garage-section">
        <div class="garage-head">
            <h2 class="section-head">{{ __('My products') }}</h2>
            @unless ($showProductForm)
                <button type="button" class="btn btn-sm" wire:click="newProduct">+ {{ __('Add product') }}</button>
            @endunless
        </div>

        @if ($showProductForm)
            <form class="garage-form" wire:submit="saveProduct">
                <div class="form-grid">
                    <label class="full" wire:key="field-type">{{ __('Type') }}
                        <select wire:model.live="type">
                            @foreach ($productTypes as $t)
                                <option value="{{ $t }}">{{ __(ucfirst(str_replace('_', ' ', $t))) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label wire:key="field-nickname">{{ __('Nickname') }} <span class="text-muted font-normal text-[0.85em]">({{ __('optional') }})</span>
                        <input type="text" list="nickname-options" wire:model="nickname" placeholder="{{ $placeholders['nickname'] }}" maxlength="80">
                        <datalist id="nickname-options">
                            @foreach ($nicknameList as $n)<option value="{{ $n }}">@endforeach
                        </datalist>
                    </label>
                    <label wire:key="field-year">{{ __('Year') }}
                        <input type="text" inputmode="numeric" list="year-options" wire:model="year" placeholder="{{ $placeholders['year'] }}" maxlength="4">
                        <datalist id="year-options">
                            @foreach (range(date('Y'), 1980) as $y)<option value="{{ $y }}">@endforeach
                        </datalist>
                    </label>
                    <label wire:key="field-make">{{ __('Make') }}
                        <input type="text" list="make-options" wire:model="make" placeholder="{{ $placeholders['make'] }}" maxlength="40">
                        <datalist id="make-options">
                            @foreach ($makeList as $m)<option value="{{ $m }}">@endforeach
                        </datalist>
                    </label>
                    <label wire:key="field-model">{{ __('Model') }}
                        <input type="text" list="model-options" wire:model="model" placeholder="{{ $placeholders['model'] }}" maxlength="60">
                        <datalist id="model-options">
                            @foreach ($modelList as $m)<option value="{{ $m }}">@endforeach
                        </datalist>
                    </label>
                    @if (in_array($type, ['car', 'motorcycle', 'atv', 'sxs']))
                        <label wire:key="field-chassis">{{ __('Chassis') }} <span class="text-muted font-normal text-[0.85em]">({{ __('e.g. EK, DC2') }})</span>
                            <input type="text" list="chassis-options" wire:model="chassis" placeholder="{{ $placeholders['chassis'] }}" maxlength="20">
                            <datalist id="chassis-options">
                                @foreach ($chassisList as $c)<option value="{{ $c }}">@endforeach
                            </datalist>
                        </label>
                    @endif
                    @if (in_array($type, ['car', 'motorcycle', 'power_equipment']))
                        <label wire:key="field-engine">{{ __('Engine') }}
                            <input type="text" list="engine-options" wire:model="engine" placeholder="{{ $placeholders['engine'] }}" maxlength="40">
                            <datalist id="engine-options">
                                @foreach ($engineList as $e)<option value="{{ $e }}">@endforeach
                            </datalist>
                        </label>
                    @endif
                </div>
                <label class="full">{{ __('Notes') }} <span class="text-muted font-normal text-[0.85em]">({{ __('mods, build, anything') }})</span>
                    <textarea wire:model="notes" rows="2" maxlength="1000"></textarea>
                </label>
                @error('year') <p class="field-err">{{ $message }}</p> @enderror
                @error('type') <p class="field-err">{{ $message }}</p> @enderror
                <p class="text-muted text-[0.82rem] my-[0.4rem]">{{ __('Adding an engine or chassis (if applicable) follows it, so matching new articles show up in your feed.') }}</p>
                <div class="form-actions">
                    <button type="submit" class="btn">{{ $productId ? __('Save changes') : __('Add to garage') }}</button>
                    <button type="button" class="btn-link" wire:click="$set('showProductForm', false)">{{ __('Cancel') }}</button>
                </div>
            </form>
        @endif

        @forelse ($products as $p)
            <div class="garage-card" wire:key="prod-{{ $p->id }}">
                <div class="garage-card-main">
                    <h3>
                        <span class="chip chip-kind">{{ __(ucfirst(str_replace('_', ' ', $p->type))) }}</span>
                        {{ $p->label() }}
                    </h3>
                    <p class="garage-meta">
                        @if ($p->engine)<span class="chip">{{ $p->engine }}</span>@endif
                        @if ($p->chassis)<span class="chip">{{ strtoupper($p->chassis) }}</span>@endif
                        @if ($p->model && !$p->nickname)<span class="chip">{{ $p->make }} {{ $p->model }}</span>@endif
                    </p>
                    @if ($p->notes)<p class="garage-notes">{{ $p->notes }}</p>@endif
                </div>
                <div class="garage-card-actions">
                    <button type="button" class="btn-link" wire:click="editProduct({{ $p->id }})">{{ __('Edit') }}</button>
                    <button type="button" class="btn-link danger" wire:click="deleteProduct({{ $p->id }})"
                        wire:confirm="{{ __('Remove this product from your garage?') }}">{{ __('Remove') }}</button>
                </div>
            </div>
        @empty
            @unless ($showProductForm)
                <p class="text-muted italic">{{ __('No products yet. Add one to personalize your feed.') }}</p>
            @endunless
        @endforelse
    </section>
</div>
