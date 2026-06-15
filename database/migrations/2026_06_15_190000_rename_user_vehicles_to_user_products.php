<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P5: the garage models all Honda/Acura PRODUCTS, not just vehicles. Rename `user_vehicles` ->
 * `user_products` (data preserved) and add an optional `taxonomy_node_id` so a product can be
 * pinned to a real taxonomy node (model/generation/trim/variant) for node-based follows + "fits
 * my products". The free-form make/model/chassis/engine columns stay as the fallback. The
 * member-facing UI keeps its "vehicle" wording (owner decision); only the storage generalizes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('user_vehicles', 'user_products');

        Schema::table('user_products', function (Blueprint $t) {
            $t->foreignId('taxonomy_node_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_products', function (Blueprint $t) {
            $t->dropConstrainedForeignId('taxonomy_node_id');
        });

        Schema::rename('user_products', 'user_vehicles');
    }
};
