<?php

namespace App\Console\Commands;

use App\Models\TaxonomyNode;
use App\Services\TaxonomySync;
use Illuminate\Console\Command;

/**
 * Bootstraps the product taxonomy + subjects from the shipped seed files (database/data/*.json)
 * into the DB. The DB is the live source of truth thereafter (edited via the control panel), so
 * this refuses to overwrite an existing taxonomy unless --force is given.
 */
class SeedTaxonomy extends Command
{
    protected $signature = 'hondabase:taxonomy:seed {--force : Overwrite the existing taxonomy (clobbers control-panel edits)}';

    protected $description = 'Seed the product taxonomy + subjects from database/data into the DB (one-time bootstrap)';

    public function handle(TaxonomySync $sync): int
    {
        if (TaxonomyNode::exists() && ! $this->option('force')) {
            $this->warn('Taxonomy already seeded ('.TaxonomyNode::count().' nodes). Use --force to overwrite.');

            return self::SUCCESS;
        }

        $counts = $sync->import(database_path('data/taxonomy.json'), database_path('data/subjects.json'));
        $this->info("Seeded {$counts['nodes']} taxonomy nodes, {$counts['subjects']} subjects.");

        return self::SUCCESS;
    }
}
