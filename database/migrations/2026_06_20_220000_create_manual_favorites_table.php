<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path', 1024);
            $table->string('name');
            $table->string('url', 1200);
            $table->timestamps();

            $table->unique(['user_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_favorites');
    }
};
