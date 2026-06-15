<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Translation authoring (Phase C). A revision can now target a non-default locale: the same
 * suggest -> review -> commit pipeline writes the proposed body to a /{locale}/... mirror path
 * instead of the canonical English bundle. The locale is the app code ('en', 'pt', ...); the
 * repo_path already carries the /{locale} prefix, but storing it explicitly lets the commit job
 * reindex the right locale row and skip follower notifications for non-default locales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->string('locale', 12)->default('en')->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
