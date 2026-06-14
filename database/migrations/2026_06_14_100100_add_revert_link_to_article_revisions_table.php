<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reversibility: a revert is just another tracked edit whose committed content restores an
 * earlier snapshot. This column links the revert back to the revision it undoes, so the audit
 * trail reads cleanly (and a revert is itself revertible). Also indexes the per-article lookup
 * used by the history view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->foreignId('reverts_revision_id')->nullable()->after('commit_sha')
                ->constrained('article_revisions')->nullOnDelete();
            $table->index(['type', 'category', 'slug'], 'article_revisions_article_idx');
        });
    }

    public function down(): void
    {
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->dropForeign(['reverts_revision_id']);
            $table->dropColumn('reverts_revision_id');
            $table->dropIndex('article_revisions_article_idx');
        });
    }
};
