<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Co-located image uploads for new-article creation. When a revision creates (or edits) an
 * article that includes uploaded images, this holds the list of bundle-relative filenames the
 * commit job must write alongside the .md. The files themselves are staged out-of-band in
 * storage/app/pending-assets/{revision} until the queued App\Jobs\CommitArticle commits them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->json('assets')->nullable()->after('proposed_body');
        });
    }

    public function down(): void
    {
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->dropColumn('assets');
        });
    }
};
