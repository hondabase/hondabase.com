<?php

namespace App\Console\Commands;

use App\Markdown\MarkdownNormalizer;
use App\Support\Locales;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hondabase:audit-presentation {--locale=* : Locale codes to scan; defaults to every configured locale} {--limit=50 : Maximum findings to print}')]
#[Description('Reports and prioritizes article presentation debt without failing validation')]
class AuditArticlePresentation extends Command
{
    public function handle(MarkdownNormalizer $normalizer): int
    {
        $requested = array_values(array_filter(array_map('strval', (array) $this->option('locale'))));
        $locales = $requested ?: Locales::codes();
        foreach ($locales as $locale) {
            if (! Locales::isSupported($locale)) {
                $this->error("Unsupported locale: {$locale}");

                return self::FAILURE;
            }
        }

        $root = rtrim((string) config('hondabase.content_path'), '/');
        $findings = [];
        foreach ($locales as $locale) {
            $localeRoot = Locales::isDefault($locale) ? $root : "{$root}/{$locale}";
            foreach ((array) config('hondabase.types', []) as $type) {
                foreach ((array) glob("{$localeRoot}/{$type}/*/*/*.md") as $path) {
                    $raw = (string) file_get_contents($path);
                    $finding = $this->inspect($raw, $normalizer);
                    if ($finding['score'] === 0) {
                        continue;
                    }
                    $relative = ltrim(str_replace($root, '', $path), '/');
                    $findings[] = $finding + ['locale' => $locale, 'path' => $relative];
                }
            }
        }

        usort($findings, fn (array $a, array $b) => [$b['score'], $a['path']] <=> [$a['score'], $b['path']]);
        $limit = max(1, (int) $this->option('limit'));

        $this->table(
            ['Score', 'Locale', 'Article', 'Signals'],
            array_map(fn (array $finding) => [
                $finding['score'],
                $finding['locale'],
                $finding['path'],
                implode(', ', $finding['signals']),
            ], array_slice($findings, 0, $limit)),
        );
        $this->info('Found '.count($findings).' article'.(count($findings) === 1 ? '' : 's').' with presentation debt.');
        if (count($findings) > $limit) {
            $this->line('Showing the highest-scoring '.$limit.'.');
        }

        return self::SUCCESS;
    }

    private function inspect(string $raw, MarkdownNormalizer $normalizer): array
    {
        $body = preg_replace('/\A---\R.*?\R---\R/s', '', $raw) ?? $raw;
        $signals = [];
        $score = 0;

        if ($normalizer->normalize($body) !== $body) {
            $signals[] = 'collapsed table';
            $score += 5;
        }
        if (preg_match('/[^\r\n][ \t]+```(?:[A-Za-z0-9_-]+)?[ \t]*$/m', $body)) {
            $signals[] = 'malformed fence';
            $score += 5;
        }
        if (preg_match('/\*\*Attachment:\*\*.*\*\*Modify:\*\*/s', $body)) {
            $signals[] = 'legacy attachment table';
            $score += 4;
        }
        if (preg_match('/<(?:a|div|span|table|tr|td|th|br|img)\b/i', $body)) {
            $signals[] = 'raw HTML';
            $score += 4;
        }
        if (preg_match('/[^\r\n](?:#{2,6}[ \t]+|!\[[^\]\r\n]*\]\([^)]+\))/m', $this->withoutValidFences($body))) {
            $signals[] = 'content glued to prose';
            $score += 3;
        }

        $longLines = 0;
        foreach (preg_split('/\R/', $this->withoutValidFences($body)) ?: [] as $line) {
            if (mb_strlen($line) > 1200) {
                $longLines++;
            }
        }
        if ($longLines > 0) {
            $signals[] = "{$longLines} long line".($longLines === 1 ? '' : 's');
            $score += min(5, $longLines);
        }

        return ['score' => $score, 'signals' => $signals];
    }

    /** Remove complete fenced blocks so their structured/code payload does not count as prose debt. */
    private function withoutValidFences(string $body): string
    {
        return preg_replace('/^(`{3,})[^\r\n]*\R.*?^\1[ \t]*$/ms', '', $body) ?? $body;
    }
}
