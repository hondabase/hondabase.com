<div class="garage">
    @if (session('garage_status'))
        <div class="flash flash-ok" role="status">{{ session('garage_status') }}</div>
    @endif

    {{-- ---------- Vehicles ---------- --}}
    <section class="garage-section">
        <div class="garage-head">
            <h2 class="section-head">My vehicles</h2>
            @unless ($showVehicleForm)
                <button type="button" class="btn btn-sm" wire:click="newVehicle">+ Add vehicle</button>
            @endunless
        </div>

        @if ($showVehicleForm)
            <form class="garage-form" wire:submit="saveVehicle">
                <div class="form-grid">
                    <label>Nickname <span class="opt">(optional)</span>
                        <input type="text" wire:model="nickname" placeholder="My DC2" maxlength="80">
                    </label>
                    <label>Year
                        <input type="number" wire:model="year" placeholder="2001" min="1970" max="2030">
                    </label>
                    <label>Make
                        <input type="text" wire:model="make" placeholder="Honda" maxlength="40">
                    </label>
                    <label>Model
                        <input type="text" wire:model="model" placeholder="Civic" maxlength="60">
                    </label>
                    <label>Chassis <span class="opt">(e.g. EK, DC2)</span>
                        <input type="text" wire:model="chassis" placeholder="EK" maxlength="20">
                    </label>
                    <label>Engine
                        <input type="text" list="engine-options" wire:model="engine" placeholder="B-Series" maxlength="40">
                        <datalist id="engine-options">
                            @foreach ($engineList as $e)<option value="{{ $e }}">@endforeach
                        </datalist>
                    </label>
                </div>
                <label class="full">Notes <span class="opt">(mods, build, anything)</span>
                    <textarea wire:model="notes" rows="2" maxlength="1000"></textarea>
                </label>
                @error('year') <p class="field-err">{{ $message }}</p> @enderror
                <p class="hint">Adding an engine or chassis follows it, so matching new articles show up in your feed.</p>
                <div class="form-actions">
                    <button type="submit" class="btn">{{ $vehicleId ? 'Save changes' : 'Add to garage' }}</button>
                    <button type="button" class="btn-link" wire:click="$set('showVehicleForm', false)">Cancel</button>
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
                    <button type="button" class="btn-link" wire:click="editVehicle({{ $v->id }})">Edit</button>
                    <button type="button" class="btn-link danger" wire:click="deleteVehicle({{ $v->id }})"
                        wire:confirm="Remove this vehicle from your garage?">Remove</button>
                </div>
            </div>
        @empty
            @unless ($showVehicleForm)
                <p class="empty">No vehicles yet. Add one to personalize your feed.</p>
            @endunless
        @endforelse
    </section>

    {{-- ---------- Equipment ---------- --}}
    <section class="garage-section">
        <div class="garage-head">
            <h2 class="section-head">My equipment</h2>
            @unless ($showEquipmentForm)
                <button type="button" class="btn btn-sm" wire:click="newEquipment">+ Add equipment</button>
            @endunless
        </div>

        @if ($showEquipmentForm)
            <form class="garage-form" wire:submit="saveEquipment">
                <div class="form-grid">
                    <label>Type
                        <select wire:model="eqKind">
                            @foreach ($kinds as $k => $kl)<option value="{{ $k }}">{{ $kl }}</option>@endforeach
                        </select>
                    </label>
                    <label>Name
                        <input type="text" wire:model="eqName" placeholder="Hondata s300" maxlength="80">
                    </label>
                    <label class="full">Detail <span class="opt">(optional)</span>
                        <input type="text" wire:model="eqDetail" placeholder="v3, firmware 4.x" maxlength="200">
                    </label>
                </div>
                @error('eqName') <p class="field-err">{{ $message }}</p> @enderror
                <div class="form-actions">
                    <button type="submit" class="btn">{{ $equipmentId ? 'Save changes' : 'Add equipment' }}</button>
                    <button type="button" class="btn-link" wire:click="$set('showEquipmentForm', false)">Cancel</button>
                </div>
            </form>
        @endif

        @forelse ($equipment as $e)
            <div class="garage-card" wire:key="eq-{{ $e->id }}">
                <div class="garage-card-main">
                    <h3><span class="chip chip-kind">{{ $e->kindLabel() }}</span> {{ $e->name }}</h3>
                    @if ($e->detail)<p class="garage-notes">{{ $e->detail }}</p>@endif
                </div>
                <div class="garage-card-actions">
                    <button type="button" class="btn-link" wire:click="editEquipment({{ $e->id }})">Edit</button>
                    <button type="button" class="btn-link danger" wire:click="deleteEquipment({{ $e->id }})"
                        wire:confirm="Remove this equipment?">Remove</button>
                </div>
            </div>
        @empty
            @unless ($showEquipmentForm)
                <p class="empty">No equipment listed yet.</p>
            @endunless
        @endforelse
    </section>
</div>
