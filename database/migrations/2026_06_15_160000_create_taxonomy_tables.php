<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product taxonomy + subject vocabulary, both DERIVED (rebuilt by the sync from content/_data/
 * taxonomy.json + subjects.json), so the forkability invariant holds: migrate:fresh + reindex
 * restores them. The taxonomy spans ALL Honda/Acura product lines (cars, motorcycles, marine,
 * power equipment, aircraft, ...), so `kind` is a free string driven by the JSON, not a fixed
 * car-only enum. `path` is the materialized type+slug chain (e.g. cars/honda/civic/eg).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_nodes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('taxonomy_nodes')->nullOnDelete();
            $t->string('type')->index();          // product line / URL root: cars, motorcycles, ...
            $t->string('kind');                    // make | model | generation | trim | family | ...
            $t->string('slug');                    // path segment, e.g. eg
            $t->string('name');                    // display, e.g. "5th Gen (EG)"
            $t->string('path')->unique();          // materialized, e.g. cars/honda/civic/eg
            $t->json('meta')->nullable();          // chassis_codes, start_year, end_year, ...
            $t->index(['type', 'path']);
        });

        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();          // engine, electronics, ...
            $t->string('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('taxonomy_nodes');
    }
};
