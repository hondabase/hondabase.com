<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['updated_at' => 'datetime', 'view_count' => 'int'];

    public function facets(): HasMany
    {
        return $this->hasMany(ArticleFacet::class);
    }

    public function url(): string
    {
        return "/{$this->type}/{$this->category}/{$this->slug}";
    }
}
