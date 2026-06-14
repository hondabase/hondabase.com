<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserVehicle extends Model
{
    protected $guarded = [];

    protected $casts = ['year' => 'int'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A human label like "2001 Honda Civic (EK)" / "My DC2". */
    public function label(): string
    {
        if ($this->nickname) {
            return $this->nickname;
        }
        $parts = array_filter([
            $this->year ?: null,
            $this->make ?: null,
            $this->model ?: null,
        ]);
        $label = trim(implode(' ', $parts));
        if ($this->chassis) {
            $label = $label !== '' ? "{$label} ({$this->chassis})" : strtoupper($this->chassis);
        }

        return $label !== '' ? $label : 'Vehicle';
    }

    /**
     * Facet follows this vehicle implies (engine family, chassis): "kind:value" pairs the
     * garage seeds so the user's feed surfaces matching articles automatically.
     */
    public function impliedFollows(): array
    {
        $out = [];
        if ($this->engine) {
            $out[] = ['kind' => 'engine', 'value' => Str::slug($this->engine), 'label' => $this->engine];
        }
        if ($this->chassis) {
            $out[] = ['kind' => 'chassis', 'value' => Str::slug($this->chassis), 'label' => strtoupper($this->chassis)];
        }

        return $out;
    }
}
