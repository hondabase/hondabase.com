<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data minimization: the app authenticates only via Discord OAuth, so it never collects an
 * email or password (verified: 0 of 140 users have an email). Drop the unused Laravel auth
 * columns so there is simply nothing sensitive to store or leak - the cleanest privacy posture
 * now that the DB backup is committed to the (public) site source. `remember_token` is kept
 * (framework remember-me) but excluded from the dump as a session credential.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique('users_email_unique');
            $t->dropColumn(['email', 'email_verified_at', 'password']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('email')->nullable()->unique()->after('name');
            $t->timestamp('email_verified_at')->nullable()->after('email');
            $t->string('password')->nullable()->after('email_verified_at');
        });
    }
};
