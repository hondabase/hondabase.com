<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Move all 'rom' category articles into 'ecu' and tag them.
        $romArticleIds = DB::table('articles')->where('category', 'rom')->pluck('id');

        DB::table('articles')->where('category', 'rom')->update(['category' => 'ecu']);

        $alreadyTagged = DB::table('article_facets')
            ->where('kind', 'tag')
            ->where('value', 'rom')
            ->pluck('article_id')
            ->flip();

        $insert = $romArticleIds
            ->reject(fn ($id) => $alreadyTagged->has($id))
            ->map(fn ($id) => ['article_id' => $id, 'kind' => 'tag', 'value' => 'rom', 'label' => 'rom'])
            ->values()
            ->all();

        if ($insert) {
            DB::table('article_facets')->insert($insert);
        }
    }

    public function down(): void
    {
        // Re-apply 'rom' category to articles that carry the rom tag but were previously in rom.
        // We can't perfectly distinguish "was in rom" from "had rom tag added by this migration",
        // so we restore category=rom for all articles currently tagged rom + in ecu.
        $ids = DB::table('article_facets')
            ->where('kind', 'tag')
            ->where('value', 'rom')
            ->join('articles', 'articles.id', '=', 'article_facets.article_id')
            ->where('articles.category', 'ecu')
            ->pluck('article_facets.article_id');

        DB::table('articles')->whereIn('id', $ids)->update(['category' => 'rom']);
    }
};
