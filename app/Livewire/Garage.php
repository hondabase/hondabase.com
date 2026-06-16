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
    // Product form
    public ?int $productId = null;

    public string $type = 'car';

    public string $nickname = '';

    public string $year = '';

    public string $make = 'Honda';

    public string $model = '';

    public string $chassis = '';

    public string $engine = '';

    public string $notes = '';

    public bool $showProductForm = false;

    protected function rules(): array
    {
        return [
            'type' => 'required|in:'.implode(',', UserProduct::TYPES),
            'nickname' => 'nullable|string|max:80',
            'year' => 'nullable|integer|min:1970|max:2030',
            'make' => 'nullable|string|max:40',
            'model' => 'nullable|string|max:60',
            'chassis' => 'nullable|string|max:20',
            'engine' => 'nullable|string|max:40',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function newProduct(): void
    {
        $this->resetProduct();
        $this->showProductForm = true;
    }

    public function editProduct(int $id): void
    {
        $v = auth()->user()->products()->findOrFail($id);
        $this->productId = $v->id;
        $this->type = $v->type;
        $this->nickname = $v->nickname ?? '';
        $this->year = (string) ($v->year ?? '');
        $this->make = $v->make ?? 'Honda';
        $this->model = $v->model ?? '';
        $this->chassis = $v->chassis ?? '';
        $this->engine = $v->engine ?? '';
        $this->notes = $v->notes ?? '';
        $this->showProductForm = true;
    }

    public function saveProduct(): void
    {
        $data = $this->validate();
        $user = auth()->user();

        $attrs = [
            'type' => $this->type,
            'nickname' => $this->nickname ?: null,
            'year' => $this->year !== '' ? (int) $this->year : null,
            'make' => $this->make ?: 'Honda',
            'model' => $this->model ?: null,
            'chassis' => $this->chassis ?: null,
            'engine' => $this->engine ?: null,
            'notes' => $this->notes ?: null,
        ];

        $product = $this->productId
            ? tap($user->products()->findOrFail($this->productId))->update($attrs)
            : $user->products()->create($attrs);

        $this->seedFollows($product);
        $this->resetProduct();
        session()->flash('garage_status', __('Product saved.'));
    }

    public function deleteProduct(int $id): void
    {
        auth()->user()->products()->whereKey($id)->delete();
        session()->flash('garage_status', __('Product removed.'));
    }

    /** Create follows implied by the product (engine/chassis), skipping any the user already has. */
    private function seedFollows(UserProduct $product): void
    {
        $user = $product->user;
        foreach ($product->impliedFollows() as $f) {
            $exists = $user->follows()->where('kind', $f['kind'])->where('value', $f['value'])->exists();
            if (! $exists) {
                $label = ArticleFacet::where('kind', $f['kind'])->where('value', $f['value'])->value('label') ?: $f['label'];
                $user->follows()->create(['kind' => $f['kind'], 'value' => $f['value'], 'label' => $label]);
            }
        }
    }

    private function resetProduct(): void
    {
        $this->reset(['productId', 'type', 'nickname', 'year', 'model', 'chassis', 'engine', 'notes', 'showProductForm']);
        $this->make = 'Honda';
        $this->resetValidation();
    }

    public function render(): View
    {
        $user = auth()->user();

        $defaults = match ($this->type) {
            'motorcycle' => [
                'models' => ['CBR600RR', 'CBR1000RR', 'Grom', 'Monkey', 'Africa Twin', 'Gold Wing', 'Rebel', 'Shadow'],
                'engines' => ['PC40E', 'SC59E'],
                'chassis' => ['PC40', 'SC59'],
                'nicknames' => ['Track Bike', 'Commuter', 'Trail', 'Cruiser'],
            ],
            'atv' => [
                'models' => ['FourTrax Recon', 'Rancher', 'Foreman', 'Rubicon', 'TRX250X', 'TRX450R', 'TRX400EX'],
                'engines' => [],
                'chassis' => [],
                'nicknames' => ['Mud Toy', 'Workhorse', 'Dune Runner'],
            ],
            'sxs' => [
                'models' => ['Talon 1000R', 'Talon 1000X', 'Pioneer 1000', 'Pioneer 700', 'Pioneer 500'],
                'engines' => [],
                'chassis' => [],
                'nicknames' => ['Trail Rig', 'Farm SxS', 'Rock Crawler'],
            ],
            'marine' => [
                'models' => ['BF2.3', 'BF5', 'BF8', 'BF15', 'BF20', 'BF115', 'BF150', 'BF200', 'BF250'],
                'engines' => [],
                'chassis' => [],
                'nicknames' => ['Kicker', 'Main Outboard', 'Trolling Motor'],
            ],
            'power_equipment' => [
                'models' => ['EU1000i', 'EU2200i', 'EU3000is', 'EU7000is', 'HRX217', 'HSS219'],
                'engines' => ['GX120', 'GX160', 'GX200', 'GX270', 'GX390', 'GCV170', 'GCV200'],
                'chassis' => [],
                'nicknames' => ['Camping Genny', 'Backup Power', 'Lawn Mower'],
            ],
            default => [ // car
                'models' => ['Civic', 'Integra', 'Accord', 'CRX', 'Prelude', 'NSX', 'S2000', 'RSX', 'Del Sol'],
                'engines' => ['B16', 'B18', 'B20', 'D15', 'D16', 'K20', 'K24', 'H22', 'F20C', 'F22C'],
                'chassis' => ['EF', 'EG', 'EK', 'EM', 'EP', 'ES', 'DC2', 'DC5', 'AP1', 'AP2', 'NA1', 'NA2', 'BB6'],
                'nicknames' => ['Daily', 'Project', 'Track Car', 'Winter Beater', 'Drag Car', 'My DC2'],
            ],
        };

        $placeholders = [
            'nickname' => \Illuminate\Support\Arr::random($defaults['nicknames']),
            'year' => (string) rand(1990, (int) date('Y')),
            'make' => 'Honda',
            'model' => \Illuminate\Support\Arr::random($defaults['models'] ?: ['Model']),
            'chassis' => $defaults['chassis'] ? \Illuminate\Support\Arr::random($defaults['chassis']) : '',
            'engine' => $defaults['engines'] ? \Illuminate\Support\Arr::random($defaults['engines']) : '',
        ];

        return view('livewire.garage', [
            'placeholders' => $placeholders,
            'nicknameList' => $defaults['nicknames'],
            'products' => $user->products()->latest()->get(),
            'productTypes' => UserProduct::TYPES,
            'makeList' => ArticleFacet::where('kind', 'make')->distinct()->orderBy('label')->pluck('label')
                ->concat(['Honda', 'Acura'])
                ->map(fn($l) => \Illuminate\Support\Str::headline($l))->unique()->values()->all(),
            'modelList' => ArticleFacet::where('kind', 'model')->distinct()->orderBy('label')->pluck('label')
                ->concat($defaults['models'])
                ->map(fn($l) => \Illuminate\Support\Str::headline($l))->unique()->values()->all(),
            'chassisList' => ArticleFacet::where('kind', 'chassis')->distinct()->orderBy('label')->pluck('label')
                ->concat($defaults['chassis'])
                ->map(fn($l) => strtoupper($l))->unique()->values()->all(),
            'engineList' => ArticleFacet::where('kind', 'engine')->distinct()->orderBy('label')->pluck('label')
                ->concat($defaults['engines'])
                ->unique()->values()->all(),
        ]);
    }
}
