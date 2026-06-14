<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ArticleAuthorService;
use Illuminate\Console\Command;

class CreditAuthor extends Command
{
    protected $signature = 'hondabase:credit-author {repo_path : Content-repo article path} {user : Discord id, username, or user id}';
    protected $description = 'Manually credit a known legacy HondaBase contributor';

    public function handle(ArticleAuthorService $authors): int
    {
        $repoPath = ltrim((string) $this->argument('repo_path'), '/');
        $contentRoot = rtrim((string) config('hondabase.content_path'), '/');
        if (str_contains($repoPath, '..') || !is_file("{$contentRoot}/{$repoPath}")) {
            $this->error('The article repo path does not exist.');
            return self::FAILURE;
        }

        $needle = (string) $this->argument('user');
        $user = User::where('is_legacy_author', false)
            ->where(function ($query) use ($needle) {
                $query->where('discord_id', $needle)->orWhere('discord_username', $needle);
                if (ctype_digit($needle)) {
                    $query->orWhere('id', (int) $needle);
                }
            })->first();

        if ($user === null) {
            $this->error('No non-legacy Discord user matched that identity.');
            return self::FAILURE;
        }

        $authors->creditContributor($repoPath, $user);
        $this->info("Credited {$user->displayName()} on {$repoPath}.");
        return self::SUCCESS;
    }
}
