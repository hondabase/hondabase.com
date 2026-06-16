<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product taxonomy + subject vocabulary, both DB-CANONICAL (initially seeded from JSON but now
 * edited exclusively via the /admin/taxonomy control panel). The public SQL dump in the site repo
 * provides the forkability path: migrate:fresh + sql-import + reindex restores the site state.
 * The taxonomy spans ALL Honda/Acura product lines (cars, motorcycles, marine, power equipment,
 * aircraft, ...), so `kind` is a free string. `path` is the materialized type+slug chain
 * (e.g. cars/honda/civic/eg).
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
