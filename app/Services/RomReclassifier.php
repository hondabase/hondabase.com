<?php

namespace App\Services;

use App\Support\FrontmatterTags;
use App\Support\Locales;
use Illuminate\Support\Str;

/**
 * Re-files the legacy flat `cars/rom` corpus. `rom` is not a real subject: it is an attribute of
 * specific ECU articles that reference chip ROMs. Chip-ROM articles (rom tag + a chip signal) are
 * filed under `ecu` and keep their `rom` tag; everything else redistributes to its real subject
 * (via Recategorizer, whose `rom` rule has been removed) and has the `rom` tag stripped.
 */
class RomReclassifier
{
    /** Tags that, alongside a `rom` tag, mark an article as chip-ROM. */
    private const CHIP_TAGS = ['memory', 'eprom', 'flash'];

    /** Slug fragments that mark a chip-ROM article (alongside a `rom` tag). */
    private const CHIP_SLUG = '/(eprom|flash|chip|bin|checksum|sram|8051|27c|28c|74hc|66k|82c55|mcu|internal-rom|otp|uv-erase|hex2-bin|latch)/i';

    public function __construct(private ArticleService $articles, private Recategorizer $recat) {}

    /** Chip-ROM = has a `rom` tag AND a chip signal (tag or slug). */
    public function isChipRom(string $slug, array $tags): bool
    {
        if (! in_array('rom', $tags, true)) {
            return false;
        }
        if (array_intersect(self::CHIP_TAGS, $tags)) {
            return true;
        }

        return (bool) preg_match(self::CHIP_SLUG, $slug);
    }

    /**
     * @return array{moves: list<array{type: string, slug: string, from: string, to: string, reason: string}>, strip: list<string>, keep: list<string>, distribution: array<string,int>}
     */
    public function plan(): array
    {
        $moves = [];
        $strip = [];
        $keep = [];
        $distribution = [];

        foreach ($this->articles->scan() as $row) {
            if (($row['locale'] ?? 'en') !== 'en') {
                continue; // plan on the English identity; the pt mirror moves alongside in execute()
            }
            if (($row['category'] ?? '') !== 'rom') {
                continue; // scoped strictly to the rom corpus
            }
            $type = $row['type'];
            $slug = $row['slug'];
            $fm = is_array($row['fm'] ?? null) ? $row['fm'] : [];
            $tags = array_map(fn ($t) => Str::slug((string) $t), (array) ($fm['tags'] ?? []));

            $chip = $this->isChipRom($slug, $tags);
            if ($chip) {
                $subject = 'ecu';
                $keep[] = $slug;
            } else {
                $subject = $this->recat->subjectFor($tags);
                if (in_array('rom', $tags, true)) {
                    $strip[] = $slug;
                }
            }

            $to = ($gen = $this->recat->generationFor($type, $fm))
                ? Str::after($gen->path, $type.'/')."/{$subject}"
                : $subject;

            $distribution[$to] = ($distribution[$to] ?? 0) + 1;
            $moves[] = ['type' => $type, 'slug' => $slug, 'from' => 'rom', 'to' => $to,
                'reason' => $chip ? 'chip-rom:ecu' : "subject:{$subject}"];
        }

        ksort($distribution);

        return compact('moves', 'strip', 'keep', 'distribution');
    }

    /**
     * Strip the `rom` tag from $stripSlugs' files (en + pt, at their current cars/rom location),
     * then git-mv the bundles and rewrite body links via Recategorizer.
     *
     * @return array{moved:int, stripped:int, rewritten:int}
     */
    public function execute(array $moves, array $stripSlugs): array
    {
        $root = rtrim((string) config('hondabase.content_path'), '/');
        $bases = ['' => $root];
        foreach (Locales::others() as $loc) {
            $bases[$loc] = "{$root}/{$loc}";
        }

        $stripSet = array_flip($stripSlugs);
        $stripped = 0;
        foreach ($moves as $m) {
            if (! isset($stripSet[$m['slug']])) {
                continue;
            }
            foreach ($bases as $base) {
                $dir = "{$base}/{$m['type']}/{$m['from']}/{$m['slug']}";
                foreach (glob("{$dir}/*.md") ?: [] as $md) {
                    if (FrontmatterTags::removeTag($md, 'rom')) {
                        $stripped++;
                    }
                }
            }
        }

        $r = $this->recat->execute($moves);

        // git mv leaves the now-empty `<type>/rom` category folder behind in each tree; remove it so
        // the category is gone on disk (spec: content/cars/rom no longer exists).
        $fromDirs = array_unique(array_map(fn ($m) => "{$m['type']}/{$m['from']}", $moves));
        foreach ($fromDirs as $rel) {
            foreach ($bases as $base) {
                @rmdir("{$base}/{$rel}");
            }
        }

        return ['moved' => $r['moved'], 'stripped' => $stripped, 'rewritten' => $r['rewritten']];
    }
}
