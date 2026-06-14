<?php

// Headless round-trip harness for App\Support\ArticleDocument: validates that splitting an
// article into frontmatter + body and recomposing it is idempotent (re-saving a saved file is
// byte-identical) and preserves every frontmatter key/value. Mirrors scripts/tiptap-roundtrip.mjs
// for the body. Usage: php scripts/frontmatter-roundtrip.php [--show <slug>]

require __DIR__.'/../vendor/autoload.php';

use App\Support\ArticleDocument;
use Symfony\Component\Yaml\Yaml;

const ROOT = __DIR__.'/../content';

function walk(string $dir): array
{
    $out = [];
    foreach (scandir($dir) as $e) {
        if ($e === '.' || $e === '..' || $e[0] === '_') {
            continue;
        }
        $p = "$dir/$e";
        if (is_dir($p)) {
            $out = array_merge($out, walk($p));
        } elseif (str_ends_with($e, '.md')) {
            $out[] = $p;
        }
    }

    return $out;
}

$files = walk(ROOT);

$showArg = array_search('--show', $argv, true);
if ($showArg !== false) {
    $slug = $argv[$showArg + 1] ?? '';
    $file = null;
    foreach ($files as $f) {
        if (basename($f) === "$slug.md" || str_contains($f, "/$slug/")) {
            $file = $f;
            break;
        }
    }
    $raw = file_get_contents($file);
    $doc = ArticleDocument::parse($raw);
    $once = ArticleDocument::compose($doc['fm'], $doc['body']);
    echo "=== ORIGINAL ===\n".substr($raw, 0, 1200)."\n";
    echo "=== AFTER 1 COMPOSE ===\n".substr($once, 0, 1200)."\n";
    exit(0);
}

$tot = 0;
$idem = 0;
$identical = 0;
$dataKept = 0;
$drift = [];

foreach ($files as $f) {
    $raw = file_get_contents($f);
    if (trim($raw) === '') {
        continue;
    }
    $tot++;

    $d1 = ArticleDocument::parse($raw);
    $c1 = ArticleDocument::compose($d1['fm'], $d1['body']);
    $d2 = ArticleDocument::parse($c1);
    $c2 = ArticleDocument::compose($d2['fm'], $d2['body']);

    if ($c1 === $c2) {
        $idem++;
    } else {
        $drift[] = basename($f).'  (not idempotent)';
    }

    if (rtrim($c1) === rtrim($raw)) {
        $identical++;
    }

    // Semantic preservation: the frontmatter keys/values survive a parse->compose->parse cycle
    // (key ORDER is intentionally canonicalised, so compare order-insensitively).
    $a = $d1['fm'];
    $b = $d2['fm'];
    deepKsort($a);
    deepKsort($b);
    if (Yaml::dump($a, 6) === Yaml::dump($b, 6) && trim($d1['body']) === trim($d2['body'])) {
        $dataKept++;
    } else {
        $drift[] = basename($f).'  (data changed)';
    }
}

function deepKsort(array &$a): void
{
    ksort($a);
    foreach ($a as &$v) {
        if (is_array($v)) {
            deepKsort($v);
        }
    }
}

printf("Total: %d\n", $tot);
printf("Idempotent (compose#2 == compose#1):       %d (%.1f%%)\n", $idem, $idem / $tot * 100);
printf("Already byte-identical (no first-save churn): %d (%.1f%%)\n", $identical, $identical / $tot * 100);
printf("Frontmatter data preserved across cycle:    %d (%.1f%%)\n", $dataKept, $dataKept / $tot * 100);
if ($drift) {
    echo "\nDrift (".count($drift)."):\n  ".implode("\n  ", array_slice($drift, 0, 25))."\n";
}
