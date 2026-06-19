<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A topical subject (engine, electronics, suspension, ...), orthogonal to the product taxonomy.
 * DB-canonical.
 */
class Subject extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** Register a subject slug if it isn't already present. */
    public static function ensure(string $slug): void
    {
        if ($slug !== '' && ! self::where('slug', $slug)->exists()) {
            self::create(['slug' => $slug, 'name' => Str::headline($slug)]);
        }
    }
}
