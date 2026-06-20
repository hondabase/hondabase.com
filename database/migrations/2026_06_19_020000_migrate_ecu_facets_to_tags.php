<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Delete ecu rows where a tag with the same article+value already exists.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('
                DELETE FROM article_facets
                WHERE id IN (
                    SELECT af.id FROM article_facets af
                    INNER JOIN article_facets af2
                        ON af2.article_id = af.article_id
                        AND af2.kind = "tag"
                        AND af2.value = af.value
                    WHERE af.kind = "ecu"
                )
            ');
        } else {
            DB::statement('
                DELETE af FROM article_facets af
                INNER JOIN article_facets af2
                    ON af2.article_id = af.article_id
                    AND af2.kind = "tag"
                    AND af2.value = af.value
                WHERE af.kind = "ecu"
            ');
        }

        DB::table('article_facets')
            ->where('kind', 'ecu')
            ->update(['kind' => 'tag']);
    }

    public function down(): void
    {
        // Cannot reliably reverse: we don't know which tags were originally ecus.
    }
};
