<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dateTime('last_viewed_at')->nullable()->after('view_count')->index();
        });

        Schema::create('article_link_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('category')->index();
            $table->string('slug')->index();
            $table->string('locale', 12)->default('en')->index();
            $table->string('occurrence_key', 64);
            $table->unsignedInteger('ordinal');
            $table->text('url');
            $table->string('label')->nullable();
            $table->unsignedBigInteger('click_count')->default(0);
            $table->dateTime('last_clicked_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'category', 'slug', 'locale', 'occurrence_key'], 'article_link_clicks_identity_unique');
            $table->index(['type', 'category', 'slug', 'locale'], 'article_link_clicks_article_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_link_clicks');
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['last_viewed_at']);
            $table->dropColumn('last_viewed_at');
        });
    }
};
