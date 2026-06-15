<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot linking an article to a taxonomy node it fits (inherited from its folder, or explicit via
 * `fits:`/`applies_to`). DERIVED by the sync.
 */
class Compatibility extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['meta' => 'array'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(TaxonomyNode::class, 'taxonomy_node_id');
    }
}
