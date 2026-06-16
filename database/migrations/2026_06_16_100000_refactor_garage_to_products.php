<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the tuning equipment table as requested.
        Schema::dropIfExists('user_equipment');

        // Add 'type' to user_products to support different categories.
        Schema::table('user_products', function (Blueprint $t) {
            $t->string('type')->default('car')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_products', function (Blueprint $t) {
            $t->dropColumn('type');
        });

        Schema::create('user_equipment', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('kind')->default('tool');
            $t->string('name');
            $t->string('detail')->nullable();
            $t->timestamps();
            $t->index('user_id');
        });
    }
};
