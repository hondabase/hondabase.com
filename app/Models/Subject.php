<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A topical subject (engine, electronics, suspension, ...), orthogonal to the product taxonomy.
 * DERIVED by the sync from content/_data/subjects.json plus any subject segment found in content.
 */
class Subject extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
