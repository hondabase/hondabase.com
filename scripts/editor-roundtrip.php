<?php

// End-to-end editor data-path harness. Exercises the exact path a live edit takes through the
// structured-frontmatter trait: file -> ArticleDocument::parse -> EditsFrontmatter::hydrateFrontmatter
// (split into fmSummary/fmTags/fmAppliesTo/fmSources/fmExtra) -> composeFrontmatter ->
// ArticleDocument::compose. Verifies that path is idempotent and preserves every frontmatter
// key/value, so opening an article in the editor and saving it back never corrupts or drops data.

require __DIR__ . '/../vendor/autoload.php';

use App\Support\ArticleDocument;
use Symfony\Component\Yaml\Yaml;

// Anonymous component standing in for ArticleEditor/ArticleCreator: same trait, same data flow.
function makeEditor(): object
{
    return new class {
        use \App\Livewire\Concerns\EditsFrontmatter;

        public string $bodyMarkdown = '';

        public function load(array $fm, string $body): void
        {
            $this->bodyMarkdown = $body;
            $this->hydrateFrontmatter($fm);
        }

        public function document(): string
        {
            return $this->composedDocument();
        }

        public function frontmatter(): array
        {
            return $this->composeFrontmatter();
        }
    };
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

$files = walk(__DIR__ . '/../content');
$tot = 0;
$idem = 0;
$dataKept = 0;
$drift = [];

foreach ($files as $f) {
    $raw = file_get_contents($f);
    if (trim($raw) === '') {
        continue;
    }
    $tot++;

    // First pass through the editor: parse the file, load it into the structured fields, recompose.
    $doc0 = ArticleDocument::parse($raw);
    $ed1 = makeEditor();
    $ed1->load($doc0['fm'], $doc0['body']);
    $out1 = $ed1->document();

    // Second pass: re-open the just-saved file in a fresh editor and save again.
    $doc1 = ArticleDocument::parse($out1);
    $ed2 = makeEditor();
    $ed2->load($doc1['fm'], $doc1['body']);
    $out2 = $ed2->document();

    if ($out1 === $out2) {
        $idem++;
    } else {
        $drift[] = basename($f) . '  (editor not idempotent)';
    }

    // Frontmatter keys/values preserved from the original file through the structured fields.
    $orig = ArticleDocument::parse($raw)['fm'];
    $after = $ed1->frontmatter();
    deepKsort($orig);
    deepKsort($after);
    if (Yaml::dump($orig, 8) === Yaml::dump($after, 8)) {
        $dataKept++;
    } else {
        $drift[] = basename($f) . '  (frontmatter changed)';
    }
}

printf("Total: %d\n", $tot);
printf("Editor round-trip idempotent:            %d (%.1f%%)\n", $idem, $idem / $tot * 100);
printf("Frontmatter preserved through the fields: %d (%.1f%%)\n", $dataKept, $dataKept / $tot * 100);
if ($drift) {
    echo "\nDrift (" . count($drift) . "):\n  " . implode("\n  ", array_slice($drift, 0, 25)) . "\n";
}
