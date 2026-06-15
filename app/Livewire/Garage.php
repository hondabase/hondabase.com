<?php

namespace App\Livewire;

use App\Models\ArticleFacet;
use App\Models\UserEquipment;
use App\Models\UserProduct;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The user's garage: CRUD over vehicles + equipment. Adding (or editing) a vehicle seeds
 * facet follows (engine family, chassis) so the personalized feed surfaces relevant articles
 * automatically. Removing a vehicle leaves the follows in place (the user may still want them).
 */
class Garage extends Component
{
    // Vehicle form
    public ?int $vehicleId = null;

    public string $nickname = '';

    public string $year = '';

    public string $make = 'Honda';

    public string $model = '';

    public string $chassis = '';

    public string $engine = '';

    public string $notes = '';

    public bool $showVehicleForm = false;

    // Equipment form
    public ?int $equipmentId = null;

    public string $eqKind = 'ecu';

    public string $eqName = '';

    public string $eqDetail = '';

    public bool $showEquipmentForm = false;

    protected function rules(): array
    {
        return [
            'nickname' => 'nullable|string|max:80',
            'year' => 'nullable|integer|min:1970|max:2030',
            'make' => 'nullable|string|max:40',
            'model' => 'nullable|string|max:60',
            'chassis' => 'nullable|string|max:20',
            'engine' => 'nullable|string|max:40',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function newVehicle(): void
    {
        $this->resetVehicle();
        $this->showVehicleForm = true;
    }

    public function editVehicle(int $id): void
    {
        $v = auth()->user()->products()->findOrFail($id);
        $this->vehicleId = $v->id;
        $this->nickname = $v->nickname ?? '';
        $this->year = (string) ($v->year ?? '');
        $this->make = $v->make ?? 'Honda';
        $this->model = $v->model ?? '';
        $this->chassis = $v->chassis ?? '';
        $this->engine = $v->engine ?? '';
        $this->notes = $v->notes ?? '';
        $this->showVehicleForm = true;
    }

    public function saveVehicle(): void
    {
        $data = $this->validate();
        $user = auth()->user();

        $attrs = [
            'nickname' => $this->nickname ?: null,
            'year' => $this->year !== '' ? (int) $this->year : null,
            'make' => $this->make ?: 'Honda',
            'model' => $this->model ?: null,
            'chassis' => $this->chassis ?: null,
            'engine' => $this->engine ?: null,
            'notes' => $this->notes ?: null,
        ];

        $vehicle = $this->vehicleId
            ? tap($user->products()->findOrFail($this->vehicleId))->update($attrs)
            : $user->products()->create($attrs);

        $this->seedFollows($vehicle);
        $this->resetVehicle();
        session()->flash('garage_status', __('Vehicle saved.'));
    }

    public function deleteVehicle(int $id): void
    {
        auth()->user()->products()->whereKey($id)->delete();
        session()->flash('garage_status', __('Vehicle removed.'));
    }

    /** Create follows implied by the vehicle (engine/chassis), skipping any the user already has. */
    private function seedFollows(UserProduct $vehicle): void
    {
        $user = $vehicle->user;
        foreach ($vehicle->impliedFollows() as $f) {
            $exists = $user->follows()->where('kind', $f['kind'])->where('value', $f['value'])->exists();
            if (! $exists) {
                $label = ArticleFacet::where('kind', $f['kind'])->where('value', $f['value'])->value('label') ?: $f['label'];
                $user->follows()->create(['kind' => $f['kind'], 'value' => $f['value'], 'label' => $label]);
            }
        }
    }

    public function newEquipment(): void
    {
        $this->resetEquipment();
        $this->showEquipmentForm = true;
    }

    public function editEquipment(int $id): void
    {
        $e = auth()->user()->equipment()->findOrFail($id);
        $this->equipmentId = $e->id;
        $this->eqKind = $e->kind;
        $this->eqName = $e->name;
        $this->eqDetail = $e->detail ?? '';
        $this->showEquipmentForm = true;
    }

    public function saveEquipment(): void
    {
        $this->validate([
            'eqKind' => 'required|in:'.implode(',', array_keys(UserEquipment::KINDS)),
            'eqName' => 'required|string|max:80',
            'eqDetail' => 'nullable|string|max:200',
        ]);
        $user = auth()->user();
        $attrs = ['kind' => $this->eqKind, 'name' => $this->eqName, 'detail' => $this->eqDetail ?: null];

        $this->equipmentId
            ? $user->equipment()->findOrFail($this->equipmentId)->update($attrs)
            : $user->equipment()->create($attrs);

        $this->resetEquipment();
        session()->flash('garage_status', __('Equipment saved.'));
    }

    public function deleteEquipment(int $id): void
    {
        auth()->user()->equipment()->whereKey($id)->delete();
        session()->flash('garage_status', __('Equipment removed.'));
    }

    private function resetVehicle(): void
    {
        $this->reset(['vehicleId', 'nickname', 'year', 'model', 'chassis', 'engine', 'notes', 'showVehicleForm']);
        $this->make = 'Honda';
        $this->resetValidation();
    }

    private function resetEquipment(): void
    {
        $this->reset(['equipmentId', 'eqName', 'eqDetail', 'showEquipmentForm']);
        $this->eqKind = 'ecu';
        $this->resetValidation();
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.garage', [
            'vehicles' => $user->products()->latest()->get(),
            'equipment' => $user->equipment()->orderBy('kind')->orderBy('name')->get(),
            'engineList' => ArticleFacet::where('kind', 'engine')->distinct()->orderBy('label')->pluck('label')->all(),
            'kinds' => UserEquipment::KINDS,
        ]);
    }
}
