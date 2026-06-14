<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P5 community / personalization (instance-local, NOT part of the public fork path).
 * Favorites bookmark concrete articles; the garage holds a user's vehicles + equipment.
 * Facet interest is handled separately by the existing `follows` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Saved articles. References the derived `articles` index by id; if a reindex drops an
        // article the bookmark goes with it (cascade), which is the correct behaviour.
        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'article_id']);
        });

        // A user's vehicles. Free-form by design (the catalog is facet-derived, not a rigid
        // table): `engine`/`chassis` may carry a facet value (e.g. "b-series", "ek") so adding a
        // vehicle can seed matching follows, but any text is accepted.
        Schema::create('user_vehicles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('nickname')->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->string('make')->default('Honda');
            $t->string('model')->nullable();
            $t->string('chassis')->nullable();
            $t->string('engine')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index('user_id');
        });

        // A user's equipment (ECU, wideband, software, tools). Catalog-or-free-form, same idea.
        Schema::create('user_equipment', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind')->default('tool'); // ecu | wideband | software | tool
            $t->string('name');
            $t->string('detail')->nullable();
            $t->timestamps();
            $t->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_equipment');
        Schema::dropIfExists('user_vehicles');
        Schema::dropIfExists('favorites');
    }
};
