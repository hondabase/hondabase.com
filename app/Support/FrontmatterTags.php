<?php

namespace App\Support;

/**
 * Edits the inline `tags: [a, b, c]` list in a markdown file's front matter. The corpus uses the
 * inline form exclusively, so block-style lists are intentionally not handled. Only the first match
 * (the front matter line) is touched; the body is left byte-for-byte intact.
 */
class FrontmatterTags
{
    /** Remove $tag from the inline tags list. Returns true iff the file changed. */
    public static function removeTag(string $path, string $tag): bool
    {
        $body = (string) file_get_contents($path);

        $new = preg_replace_callback('/^(tags:\s*\[)([^\]]*)(\])/m', function ($m) use ($tag) {
            $items = array_values(array_filter(array_map('trim', explode(',', $m[2])), fn ($t) => $t !== ''));
            $kept = array_values(array_filter($items, fn ($t) => $t !== $tag));

            return $m[1].implode(', ', $kept).$m[3];
        }, $body, 1);

        if ($new === null || $new === $body) {
            return false;
        }

        file_put_contents($path, $new);

        return true;
    }
}
