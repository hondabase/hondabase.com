<?php

namespace App\Support;

use Symfony\Component\Yaml\Yaml;

/**
 * Splits an article file into its YAML frontmatter and Markdown body and reassembles them.
 *
 * The TipTap editor edits the body as Markdown and the frontmatter via structured fields, so a
 * raw article file has to be taken apart on load and put back together on save. compose() emits
 * the frontmatter in a canonical, stable shape (house style: scalars and inline leaf lists, block
 * maps and block lists-of-maps) so that re-saving an already-saved article is byte-identical - the
 * same idempotency the TipTap body round-trip relies on. Any frontmatter key the editor does not
 * model is preserved verbatim, so editing an article never silently drops its metadata.
 */
class ArticleDocument
{
    /** Canonical key order the editor emits; keys it does not model follow, in their original order. */
    private const ORDER = ['title', 'summary', 'tags', 'applies_to', 'complexity', 'sources'];

    /**
     * Split a raw article file into ['fm' => array, 'body' => string]. fm is [] when the file has
     * no (or unparseable) frontmatter, in which case body is the whole file.
     */
    public static function parse(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        if (! preg_match('/^---\s*?\n(.*?)\n---\s*?\n(.*)$/s', $raw, $m)) {
            return ['fm' => [], 'body' => $raw];
        }
        try {
            $fm = Yaml::parse($m[1]);
        } catch (\Throwable $e) {
            return ['fm' => [], 'body' => $raw];
        }

        return ['fm' => is_array($fm) ? $fm : [], 'body' => $m[2]];
    }

    /**
     * Reassemble a frontmatter array + body into a raw article file. Frontmatter that is empty
     * (or pruned to empty) emits no fence at all, matching the articles that legitimately carry
     * no frontmatter. The body is left-trimmed of blank lines and given a single trailing newline.
     */
    public static function compose(array $fm, string $body): string
    {
        $fm = self::prune($fm);
        $body = rtrim(ltrim(str_replace("\r\n", "\n", $body), "\n")) . "\n";

        if ($fm === []) {
            return $body;
        }

        return "---\n" . self::dumpFrontmatter($fm) . "---\n\n" . $body;
    }

    /** Emit the frontmatter key-by-key so leaf lists stay inline while maps stay block. */
    private static function dumpFrontmatter(array $fm): string
    {
        $ordered = [];
        foreach (self::ORDER as $k) {
            if (array_key_exists($k, $fm)) {
                $ordered[$k] = $fm[$k];
            }
        }
        foreach ($fm as $k => $v) {
            if (! array_key_exists($k, $ordered)) {
                $ordered[$k] = $v;
            }
        }

        $out = '';
        foreach ($ordered as $k => $v) {
            $out .= self::dumpKey($k, $v);
        }

        return $out;
    }

    /**
     * Dump a single top-level key with the inline depth its shape wants. Symfony's $inline level
     * is the depth at which it switches to flow style, so: a flat list flows at depth 1
     * (`tags: [a, b]`); applies_to keeps its map block but flows the leaf lists at depth 2; sources
     * stays a block list of block maps. Scalars are unaffected by the level.
     */
    private static function dumpKey(string $key, mixed $value): string
    {
        if (is_array($value) && $value !== []) {
            if ($key === 'applies_to') {
                return Yaml::dump([$key => $value], 2, 2);
            }
            if ($key === 'sources') {
                return Yaml::dump([$key => $value], 4, 2);
            }
            if (array_is_list($value)) {
                return Yaml::dump([$key => $value], 1, 2);
            }

            return Yaml::dump([$key => $value], 3, 2);
        }

        return Yaml::dump([$key => $value], 2, 2);
    }

    /** Drop null / empty-string / empty-array values so the emitted frontmatter has no dead keys. */
    private static function prune(array $fm): array
    {
        $out = [];
        foreach ($fm as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
