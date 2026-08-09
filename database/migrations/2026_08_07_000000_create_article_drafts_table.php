<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private, user-owned working copies for brand-new articles. Drafts are not revisions and never
 * enter the review/publish pipeline until their owner explicitly submits the creator form.
 * Their data is deliberately omitted from the public database dump.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->string('category')->nullable();
            $table->string('slug')->nullable();
            $table->longText('document');
            $table->string('note', 500)->nullable();
            $table->json('assets')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_drafts');
    }
};
