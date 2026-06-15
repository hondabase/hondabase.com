<?php

namespace App\Console\Commands;

use App\Services\ArticleIndexer;
use App\Services\Recategorizer;
use Illuminate\Console\Command;

/**
 * Plan (default) or execute the re-categorization of the flat corpus into the taxonomy. Dry-run
 * prints the target distribution + the full move list (written to storage) and changes nothing.
 * `--execute` performs the git-mv across en+pt + rewrites links; `--prune=a,b` also deletes those
 * off-topic slugs. After executing it reindexes. No redirects (old URLs 404, owner decision).
 */
class Recategorize extends Command
{
    protected $signature = 'hondabase:recategorize {--execute : Perform the moves (default is a dry run)} {--prune= : Comma-separated slugs to delete}';

    protected $description = 'Re-file the flat cars/electronics corpus into the product taxonomy (dry-run by default)';

    public function handle(Recategorizer $recat, ArticleIndexer $indexer): int
    {
        $plan = $recat->plan();
        $prune = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('prune')))));

        $this->info(sprintf('%d articles would move into %d target categories (%d generation-specific).',
            count($plan['moves']), count($plan['distribution']), $plan['generationMoves']));
        $this->newLine();
        $this->table(['Target category', 'Articles'], collect($plan['distribution'])->map(fn ($n, $c) => [$c, $n])->values());

        if ($plan['review']) {
            $this->newLine();
            $this->warn('Review candidates (no tags - confirm subject or prune): '.implode(', ', $plan['review']));
        }

        $file = storage_path('app/recategorize-plan.json');
        file_put_contents($file, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line("Full move list written to {$file}");

        if (! $this->option('execute')) {
            $this->newLine();
            $this->comment('Dry run - nothing changed. Re-run with --execute (and --prune=slug,slug) to apply.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Apply '.count($plan['moves']).' moves'.($prune ? ' and prune '.count($prune).' slugs' : '').' across en+pt trees?')) {
            return self::SUCCESS;
        }

        $r = $recat->execute($plan['moves'], $prune);
        $this->info("Moved {$r['moved']} bundles, pruned {$r['pruned']}, rewrote links in {$r['rewritten']} files.");

        $counts = $indexer->indexAll();
        $this->info("Reindexed: {$counts['articles']} articles, {$counts['compatibilities']} compatibility links.");

        return self::SUCCESS;
    }
}
