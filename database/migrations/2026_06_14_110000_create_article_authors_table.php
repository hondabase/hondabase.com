<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_global_name')->nullable()->after('discord_username');
            $table->boolean('is_legacy_author')->default(false)->after('is_staff');
            $table->string('legacy_source')->nullable()->after('is_legacy_author');
            $table->string('legacy_handle')->nullable()->after('legacy_source');
            $table->string('legacy_key')->nullable()->unique()->after('legacy_handle');
        });

        Schema::create('author_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('source');
            $table->string('handle');
            $table->string('alias_key')->unique();
            $table->timestamps();

            $table->index(['source', 'handle']);
        });

        Schema::create('article_authors', function (Blueprint $table) {
            $table->id();
            $table->string('repo_path');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('is_original')->default(false);
            $table->boolean('is_contributor')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['repo_path', 'user_id']);
            $table->index(['repo_path', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_authors');
        Schema::dropIfExists('author_aliases');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['legacy_key']);
            $table->dropColumn([
                'discord_global_name',
                'is_legacy_author',
                'legacy_source',
                'legacy_handle',
                'legacy_key',
            ]);
        });
    }
};
