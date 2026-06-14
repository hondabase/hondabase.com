<?php

namespace App\Console\Commands;

use App\Models\ArticleAuthor;
use App\Models\AuthorAlias;
use App\Models\User;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class ImportPgmfiAuthors extends Command
{
    protected $signature = 'hondabase:import-pgmfi-authors {--check : Verify without changing files or the database}';

    protected $description = 'Backfill PGMFI source metadata and original article authors';

    private const AUTHOR_LABELS = [
        'guest' => 'Anonymous PGMFI contributor',
        'twikiguest' => 'Anonymous PGMFI contributor',
    ];

    private const MANUAL_TOPICS = [
        'honda-acura-trouble-codes' => 'HondaErrorCodes',
        'how-to-check-obd1-ecu-codes' => 'CheckingErrorCodes',
    ];

    public function handle(): int
    {
        $contentRoot = rtrim((string) config('hondabase.content_path'), '/');
        $sourceRoot = rtrim((string) config('hondabase.pgmfi_source_path'), '/');
        $topicMap = $this->topicMap($sourceRoot);
        $records = [];
        $errors = [];

        foreach (glob("{$contentRoot}/cars/electronics/*/*.md") ?: [] as $file) {
            $repoPath = ltrim(substr($file, strlen($contentRoot)), '/');
            if (in_array($repoPath, (array) config('hondabase.pgmfi_non_ports', []), true)) {
                continue;
            }

            $slug = strtolower(basename(dirname($file)));
            $topic = self::MANUAL_TOPICS[$slug] ?? ($topicMap[$slug] ?? null);
            if ($topic === null) {
                $errors[] = basename(dirname($file)).': no PGMFI topic mapping';

                continue;
            }

            $authors = $this->authors($sourceRoot, $topic);
            if ($authors === []) {
                $errors[] = basename(dirname($file)).": no authors recovered from {$topic}";

                continue;
            }

            $records[] = [
                'file' => $file,
                'repo_path' => $repoPath,
                'source' => $this->sourceMetadata($topic),
                'authors' => $authors,
            ];
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $changed = 0;
        $creditCount = 0;
        foreach ($records as $record) {
            $raw = (string) file_get_contents($record['file']);
            $desired = $this->withSource($raw, $record['source']);

            if ($this->option('check')) {
                if ($desired !== $raw) {
                    $errors[] = $record['repo_path'].': source metadata is missing or stale';
                }
                if (! $this->authorsAreCurrent($record['repo_path'], $record['authors'])) {
                    $errors[] = $record['repo_path'].': database original authors are missing or stale';
                }

                continue;
            }

            if ($desired !== $raw) {
                file_put_contents($record['file'], $desired);
                $changed++;
            }
            $creditCount += $this->syncAuthors($record['repo_path'], $record['authors']);
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($this->option('check')) {
            $this->info('PGMFI attribution is complete for '.count($records).' electronics articles.');
        } else {
            $orphanLegacyIds = User::where('is_legacy_author', true)
                ->whereDoesntHave('articleAuthorships')
                ->pluck('id');
            AuthorAlias::whereIn('user_id', $orphanLegacyIds)->delete();
            User::whereIn('id', $orphanLegacyIds)->delete();
            $this->info('Imported PGMFI attribution for '.count($records)." articles ({$changed} files updated, {$creditCount} author links synced).");
        }

        return self::SUCCESS;
    }

    private function topicMap(string $sourceRoot): array
    {
        $map = [];
        foreach (glob("{$sourceRoot}/bin/view/Library/*.html") ?: [] as $file) {
            $topic = basename($file, '.html');
            $key = $this->kebab($topic);
            if (! isset($map[$key]) || (str_contains($map[$key], ' ') && ! str_contains($topic, ' '))) {
                $map[$key] = $topic;
            }
        }

        return $map;
    }

    private function authors(string $sourceRoot, string $topic): array
    {
        $revisions = [];
        foreach ([
            "{$sourceRoot}/bin/rdiff/Library/{$topic}.html",
            "{$sourceRoot}/bin/view/Library/{$topic}.html",
        ] as $file) {
            if (! is_file($file)) {
                continue;
            }
            $raw = mb_convert_encoding((string) file_get_contents($file), 'UTF-8', 'ISO-8859-1');

            if (preg_match_all('#<a[^>]*>r([0-9.]+)</a>\s*-\s*[^<]*?-\s*Home\.([^)<>]+)\)#i', $raw, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $this->recordRevision($revisions, $match[1], $match[2]);
                }
            }
            if (preg_match_all('/Revision r([0-9.]+) - [^\r\n<]*? GMT - (?:Home\.)?([^\r\n<]+)/i', $raw, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $this->recordRevision($revisions, $match[1], $match[2]);
                }
            }
            if (preg_match('#<b>Revision:</b>(.*?)</FONT>#is', $raw, $match)) {
                $line = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML401, 'UTF-8')));
                if (preg_match('/r([0-9.]+).*?GMT(?: - (.*?))?(?: \?)?$/u', $line, $footer)) {
                    $this->recordRevision($revisions, $footer[1], $footer[2] ?? '');
                }
            }
        }

        uksort($revisions, fn (string $a, string $b) => version_compare($a, $b));
        $seen = [];
        $authors = [];
        foreach ($revisions as $author) {
            $key = mb_strtolower($author, 'UTF-8');
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $authors[] = $author;
            }
        }

        return $authors;
    }

    private function recordRevision(array &$revisions, string $revision, string $author): void
    {
        $author = trim(html_entity_decode(strip_tags($author), ENT_QUOTES | ENT_HTML401, 'UTF-8'));
        $author = preg_replace('/^Home\./i', '', $author);
        $author = rtrim($author, "? \t\n\r\0\x0B");
        $author = self::AUTHOR_LABELS[mb_strtolower($author, 'UTF-8')] ?? $author;
        if ($author !== '') {
            $revisions[$revision] = $author;
        }
    }

    private function sourceMetadata(string $topic): array
    {
        return [
            'name' => 'pgmfi.org wiki',
            'title' => $this->spacedTitle($topic),
            'url' => '/pgmfi/wiki/library/'.$this->kebab($topic),
            'license' => 'CC BY-NC-SA 1.0',
            'license_url' => 'https://creativecommons.org/licenses/by-nc-sa/1.0/',
            'adapted' => true,
        ];
    }

    private function withSource(string $raw, array $source): string
    {
        $sources = [$source];
        if (! preg_match('/^---\s*?\r?\n(.*?)\r?\n---\s*?\r?\n(.*)$/s', $raw, $match)) {
            return "---\n".Yaml::dump(['sources' => $sources], 4, 2)."---\n\n".ltrim($raw);
        }

        $frontmatter = Yaml::parse($match[1]);
        $frontmatter = is_array($frontmatter) ? $frontmatter : [];
        if (($frontmatter['sources'] ?? null) === $sources) {
            return $raw;
        }

        if (! array_key_exists('sources', $frontmatter)) {
            $sourceYaml = rtrim(Yaml::dump(['sources' => $sources], 4, 2));

            return "---\n".rtrim($match[1])."\n".$sourceYaml."\n---\n".$match[2];
        }

        $frontmatter['sources'] = $sources;

        return "---\n".Yaml::dump($frontmatter, 4, 2)."---\n".$match[2];
    }

    private function syncAuthors(string $repoPath, array $handles): int
    {
        $userIds = [];
        foreach ($handles as $handle) {
            $user = $this->resolveAlias('pgmfi', $handle);
            if (! isset($userIds[$user->id])) {
                $userIds[$user->id] = count($userIds);
            }
        }

        foreach ($userIds as $userId => $sortOrder) {
            $credit = ArticleAuthor::firstOrNew(['repo_path' => $repoPath, 'user_id' => $userId]);
            $credit->is_original = true;
            $credit->sort_order = $sortOrder;
            $credit->save();
        }

        $staleCredits = ArticleAuthor::where('repo_path', $repoPath)
            ->where('is_original', true)
            ->whereNotIn('user_id', array_keys($userIds))
            ->whereHas('user.authorAliases', fn ($query) => $query->where('source', 'pgmfi'))
            ->get();
        foreach ($staleCredits as $credit) {
            $credit->is_original = false;
            $credit->is_contributor ? $credit->save() : $credit->delete();
        }

        return count($userIds);
    }

    private function resolveAlias(string $source, string $handle): User
    {
        $aliasKey = $source.':'.mb_strtolower($handle, 'UTF-8');
        $alias = AuthorAlias::with('user')->where('alias_key', $aliasKey)->first();
        if ($alias?->user) {
            return $alias->user;
        }

        $user = User::create([
            'name' => $handle,
            'is_legacy_author' => true,
            'legacy_source' => $source,
            'legacy_handle' => $handle,
            'legacy_key' => $aliasKey,
        ]);
        AuthorAlias::create([
            'user_id' => $user->id,
            'source' => $source,
            'handle' => $handle,
            'alias_key' => $aliasKey,
        ]);

        return $user;
    }

    private function authorsAreCurrent(string $repoPath, array $handles): bool
    {
        $desired = [];
        foreach ($handles as $handle) {
            $key = 'pgmfi:'.mb_strtolower($handle, 'UTF-8');
            $alias = AuthorAlias::where('alias_key', $key)->first();
            if ($alias === null) {
                return false;
            }
            $desired[$alias->user_id] = true;
        }

        $actual = ArticleAuthor::where('repo_path', $repoPath)
            ->where('is_original', true)
            ->pluck('user_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        $desiredIds = array_keys($desired);
        $actualIds = array_keys($actual);
        sort($desiredIds);
        sort($actualIds);

        return $desiredIds === $actualIds;
    }

    private function kebab(string $name): string
    {
        $name = preg_replace('/(?<=[a-z])(?=[A-Z])|(?<=[A-Z0-9])(?=[A-Z][a-z])/', '-', $name);
        $name = preg_replace('/[^A-Za-z0-9]+/', '-', $name);

        return strtolower(trim(preg_replace('/-+/', '-', $name), '-'));
    }

    private function spacedTitle(string $name): string
    {
        $name = preg_replace('/(?<=[a-z])(?=[A-Z])|(?<=[A-Z0-9])(?=[A-Z][a-z])/', ' ', $name);

        return trim(preg_replace('/[_\s]+/', ' ', $name));
    }
}
