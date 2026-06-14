<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds a 'conflicted' status. A revision conflicts when, at apply time, the on-disk article no
 * longer matches the body the edit was based on (main moved underneath it). Rather than silently
 * overwrite the newer content, the bot parks the revision as 'conflicted' so a human re-reviews.
 *
 * status is a native MariaDB enum, so the column is altered in place via raw DDL (the value set
 * is closed and rarely changes; a string column would lose that guarantee for no benefit).
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores Schema::enum() as an unconstrained string, so no DDL change is needed.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            'ALTER TABLE article_revisions MODIFY status '
            ."ENUM('pending', 'approved', 'rejected', 'conflicted') NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        DB::statement("UPDATE article_revisions SET status = 'pending' WHERE status = 'conflicted'");
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            'ALTER TABLE article_revisions MODIFY status '
            ."ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'"
        );
    }
};
