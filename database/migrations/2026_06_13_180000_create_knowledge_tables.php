<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Derived index of articles, rebuilt from the content repo by `hondabase:reindex`.
        Schema::create('articles', function (Blueprint $t) {
            $t->id();
            $t->string('type')->index();
            $t->string('category')->index();
            $t->string('slug');
            $t->string('title');
            $t->text('summary')->nullable();
            $t->string('complexity')->nullable();
            $t->longText('body_text')->nullable();
            $t->string('repo_path')->unique();
            $t->dateTime('updated_at')->nullable();
            $t->unsignedBigInteger('view_count')->default(0);
            $t->unique(['type', 'category', 'slug']);
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $t->fullText(['title', 'summary', 'body_text']);
            }
        });

        // Flexible classification: type/category/tag plus every applies_to field becomes a
        // (kind, value) facet. This is what users follow and what the explorer filters on.
        Schema::create('article_facets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->string('kind');
            $t->string('value');
            $t->string('label');
            $t->unique(['article_id', 'kind', 'value']);
            $t->index(['kind', 'value']);
        });

        // A user follows a facet (category, tag, engine family, OBD gen, chassis, ...).
        Schema::create('follows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind');
            $t->string('value');
            $t->string('label')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'kind', 'value']);
            $t->index(['kind', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
        Schema::dropIfExists('article_facets');
        Schema::dropIfExists('articles');
    }
};
