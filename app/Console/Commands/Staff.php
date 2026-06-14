<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Grant or revoke the staff role (article management). The owner is staff implicitly and does
 * not need this. Identify the user by Discord username, Discord id, or numeric user id.
 *
 *   php artisan hondabase:staff FlavioP
 *   php artisan hondabase:staff 123456789012345678 --revoke
 */
class Staff extends Command
{
    protected $signature = 'hondabase:staff
        {user : Discord username, Discord id, or numeric user id}
        {--revoke : Remove the staff role instead of granting it}
        {--list : List current staff and exit}';

    protected $description = 'Grant or revoke the article-management staff role';

    public function handle(): int
    {
        if ($this->option('list')) {
            $staff = User::where('is_staff', true)->get(['id', 'discord_username', 'name']);
            if ($staff->isEmpty()) {
                $this->info('No users have the staff flag set (the owner is staff implicitly).');
                return self::SUCCESS;
            }
            $this->table(['ID', 'Discord', 'Name'], $staff->map(fn ($u) => [$u->id, $u->discord_username, $u->name])->all());
            return self::SUCCESS;
        }

        $needle = (string) $this->argument('user');
        $user = User::where('discord_username', $needle)
            ->orWhere('discord_id', $needle)
            ->when(ctype_digit($needle), fn ($q) => $q->orWhere('id', (int) $needle))
            ->first();

        if ($user === null) {
            $this->error("No user matched \"{$needle}\" (they must have signed in at least once).");
            return self::FAILURE;
        }

        $grant = !$this->option('revoke');
        $user->forceFill(['is_staff' => $grant])->save();

        $verb = $grant ? 'granted' : 'revoked';
        $this->info("Staff role {$verb} for {$user->discord_username} (#{$user->id}).");
        if (!$grant && $user->isOwner()) {
            $this->warn('Note: this user is the instance owner, so they remain staff regardless.');
        }

        return self::SUCCESS;
    }
}
