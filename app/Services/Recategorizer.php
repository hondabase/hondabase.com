<?php

namespace App\Services;

use App\Models\TaxonomyNode;
use App\Support\Locales;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Plans (and, on demand, executes) the re-categorization of the flat `cars/electronics` corpus into
 * the real taxonomy. Each article is classified from its front matter:
 *   - generation-specific (a single applies_to.chassis -> one generation node) -> filed into the
 *     vehicle tree (<make>/<model>/<gen>/<subject>), giving inherited compatibility;
 *   - otherwise multi-fit/generic -> kept subject-centric, re-filed from `electronics` into a real
 *     subject (ecu/sensors/rom/wiring/...) derived from tags.
 * Execution git-mv's bundles in BOTH the en + pt trees, rewrites absolute /cars/... body links, and
 * optionally prunes named off-topic slugs. No redirects (owner decision). DESTRUCTIVE only with execute().
 */
class Recategorizer
{
    /** Tag -> subject, first match wins. `reference` is last (it tags almost everything). */
    private const SUBJECT_RULES = [
        'wiring' => ['wiring', 'conversion', 'swap', 'pinout', 'schematic', 'harness'],
        'fueling' => ['fueling', 'injectors', 'fuel'],
        'ignition' => ['ignition'],
        'diagnostics' => ['diagnostics', 'troubleshooting', 'error-codes', 'codes'],
        'rom' => ['rom', 'chipping', 'maps'],
        'tuning' => ['tuning', 'datalogging'],
        'sensors' => ['sensors', 'sensor', 'vtec', 'knock', 'iab', 'adc'],
        'ecu' => ['ecu', 'hardware', 'microcontroller', 'serial', 'protocol', 'interface', 'pgm-fi', 'memory'],
        'engine' => ['engine', 'mechanical', 'drivetrain'],
        'reference' => ['reference', 'education', 'identification', 'history'],
    ];

    public function __construct(private ArticleService $articles) {}

    /**
     * @return array{moves: list<array>, review: list<string>, distribution: array<string,int>, generationMoves: int}
     */
    public function plan(): array
    {
        $moves = [];
        $review = [];
        $distribution = [];
        $generationMoves = 0;

        foreach ($this->articles->scan() as $row) {
            if (($row['locale'] ?? 'en') !== 'en') {
                continue; // plan on the English identity; pt mirror moves alongside in execute()
            }
            $type = $row['type'];
            $from = $row['category'];
            $slug = $row['slug'];
            $fm = is_array($row['fm'] ?? null) ? $row['fm'] : [];
            $tags = array_map(fn ($t) => Str::slug((string) $t), (array) ($fm['tags'] ?? []));

            if ($tags === []) {
                $review[] = "{$type}/{$from}/{$slug}";
            }

            $subject = $this->subjectFor($tags);
            if ($gen = $this->generationFor($type, $fm)) {
                $to = Str::after($gen->path, $type.'/')."/{$subject}";
                $reason = "generation:{$gen->path}";
                $generationMoves++;
            } else {
                $to = $subject;
                $reason = "subject:{$subject}";
            }

            $distribution[$to] = ($distribution[$to] ?? 0) + 1;

            if ($to !== $from) {
                $moves[] = ['type' => $type, 'slug' => $slug, 'from' => $from, 'to' => $to, 'reason' => $reason];
            }
        }

        ksort($distribution);

        return ['moves' => $moves, 'review' => $review, 'distribution' => $distribution, 'generationMoves' => $generationMoves];
    }

    public function subjectFor(array $tags): string
    {
        $set = array_flip($tags);
        foreach (self::SUBJECT_RULES as $subject => $keys) {
            foreach ($keys as $k) {
                if (isset($set[$k])) {
                    return $subject;
                }
            }
        }

        return 'reference';
    }

    /** A single applies_to.chassis that maps to exactly one generation node => generation-specific. */
    public function generationFor(string $type, array $fm): ?TaxonomyNode
    {
        $at = is_array($fm['applies_to'] ?? null) ? $fm['applies_to'] : [];
        $chassis = array_values(array_filter((array) ($at['chassis'] ?? [])));
        if (count($chassis) !== 1) {
            return null;
        }
        $code = strtolower((string) $chassis[0]);
        $matches = TaxonomyNode::where('type', $type)->where('kind', 'generation')->get()
            ->filter(fn (TaxonomyNode $n) => in_array($code, $n->chassisCodes(), true))->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Apply a plan: git-mv each bundle in the en + pt trees, rewrite absolute body links, prune the
     * named slugs. Returns counts. Caller guarantees approval. `$pruneSlugs` are deleted outright.
     *
     * @param  list<string>  $pruneSlugs
     * @return array{moved:int, pruned:int, rewritten:int}
     */
    public function execute(array $moves, array $pruneSlugs = []): array
    {
        $root = rtrim((string) config('hondabase.content_path'), '/');
        $locales = ['' => $root]; // en (unprefixed)
        foreach (Locales::others() as $loc) {
            $locales[$loc] = "{$root}/{$loc}";
        }

        // slug -> new category, to rewrite absolute /cars/<old>/<slug> links to their new home.
        $linkMap = [];
        foreach ($moves as $m) {
            $linkMap[$m['type'].'/'.$m['slug']] = $m['to'];
        }

        $moved = 0;
        foreach ($moves as $m) {
            foreach ($locales as $base) {
                $src = "{$base}/{$m['type']}/{$m['from']}/{$m['slug']}";
                $dst = "{$base}/{$m['type']}/{$m['to']}/{$m['slug']}";
                if (! is_dir($src)) {
                    continue; // pt mirror may not exist for this article
                }
                @mkdir(dirname($dst), 0775, true);
                Process::path($root)->run(['git', 'mv', $src, $dst]);
                $moved++;
            }
        }

        $pruned = 0;
        foreach ($pruneSlugs as $slug) {
            foreach ($locales as $base) {
                foreach (glob("{$base}/*/*/{$slug}", GLOB_ONLYDIR) ?: [] as $dir) {
                    Process::path($root)->run(['git', 'rm', '-r', '-q', $dir]);
                    $pruned++;
                }
            }
        }

        $rewritten = $this->rewriteLinks($root, $linkMap);

        return ['moved' => $moved, 'pruned' => $pruned, 'rewritten' => $rewritten];
    }

    /** Rewrite absolute `/cars/<oldcat>/<slug>` links in every .md to the article's new category. */
    private function rewriteLinks(string $root, array $linkMap): int
    {
        $count = 0;
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }
            $body = (string) file_get_contents($file->getPathname());
            $new = preg_replace_callback('~/(cars|motorcycles|aircraft|common)/[A-Za-z0-9._/-]+?/([a-z0-9][a-z0-9-]*)(?=[)\s"#]|$)~', function ($mt) use ($linkMap) {
                $key = $mt[1].'/'.$mt[2];

                return isset($linkMap[$key]) ? "/{$mt[1]}/{$linkMap[$key]}/{$mt[2]}" : $mt[0];
            }, $body);
            if ($new !== null && $new !== $body) {
                file_put_contents($file->getPathname(), $new);
                $count++;
            }
        }

        return $count;
    }
}
