<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which taxonomy node(s) an article fits. DERIVED by the sync (forkability): rebuilt from each
 * article's location + front matter, never hand-edited. `source` records how the link was found:
 *   - inherited: the article physically lives inside that product folder (cars/honda/civic/eg/...)
 *   - explicit:  a generic/subject-centric article declares `fits:` (or legacy `applies_to`) for it
 * `meta` carries per-fit notes/trim/tags from `fits:`. Cascades with the article row, so a reindex
 * that drops an article drops its links too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compatibilities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->foreignId('taxonomy_node_id')->constrained()->cascadeOnDelete();
            $t->string('source')->default('inherited'); // inherited | explicit
            $t->json('meta')->nullable();
            $t->unique(['article_id', 'taxonomy_node_id']);
            $t->index('taxonomy_node_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compatibilities');
    }
};
