<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ArticleAuthorService;
use Illuminate\Console\Command;

class MergeAuthor extends Command
{
    protected $signature = 'hondabase:merge-author {legacy : Legacy user id, key, or handle} {user : Discord id, username, or user id}';

    protected $description = 'Merge a verified legacy ghost author into a Discord user';

    public function handle(ArticleAuthorService $authors): int
    {
        $legacyNeedle = (string) $this->argument('legacy');
        $legacy = User::where('is_legacy_author', true)
            ->where(function ($query) use ($legacyNeedle) {
                $query->where('legacy_key', $legacyNeedle)->orWhere('legacy_handle', $legacyNeedle);
                if (ctype_digit($legacyNeedle)) {
                    $query->orWhere('id', (int) $legacyNeedle);
                }
            })->first();

        $userNeedle = (string) $this->argument('user');
        $user = User::where('is_legacy_author', false)
            ->where(function ($query) use ($userNeedle) {
                $query->where('discord_id', $userNeedle)->orWhere('discord_username', $userNeedle);
                if (ctype_digit($userNeedle)) {
                    $query->orWhere('id', (int) $userNeedle);
                }
            })->first();

        if ($legacy === null || $user === null) {
            $this->error('Both a legacy ghost and a non-legacy Discord user must exist.');

            return self::FAILURE;
        }

        $label = $legacy->displayName();
        $authors->mergeLegacyAuthor($legacy, $user);
        $this->info("Merged {$label} into {$user->displayName()}.");

        return self::SUCCESS;
    }
}
