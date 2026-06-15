<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One node in the product taxonomy (a make, model, generation, trim, family, ...). DERIVED from
 * content/_data/taxonomy.json by the sync. `path` is the materialized type+slug chain
 * (cars/honda/civic/eg) so a category path can be matched to a node with one lookup.
 */
class TaxonomyNode extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['meta' => 'array'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'compatibilities')->withPivot('source', 'meta');
    }

    /** Chassis codes declared in meta (e.g. ['eg', 'eh']); used to bridge applies_to -> node. */
    public function chassisCodes(): array
    {
        return array_map('strtolower', (array) ($this->meta['chassis_codes'] ?? []));
    }

    /** Year range label like "1992-1995" when both endpoints are present, else null. */
    public function yearRange(): ?string
    {
        $a = $this->meta['start_year'] ?? null;
        $b = $this->meta['end_year'] ?? null;

        return $a && $b ? "{$a}-{$b}" : null;
    }

    /** The /{type}/{path-without-type} public URL of this node's landing page. */
    public function url(): string
    {
        return '/'.$this->path;
    }
}
