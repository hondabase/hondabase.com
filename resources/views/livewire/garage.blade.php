<div class="garage">
    @if (session('garage_status'))
        <div class="flash flash-ok" role="status">{{ session('garage_status') }}</div>
    @endif

    {{-- ---------- Vehicles ---------- --}}
    <section class="garage-section">
        <div class="garage-head">
            <h2 class="section-head">{{ __('My vehicles') }}</h2>
            @unless ($showVehicleForm)
                <button type="button" class="btn btn-sm" wire:click="newVehicle">+ {{ __('Add vehicle') }}</button>
            @endunless
        </div>

        @if ($showVehicleForm)
            <form class="garage-form" wire:submit="saveVehicle">
                <div class="form-grid">
                    <label>{{ __('Nickname') }} <span class="opt">({{ __('optional') }})</span>
                        <input type="text" wire:model="nickname" placeholder="My DC2" maxlength="80">
                    </label>
                    <label>{{ __('Year') }}
                        <input type="number" wire:model="year" placeholder="2001" min="1970" max="2030">
                    </label>
                    <label>{{ __('Make') }}
                        <input type="text" wire:model="make" placeholder="Honda" maxlength="40">
                    </label>
                    <label>{{ __('Model') }}
                        <input type="text" wire:model="model" placeholder="Civic" maxlength="60">
                    </label>
                    <label>{{ __('Chassis') }} <span class="opt">({{ __('e.g. EK, DC2') }})</span>
                        <input type="text" wire:model="chassis" placeholder="EK" maxlength="20">
                    </label>
                    <label>{{ __('Engine') }}
                        <input type="text" list="engine-options" wire:model="engine" placeholder="B-Series" maxlength="40">
                        <datalist id="engine-options">
                            @foreach ($engineList as $e)<option value="{{ $e }}">@endforeach
                        </datalist>
                    </label>
                </div>
                <label class="full">{{ __('Notes') }} <span class="opt">({{ __('mods, build, anything') }})</span>
                    <textarea wire:model="notes" rows="2" maxlength="1000"></textarea>
                </label>
                @error('year') <p class="field-err">{{ $message }}</p> @enderror
                <p class="hint">{{ __('Adding an engine or chassis follows it, so matching new articles show up in your feed.') }}</p>
                <div class="form-actions">
                    <button type="submit" class="btn">{{ $vehicleId ? __('Save changes') : __('Add to garage') }}</button>
                    <button type="button" class="btn-link" wire:click="$set('showVehicleForm', false)">{{ __('Cancel') }}</button>
                </div>
            </form>
        @endif

        @forelse ($vehicles as $v)
            <div class="garage-card" wire:key="veh-{{ $v->id }}">
                <div class="garage-card-main">
                    <h3>{{ $v->label() }}</h3>
                    <p class="garage-meta">
                        @if ($v->engine)<span class="chip">{{ $v->engine }}</span>@endif
                        @if ($v->chassis)<span class="chip">{{ strtoupper($v->chassis) }}</span>@endif
                        @if ($v->model && !$v->nickname)<span class="chip">{{ $v->make }} {{ $v->model }}</span>@endif
                    </p>
                    @if ($v->notes)<p class="garage-notes">{{ $v->notes }}</p>@endif
                </div>
                <div class="garage-card-actions">
                    <button type="button" class="btn-link" wire:click="editVehicle({{ $v->id }})">{{ __('Edit') }}</button>
                    <button type="button" class="btn-link danger" wire:click="deleteVehicle({{ $v->id }})"
                        wire:confirm="{{ __('Remove this vehicle from your garage?') }}">{{ __('Remove') }}</button>
                </div>
            </div>
        @empty
            @unless ($showVehicleForm)
                <p class="empty">{{ __('No vehicles yet. Add one to personalize your feed.') }}</p>
            @endunless
        @endforelse
    </section>

    {{-- ---------- Equipment ---------- --}}
    <section class="garage-section">
        <div class="garage-head">
            <h2 class="section-head">{{ __('My equipment') }}</h2>
            @unless ($showEquipmentForm)
                <button type="button" class="btn btn-sm" wire:click="newEquipment">+ {{ __('Add equipment') }}</button>
            @endunless
        </div>

        @if ($showEquipmentForm)
            <form class="garage-form" wire:submit="saveEquipment">
                <div class="form-grid">
                    <label>{{ __('Type') }}
                        <select wire:model="eqKind">
                            @foreach ($kinds as $k => $kl)<option value="{{ $k }}">{{ __($kl) }}</option>@endforeach
                        </select>
                    </label>
                    <label>{{ __('Name') }}
                        <input type="text" wire:model="eqName" placeholder="Hondata s300" maxlength="80">
                    </label>
                    <label class="full">{{ __('Detail') }} <span class="opt">({{ __('optional') }})</span>
                        <input type="text" wire:model="eqDetail" placeholder="v3, firmware 4.x" maxlength="200">
                    </label>
                </div>
                @error('eqName') <p class="field-err">{{ $message }}</p> @enderror
                <div class="form-actions">
                    <button type="submit" class="btn">{{ $equipmentId ? __('Save changes') : __('Add equipment') }}</button>
                    <button type="button" class="btn-link" wire:click="$set('showEquipmentForm', false)">{{ __('Cancel') }}</button>
                </div>
            </form>
        @endif

        @forelse ($equipment as $e)
            <div class="garage-card" wire:key="eq-{{ $e->id }}">
                <div class="garage-card-main">
                    <h3><span class="chip chip-kind">{{ __($e->kindLabel()) }}</span> {{ $e->name }}</h3>
                    @if ($e->detail)<p class="garage-notes">{{ $e->detail }}</p>@endif
                </div>
                <div class="garage-card-actions">
                    <button type="button" class="btn-link" wire:click="editEquipment({{ $e->id }})">{{ __('Edit') }}</button>
                    <button type="button" class="btn-link danger" wire:click="deleteEquipment({{ $e->id }})"
                        wire:confirm="{{ __('Remove this equipment?') }}">{{ __('Remove') }}</button>
                </div>
            </div>
        @empty
            @unless ($showEquipmentForm)
                <p class="empty">{{ __('No equipment listed yet.') }}</p>
            @endunless
        @endforelse
    </section>
</div>
