#!/usr/bin/env php
<?php

use App\Models\ArticleDraft;
use App\Models\User;
use App\Support\ArticleDocument;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::query()->whereRaw('LOWER(discord_username) = ?', ['viruxe'])->sole();
$records = [];

foreach (ArticleDraft::query()->where('user_id', $user->id)->orderBy('id')->get() as $draft) {
    $frontmatter = ArticleDocument::parse($draft->document)['fm'];
    foreach (($frontmatter['sources'] ?? []) as $source) {
        if (! is_array($source) || ! in_array($source['name'] ?? null, ['Icelord', 'Nthefastlane'], true)) {
            continue;
        }

        $url = trim((string) ($source['url'] ?? ''));
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            continue;
        }

        $records[] = [
            'draft_id' => $draft->id,
            'draft_title' => $draft->title,
            'article_path' => "{$draft->type}/{$draft->category}/{$draft->slug}",
            'site' => $source['name'],
            'source_title' => $source['title'] ?? null,
            'source_page_url' => $url,
        ];
    }
}

echo json_encode([
    'owner' => [
        'id' => $user->id,
        'name' => $user->name,
        'discord_username' => $user->discord_username,
    ],
    'sources' => $records,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
