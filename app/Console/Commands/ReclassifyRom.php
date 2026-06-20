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
