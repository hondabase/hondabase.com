<?php

namespace App\Livewire\Concerns;

use App\Support\ArticleDocument;

/**
 * Structured-frontmatter editing shared by the article editor + creator. The raw article file is
 * split (ArticleDocument::parse) into a Markdown body - edited in TipTap - and frontmatter, which
 * is edited here as structured fields instead of raw YAML. On save the fields are recomposed into
 * canonical YAML and rejoined to the body (ArticleDocument::compose). Any frontmatter key the
 * editor does not model is kept in $fmExtra and round-tripped untouched, so editing never silently
 * drops metadata (e.g. a hand-set `title`, or a key added in future).
 *
 * The using component must declare a public string $bodyMarkdown (the TipTap-edited body).
 */
trait EditsFrontmatter
{
    /** Article summary (frontmatter `summary`); feeds search + the "applies to" panel. */
    public string $fmSummary = '';

    /** beginner | intermediate | advanced (frontmatter `complexity`), or '' for unset. */
    public string $fmComplexity = '';

    /** Comma-separated tags (frontmatter `tags`). */
    public string $fmTags = '';

    /** applies_to as editable rows: [['key'=>'engines','value'=>'B-Series, K-Series','list'=>true], ...]. */
    public array $fmAppliesTo = [];

    /** sources as editable provenance cards; any extra sub-keys preserved under 'extra'. */
    public array $fmSources = [];

    /** Frontmatter keys the editor does not model, preserved verbatim for a lossless round-trip. */
    public array $fmExtra = [];

    /** Top-level frontmatter keys this trait owns; everything else passes through via $fmExtra. */
    private const MODELED_KEYS = ['summary', 'tags', 'complexity', 'applies_to', 'sources'];

    /** Known source sub-keys, in canonical emit order; others are preserved as extras. */
    private const SOURCE_KEYS = ['name', 'title', 'url', 'license', 'license_url'];

    /** Map a parsed frontmatter array into the structured fields. */
    protected function hydrateFrontmatter(array $fm): void
    {
        $this->fmSummary = is_scalar($fm['summary'] ?? null) ? (string) $fm['summary'] : '';
        $this->fmComplexity = is_scalar($fm['complexity'] ?? null) ? (string) $fm['complexity'] : '';
        $this->fmTags = implode(', ', $this->csvList($fm['tags'] ?? []));
        $this->fmAppliesTo = $this->appliesToRows($fm['applies_to'] ?? []);
        $this->fmSources = $this->sourceRows($fm['sources'] ?? []);
        $this->fmExtra = array_diff_key($fm, array_flip(self::MODELED_KEYS));
    }

    /** Rebuild the frontmatter array from the structured fields (with untouched extras preserved). */
    protected function composeFrontmatter(): array
    {
        $fm = $this->fmExtra;

        if (trim($this->fmSummary) !== '') {
            $fm['summary'] = trim($this->fmSummary);
        }
        if (($tags = $this->splitCsv($this->fmTags)) !== []) {
            $fm['tags'] = $tags;
        }
        if (($applies = $this->appliesToArray()) !== []) {
            $fm['applies_to'] = $applies;
        }
        if (trim($this->fmComplexity) !== '') {
            $fm['complexity'] = trim($this->fmComplexity);
        }
        if (($sources = $this->sourcesArray()) !== []) {
            $fm['sources'] = $sources;
        }

        return $fm;
    }

    /** The full raw article file (frontmatter + body) as it will be stored/committed. */
    protected function composedDocument(): string
    {
        return ArticleDocument::compose($this->composeFrontmatter(), $this->bodyMarkdown);
    }

    // ----- applies_to rows -----

    public function addAppliesTo(): void
    {
        $this->fmAppliesTo[] = ['key' => '', 'value' => '', 'list' => true];
    }

    public function removeAppliesTo(int $i): void
    {
        unset($this->fmAppliesTo[$i]);
        $this->fmAppliesTo = array_values($this->fmAppliesTo);
    }

    private function appliesToRows(mixed $at): array
    {
        if (! is_array($at)) {
            return [];
        }
        $rows = [];
        foreach ($at as $key => $v) {
            $isList = is_array($v);
            $vals = $isList ? $v : [$v];
            $rows[] = [
                'key' => (string) $key,
                'value' => implode(', ', array_map('strval', $vals)),
                'list' => $isList,
            ];
        }

        return $rows;
    }

    private function appliesToArray(): array
    {
        $out = [];
        foreach ($this->fmAppliesTo as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            $raw = trim((string) ($row['value'] ?? ''));
            if ($key === '' || $raw === '') {
                continue;
            }
            $vals = $this->splitCsv($raw);
            if ($vals === []) {
                continue;
            }
            // Keep a scalar scalar (e.g. `years: 1992-2000`) but a multi-value field a list.
            $out[$key] = (($row['list'] ?? true) || count($vals) > 1) ? $vals : $vals[0];
        }

        return $out;
    }

    // ----- sources -----

    public function addSource(): void
    {
        $this->fmSources[] = ['name' => '', 'title' => '', 'url' => '', 'license' => '', 'license_url' => '', 'adapted' => false, 'extra' => []];
    }

    public function removeSource(int $i): void
    {
        unset($this->fmSources[$i]);
        $this->fmSources = array_values($this->fmSources);
    }

    private function sourceRows(mixed $sources): array
    {
        if (! is_array($sources)) {
            return [];
        }
        $rows = [];
        foreach ($sources as $s) {
            if (! is_array($s)) {
                continue;
            }
            $row = ['extra' => array_diff_key($s, array_flip([...self::SOURCE_KEYS, 'adapted']))];
            foreach (self::SOURCE_KEYS as $k) {
                $row[$k] = is_scalar($s[$k] ?? null) ? (string) $s[$k] : '';
            }
            $row['adapted'] = (bool) ($s['adapted'] ?? false);
            $rows[] = $row;
        }

        return $rows;
    }

    private function sourcesArray(): array
    {
        $out = [];
        foreach ($this->fmSources as $row) {
            $ordered = [];
            foreach (self::SOURCE_KEYS as $k) {
                if (($v = trim((string) ($row[$k] ?? ''))) !== '') {
                    $ordered[$k] = $v;
                }
            }
            if (! empty($row['adapted'])) {
                $ordered['adapted'] = true;
            }
            foreach ((is_array($row['extra'] ?? null) ? $row['extra'] : []) as $k => $v) {
                if (! array_key_exists($k, $ordered)) {
                    $ordered[$k] = $v;
                }
            }
            if ($ordered !== []) {
                $out[] = $ordered;
            }
        }

        return $out;
    }

    // ----- helpers -----

    /** Split a comma-separated string into a clean list, casting pure-integer tokens to int. */
    private function splitCsv(string $s): array
    {
        $parts = preg_split('/\s*,\s*/', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_map(
            fn ($p) => ($p !== '' && ctype_digit($p)) ? (int) $p : $p,
            array_map('trim', $parts),
        ));
    }

    /** Normalize a frontmatter list value to a list of non-empty strings (for display joining). */
    private function csvList(mixed $v): array
    {
        if (is_array($v)) {
            return array_values(array_filter(array_map('strval', $v), fn ($s) => $s !== ''));
        }

        return is_scalar($v) && (string) $v !== '' ? [(string) $v] : [];
    }
}
