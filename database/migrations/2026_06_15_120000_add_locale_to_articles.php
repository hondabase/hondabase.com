<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-locale derived index rows. The default locale (English) keeps `locale='en'`; a translation
 * is its own row (same type/category/slug, distinct locale + repo_path). The uniqueness key gains
 * the locale so en and pt rows for one article coexist. The index stays fully rebuildable via
 * `hondabase:reindex` (forkability invariant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $t) {
            $t->string('locale', 12)->default('en')->after('slug')->index();
            $t->dropUnique(['type', 'category', 'slug']);
            $t->unique(['type', 'category', 'slug', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $t) {
            $t->dropUnique(['type', 'category', 'slug', 'locale']);
            $t->unique(['type', 'category', 'slug']);
            $t->dropIndex(['locale']);
            $t->dropColumn('locale');
        });
    }
};
