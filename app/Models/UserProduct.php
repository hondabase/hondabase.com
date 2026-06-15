<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A Honda/Acura product a user owns - car, motorcycle, generator, outboard, ATV, etc. Free-form by
 * design (make/model/chassis/engine are plain text seeding facet follows); optionally pinned to a
 * real `taxonomy_node_id` for node-based follows + "fits my products". The member-facing garage UI
 * still calls these "vehicles" (owner decision); the model is the generalized storage.
 */
class UserProduct extends Model
{
    protected $guarded = [];

    protected $casts = ['year' => 'int'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The taxonomy node this product is pinned to (model/generation/trim), if any. */
    public function taxonomyNode(): BelongsTo
    {
        return $this->belongsTo(TaxonomyNode::class);
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
     * Facet follows this product implies ("kind:value" pairs the garage seeds so the user's feed
     * surfaces matching articles automatically). Sources: the free-form engine/chassis, plus any
     * make/model/generation/chassis facets derived from a pinned taxonomy node.
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
        foreach ($this->nodeFollows() as $f) {
            $out[] = $f;
        }

        return $out;
    }

    /** Follows derived from a pinned taxonomy node (make/model/generation + its chassis codes). */
    private function nodeFollows(): array
    {
        $node = $this->taxonomy_node_id ? $this->taxonomyNode : null;
        if (! $node) {
            return [];
        }

        $out = [['kind' => $node->kind, 'value' => $node->slug, 'label' => $node->name]];
        foreach ($node->chassisCodes() as $code) {
            $out[] = ['kind' => 'chassis', 'value' => Str::slug($code), 'label' => strtoupper($code)];
        }

        return $out;
    }
}
