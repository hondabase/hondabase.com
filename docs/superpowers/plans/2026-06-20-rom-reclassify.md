# Reclassify `rom` from category to tag-under-ecu — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop `rom` being a content category; move curated chip-ROM articles into `cars/ecu` keeping a `rom` tag, redistribute the rest of the `cars/rom` corpus by their tags, and strip the `rom` tag from the non-chip remainder.

**Architecture:** A new `RomReclassifier` service plans and executes the move, reusing the existing `Recategorizer` for tag→subject resolution, generation-tree filing, `git mv` of en+pt bundles, and body-link rewriting. A small `FrontmatterTags` support class strips a tag from a markdown file's inline `tags:` list. A dry-run-by-default `hondabase:reclassify-rom` command wires plan → execute → reindex.

**Tech Stack:** Laravel 12, PHP 8.x, PHPUnit 12 (Feature tests use `RefreshDatabase` + a temp git content root, mirroring `tests/Feature/RecategorizeTest.php`).

## Global Constraints

- Content lives in the git repo at `config('hondabase.content_path')` (`content/`). Article bundles are folders `{type}/{category}/{slug}/` containing `{slug}.md`; the pt mirror lives under `{root}/pt/...`.
- All `cars/rom` frontmatter uses **inline** `tags: [a, b, c]` form (verified: 108/108). The strip helper only needs the inline form.
- No redirects for moved URLs (owner decision, consistent with `Recategorizer`).
- Admin/CLI output stays English. Member-facing strings are out of scope here.
- Commits are made on branch `reclassify-rom-tag`. End commit messages with `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.
- Chip-ROM signal (verbatim): article has a `rom` tag **and** (a tag in `{memory, eprom, flash}` **or** slug matches `/(eprom|flash|chip|bin|checksum|sram|8051|27c|28c|74hc|66k|82c55|mcu|internal-rom|otp|uv-erase|hex2-bin|latch)/i`).

---

### Task 1: `FrontmatterTags::removeTag` helper

**Files:**
- Create: `app/Support/FrontmatterTags.php`
- Test: `tests/Unit/FrontmatterTagsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Support\FrontmatterTags::removeTag(string $path, string $tag): bool` — removes `$tag` from the first inline `tags: [...]` list in the file; returns `true` iff the file changed.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Support\FrontmatterTags;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\TestCase;

class FrontmatterTagsTest extends TestCase
{
    private function tmp(string $contents): string
    {
        $path = sys_get_temp_dir().'/fmtags-'.uniqid().'.md';
        File::put($path, $contents);

        return $path;
    }

    public function test_removes_tag_and_preserves_others(): void
    {
        $path = $this->tmp("---\ntags: [tuning, rom, ecu, memory]\ncomplexity: beginner\n---\n# Title\n\nBody.\n");

        $this->assertTrue(FrontmatterTags::removeTag($path, 'rom'));

        $out = File::get($path);
        $this->assertStringContainsString('tags: [tuning, ecu, memory]', $out);
        $this->assertStringContainsString('complexity: beginner', $out);
        $this->assertStringContainsString('# Title', $out);
        $this->assertStringNotContainsString('rom', $out);
        File::delete($path);
    }

    public function test_returns_false_and_leaves_file_unchanged_when_tag_absent(): void
    {
        $original = "---\ntags: [tuning, ecu]\n---\n# Title\n";
        $path = $this->tmp($original);

        $this->assertFalse(FrontmatterTags::removeTag($path, 'rom'));
        $this->assertSame($original, File::get($path));
        File::delete($path);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FrontmatterTagsTest`
Expected: FAIL — `Class "App\Support\FrontmatterTags" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=FrontmatterTagsTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/FrontmatterTags.php tests/Unit/FrontmatterTagsTest.php
git commit -m "Add FrontmatterTags::removeTag helper

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Remove the `rom` rule + add `RomReclassifier::plan`

**Files:**
- Modify: `app/Services/Recategorizer.php` (delete the `'rom' => [...]` line from `SUBJECT_RULES`)
- Create: `app/Services/RomReclassifier.php`
- Test: `tests/Feature/RomReclassifyTest.php`

**Interfaces:**
- Consumes: `Recategorizer::subjectFor(array $tags): string`, `Recategorizer::generationFor(string $type, array $fm): ?TaxonomyNode`, `Recategorizer::execute(array $moves, array $pruneSlugs = []): array`, `ArticleService::scan()` (yields rows with `type, category, slug, locale, fm`).
- Produces:
  - `RomReclassifier::isChipRom(string $slug, array $tags): bool`
  - `RomReclassifier::plan(): array{moves: list<array{type,slug,from,to,reason}>, strip: list<string>, keep: list<string>, distribution: array<string,int>}`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\TaxonomyNode;
use App\Services\ArticleService;
use App\Services\RomReclassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RomReclassifyTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing-romreclass-'.uniqid());

        TaxonomyNode::create(['type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda', 'path' => 'cars/honda']);

        // chip-ROM: has rom tag + chip signal (memory tag) -> ecu, keeps rom tag
        $this->seed('cars/rom/27sf256/27sf256.md', "---\ntags: [tuning, rom, ecu, memory]\n---\n# Flash\n\nText.\n");
        // generic: has rom tag, no chip signal -> tuning, rom stripped
        $this->seed('cars/rom/boost/boost.md', "---\ntags: [tuning, rom]\n---\n# Boost\n\nText.\n");
        // pt mirror of the generic article
        $this->seed('pt/cars/rom/boost/boost.md', "---\ntags: [tuning, rom]\n---\n# Boost\n\nTexto.\n");

        config(['hondabase.content_path' => $this->root]);
        $this->app->forgetInstance(ArticleService::class);

        foreach ([['git', 'init', '-q'], ['git', '-c', 'user.name=t', '-c', 'user.email=t@t', 'add', '-A'], ['git', '-c', 'user.name=t', '-c', 'user.email=t@t', 'commit', '-q', '-m', 's']] as $cmd) {
            Process::path($this->root)->run($cmd);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function seed(string $rel, string $contents): void
    {
        File::ensureDirectoryExists(dirname($this->root.'/'.$rel));
        File::put($this->root.'/'.$rel, $contents);
    }

    public function test_plan_routes_chip_to_ecu_and_redistributes_the_rest(): void
    {
        $plan = $this->app->make(RomReclassifier::class)->plan();
        $to = collect($plan['moves'])->keyBy('slug')->map(fn ($m) => $m['to']);

        $this->assertSame('ecu', $to['27sf256']);     // chip-ROM forced to ecu
        $this->assertSame('tuning', $to['boost']);    // generic redistributes by tag
        $this->assertContains('27sf256', $plan['keep']);
        $this->assertContains('boost', $plan['strip']);
        $this->assertNotContains('27sf256', $plan['strip']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RomReclassifyTest`
Expected: FAIL — `Class "App\Services\RomReclassifier" not found`.

- [ ] **Step 3a: Delete the `rom` rule from `SUBJECT_RULES`**

In `app/Services/Recategorizer.php`, remove this line from the `SUBJECT_RULES` constant:

```php
        'rom' => ['rom', 'chipping', 'maps'],
```

Leave every other rule and its ordering unchanged.

- [ ] **Step 3b: Create `RomReclassifier` with `isChipRom` + `plan`**

```php
<?php

namespace App\Services;

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
     * @return array{moves: list<array>, strip: list<string>, keep: list<string>, distribution: array<string,int>}
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
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RomReclassifyTest`
Expected: PASS. Also run `php artisan test --filter=RecategorizeTest` and confirm it still passes (the removed `rom` rule is not exercised by that test).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Recategorizer.php app/Services/RomReclassifier.php tests/Feature/RomReclassifyTest.php
git commit -m "Add RomReclassifier::plan; drop rom subject rule

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: `RomReclassifier::execute`

**Files:**
- Modify: `app/Services/RomReclassifier.php` (add `execute`)
- Test: `tests/Feature/RomReclassifyTest.php` (add a case)

**Interfaces:**
- Consumes: `FrontmatterTags::removeTag`, `Recategorizer::execute`, `App\Support\Locales::others()`.
- Produces: `RomReclassifier::execute(array $moves, array $stripSlugs): array{moved:int, stripped:int, rewritten:int}` — strips the `rom` tag from the named slugs' en+pt files (at their pre-move `cars/rom` location), then `git mv`s the bundles and rewrites body links via `Recategorizer::execute`.

- [ ] **Step 1: Write the failing test**

Add this method to `tests/Feature/RomReclassifyTest.php`:

```php
    public function test_execute_files_chip_under_ecu_strips_rom_from_generic_and_moves_pt(): void
    {
        $recl = $this->app->make(RomReclassifier::class);
        $plan = $recl->plan();

        $result = $recl->execute($plan['moves'], $plan['strip']);

        // chip-ROM under ecu, rom tag kept
        $this->assertFileExists($this->root.'/cars/ecu/27sf256/27sf256.md');
        $this->assertStringContainsString('rom', File::get($this->root.'/cars/ecu/27sf256/27sf256.md'));

        // generic under tuning in both locales, rom tag stripped
        $this->assertFileExists($this->root.'/cars/tuning/boost/boost.md');
        $this->assertFileExists($this->root.'/pt/cars/tuning/boost/boost.md');
        $this->assertStringNotContainsString('rom', File::get($this->root.'/cars/tuning/boost/boost.md'));
        $this->assertStringNotContainsString('rom', File::get($this->root.'/pt/cars/tuning/boost/boost.md'));

        $this->assertDirectoryDoesNotExist($this->root.'/cars/rom');
        $this->assertSame(2, $result['stripped']); // boost en + pt
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_execute_files_chip_under_ecu_strips_rom_from_generic_and_moves_pt`
Expected: FAIL — `Call to undefined method App\Services\RomReclassifier::execute()`.

- [ ] **Step 3: Implement `execute`**

Add `use App\Support\FrontmatterTags;` and `use App\Support\Locales;` to the imports of `app/Services/RomReclassifier.php`, then add:

```php
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

        return ['moved' => $r['moved'], 'stripped' => $stripped, 'rewritten' => $r['rewritten']];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RomReclassifyTest`
Expected: PASS (all cases).

- [ ] **Step 5: Commit**

```bash
git add app/Services/RomReclassifier.php tests/Feature/RomReclassifyTest.php
git commit -m "Add RomReclassifier::execute (strip rom tag + move bundles)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: `hondabase:reclassify-rom` command

**Files:**
- Create: `app/Console/Commands/ReclassifyRom.php`
- Test: `tests/Feature/ReclassifyRomCommandTest.php`

**Interfaces:**
- Consumes: `RomReclassifier::plan`, `RomReclassifier::execute`, `ArticleIndexer::indexAll(): array{articles:int, compatibilities:int}`.
- Produces: artisan command `hondabase:reclassify-rom {--execute} {--prune=}` (dry-run by default; prune is reserved for off-topic slug deletion, same semantics as `hondabase:recategorize`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\TaxonomyNode;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ReclassifyRomCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing-romcmd-'.uniqid());
        TaxonomyNode::create(['type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda', 'path' => 'cars/honda']);
        File::ensureDirectoryExists($this->root.'/cars/rom/boost');
        File::put($this->root.'/cars/rom/boost/boost.md', "---\ntags: [tuning, rom]\n---\n# Boost\n");
        config(['hondabase.content_path' => $this->root]);
        $this->app->forgetInstance(ArticleService::class);
        foreach ([['git', 'init', '-q'], ['git', '-c', 'user.name=t', '-c', 'user.email=t@t', 'add', '-A'], ['git', '-c', 'user.name=t', '-c', 'user.email=t@t', 'commit', '-q', '-m', 's']] as $cmd) {
            Process::path($this->root)->run($cmd);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->artisan('hondabase:reclassify-rom')
            ->assertSuccessful();

        $this->assertDirectoryExists($this->root.'/cars/rom/boost');
    }

    public function test_execute_moves_and_reindexes(): void
    {
        $this->artisan('hondabase:reclassify-rom --execute')
            ->expectsConfirmation('Apply 1 moves and strip the rom tag from 1 articles across en+pt trees?', 'yes')
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist($this->root.'/cars/rom/boost');
        $this->assertFileExists($this->root.'/cars/tuning/boost/boost.md');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReclassifyRomCommandTest`
Expected: FAIL — command `hondabase:reclassify-rom` is not defined.

- [ ] **Step 3: Implement the command**

```php
<?php

namespace App\Console\Commands;

use App\Services\ArticleIndexer;
use App\Services\RomReclassifier;
use Illuminate\Console\Command;

/**
 * Plan (default) or execute the reclassification of the flat `cars/rom` corpus. Chip-ROM articles
 * move to `cars/ecu` keeping their `rom` tag; the rest redistribute to their real subjects with the
 * `rom` tag stripped. Dry-run prints the target distribution, the chip-ROM keep-list and the strip
 * count, and writes the plan to storage. `--execute` performs the moves + tag strips, then reindexes.
 */
class ReclassifyRom extends Command
{
    protected $signature = 'hondabase:reclassify-rom {--execute : Perform the moves (default is a dry run)}';

    protected $description = 'Re-file the flat cars/rom corpus: chip-ROM -> ecu (keep tag), rest -> real subject (strip tag)';

    public function handle(RomReclassifier $recl, ArticleIndexer $indexer): int
    {
        $plan = $recl->plan();

        $this->info(sprintf('%d rom articles would move; %d keep the rom tag (chip-ROM), %d have it stripped.',
            count($plan['moves']), count($plan['keep']), count($plan['strip'])));
        $this->newLine();
        $this->table(['Target category', 'Articles'], collect($plan['distribution'])->map(fn ($n, $c) => [$c, $n])->values());
        $this->newLine();
        $this->line('Chip-ROM (keep rom tag): '.implode(', ', $plan['keep']));

        $file = storage_path('app/reclassify-rom-plan.json');
        file_put_contents($file, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line("Full plan written to {$file}");

        if (! $this->option('execute')) {
            $this->newLine();
            $this->comment('Dry run - nothing changed. Re-run with --execute to apply.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Apply '.count($plan['moves']).' moves and strip the rom tag from '.count($plan['strip']).' articles across en+pt trees?')) {
            return self::SUCCESS;
        }

        $r = $recl->execute($plan['moves'], $plan['strip']);
        $this->info("Moved {$r['moved']} bundles, stripped rom from {$r['stripped']} files, rewrote links in {$r['rewritten']} files.");

        $counts = $indexer->indexAll();
        $this->info("Reindexed: {$counts['articles']} articles, {$counts['compatibilities']} compatibility links.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ReclassifyRomCommandTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ReclassifyRom.php tests/Feature/ReclassifyRomCommandTest.php
git commit -m "Add hondabase:reclassify-rom command

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Run against real content and verify

This task mutates the real `content/` git repo. It is destructive; do it deliberately and review the dry-run first.

- [ ] **Step 1: Full test suite green**

Run: `php artisan test`
Expected: PASS, no regressions (pay attention to `RecategorizeTest`, `ObdTagFacetTest`, `ExplorerSearchTest`).

- [ ] **Step 2: Dry-run and review**

Run: `php artisan hondabase:reclassify-rom`
Expected: ~108 moves; distribution dominated by `tuning` (~72) with `ecu` carrying the chip-ROM overrides; ~14 in the chip-ROM keep-list (`27sf256, eprom, flash, bin-file, intel8051, mcu, internal-rom, ...`). Eyeball the keep-list and the distribution. Inspect `storage/app/reclassify-rom-plan.json` if needed.

- [ ] **Step 3: Execute**

Run: `php artisan hondabase:reclassify-rom --execute` and confirm the prompt.
Expected: moves applied, rom tag stripped from the non-chip articles, reindex reports the article count.

- [ ] **Step 4: Verify the outcome (DB + content)**

Run:

```bash
php artisan tinker --execute="
echo 'category facets still named rom: '.\App\Models\ArticleFacet::where('kind','category')->where(function(\$q){\$q->where('value','rom')->orWhere('value','like','rom/%');})->count().PHP_EOL;
echo 'rom TAG facet count: '.\App\Models\ArticleFacet::where('kind','tag')->where('value','rom')->count().PHP_EOL;
\$ecu = \App\Models\Article::where('category','ecu')->where('locale','en')->whereIn('slug',['27sf256','eprom','flash'])->pluck('slug');
echo '27sf256/eprom/flash now under ecu: '.\$ecu->toJson().PHP_EOL;
"
ls content/cars/rom 2>&1 || echo 'cars/rom removed (expected)'
```

Expected: category-named-rom count is **0**; rom TAG count ≈ 14, all on `cars/ecu/*`; `content/cars/rom` no longer exists.

- [ ] **Step 5: Commit the app branch and the content repo separately**

```bash
# app repo (already committed task-by-task; nothing new unless code changed)
git -C content add -A
git -C content -c user.name="VIRUXE" -c user.email="flavioaspereira@gmail.com" commit -m "Reclassify rom: chip-ROM -> ecu (keep tag), rest -> real subject"
```

(Confirm with the owner before pushing the content repo; URLs under `/cars/rom/...` will 404 by design.)

---

## Self-Review

**Spec coverage:**
- `rom` no longer a category facet → Task 5 verification (category-named-rom = 0) after the moves of Tasks 2-4. ✓
- `rom` becomes a tag on chip-ROM articles → `isChipRom` keeps the tag (Task 2/3); verified Task 5. ✓
- Chip-ROM under `cars/ecu` → Task 2 routes chip to `ecu`; Task 3 test asserts file under `cars/ecu`. ✓
- Remainder redistributes by tags, rom stripped → Task 2 `strip` list + Task 3 `execute` strips; test asserts `tuning` + stripped. ✓
- Remove `rom` rule permanently → Task 2 Step 3a. ✓
- Frontmatter strip helper (inline form) → Task 1. ✓
- pt mirror handled → Task 3 iterates `Locales::others()`; test asserts pt move + strip. ✓
- Dry-run by default + plan JSON → Task 4. ✓
- Reindex after execute → Task 4. ✓

**Placeholder scan:** No TBD/TODO; every code step shows complete code. ✓

**Type consistency:** `plan()` returns `moves/strip/keep/distribution`; the command and `execute(moves, strip)` consume exactly those keys. `isChipRom(slug, tags)` signature matches both call sites. `FrontmatterTags::removeTag(path, tag): bool` matches its use in `execute`. ✓
