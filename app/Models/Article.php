<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['updated_at' => 'datetime', 'view_count' => 'int', 'last_viewed_at' => 'datetime', 'is_hidden' => 'bool'];

    public function facets(): HasMany
    {
        return $this->hasMany(ArticleFacet::class);
    }

    public function compatibilities(): HasMany
    {
        return $this->hasMany(Compatibility::class);
    }

    /**
     * Public URL for this row's locale. The default locale is canonical and unprefixed; other
     * locales get a /{locale} prefix. The explorer sets `locale` transiently on a canonical row
     * to render its localized link, so this respects whatever locale the row currently carries.
     */
    public function url(): string
    {
        $locale = $this->locale ?? Locales::default();
        $prefix = Locales::isDefault($locale) ? '' : "/{$locale}";

        return "{$prefix}/{$this->type}/{$this->category}/{$this->slug}";
    }
}
