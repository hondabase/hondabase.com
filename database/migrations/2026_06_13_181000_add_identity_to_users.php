<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('discord_id')->nullable()->unique()->after('id');
            $t->string('discord_username')->nullable()->after('name');
            $t->string('avatar')->nullable()->after('discord_username');
            $t->string('github_id')->nullable()->after('avatar');
            $t->string('github_login')->nullable()->after('github_id');
            $t->string('email')->nullable()->change();
            $t->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['discord_id', 'discord_username', 'avatar', 'github_id', 'github_login']);
        });
    }
};
