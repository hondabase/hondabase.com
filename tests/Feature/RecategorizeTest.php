<?php

namespace Tests\Feature;

use App\Services\ArticleService;
use App\Services\Recategorizer;
use App\Services\TaxonomySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * P4: the re-categorizer classifies the flat corpus into subjects (tag-driven) or, when an article
 * maps to a single generation, the vehicle tree; execute() moves bundles in both locale trees and
 * rewrites absolute body links.
 */
class RecategorizeTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing-recat-'.uniqid());
        File::ensureDirectoryExists($this->root.'/_data');
        File::put($this->root.'/_data/taxonomy.json', json_encode([
            'cars' => ['honda' => ['kind' => 'make', 'name' => 'Honda', 'children' => [
                'civic' => ['kind' => 'model', 'name' => 'Civic', 'children' => [
                    'eg' => ['kind' => 'generation', 'name' => 'EG', 'meta' => ['chassis_codes' => ['eg']]],
                ]],
            ]]],
        ]));

        $this->seedFile('cars/electronics/tps-sensor/tps-sensor.md', "---\ntags: [sensors]\n---\n# TPS Sensor\n\nText.\n");
        $this->seedFile('cars/electronics/ecu-pinout/ecu-pinout.md', "---\ntags: [ecu]\n---\n# ECU Pinout\n\nSee [TPS](/cars/electronics/tps-sensor).\n");
        $this->seedFile('cars/electronics/eg-guide/eg-guide.md', "---\ntags: [engine]\napplies_to:\n  chassis: [eg]\n---\n# EG Guide\n\nText.\n");
        $this->seedFile('cars/electronics/overview/overview.md', "# Overview\n\nNo tags here.\n");
        // pt mirror of one article - must move alongside its English bundle
        $this->seedFile('pt/cars/electronics/tps-sensor/tps-sensor.md', "# Sensor TPS\n\nTexto.\n");

        config(['hondabase.content_path' => $this->root]);
        $this->app->forgetInstance(ArticleService::class);
        $this->app->make(TaxonomySync::class)->import($this->root.'/_data/taxonomy.json', $this->root.'/_data/subjects.json');

        foreach ([['git', 'init', '-q'], ['git', '-c', 'user.name=t', '-c', 'user.email=t@t', 'add', '-A'], ['git', '-c', 'user.name=t', '-c', 'user.email=t@t', 'commit', '-q', '-m', 's']] as $cmd) {
            Process::path($this->root)->run($cmd);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function seedFile(string $rel, string $contents): void
    {
        File::ensureDirectoryExists(dirname($this->root.'/'.$rel));
        File::put($this->root.'/'.$rel, $contents);
    }

    public function test_plan_classifies_by_tag_and_generation(): void
    {
        $plan = $this->app->make(Recategorizer::class)->plan();
        $to = collect($plan['moves'])->keyBy('slug')->map(fn ($m) => $m['to']);

        $this->assertSame('sensors', $to['tps-sensor']);
        $this->assertSame('ecu', $to['ecu-pinout']);
        $this->assertSame('honda/civic/eg/engine', $to['eg-guide']); // single chassis -> generation tree
        $this->assertContains('cars/electronics/overview', $plan['review']); // no tags -> flagged
        $this->assertSame(1, $plan['generationMoves']);
    }

    public function test_execute_moves_both_locales_and_rewrites_links(): void
    {
        $recat = $this->app->make(Recategorizer::class);
        $result = $recat->execute($recat->plan()['moves']);

        // en + pt bundles moved out of electronics
        $this->assertFileExists($this->root.'/cars/sensors/tps-sensor/tps-sensor.md');
        $this->assertFileExists($this->root.'/pt/cars/sensors/tps-sensor/tps-sensor.md');
        $this->assertDirectoryDoesNotExist($this->root.'/cars/electronics/tps-sensor');

        // absolute link rewritten to the new category
        $this->assertStringContainsString('/cars/sensors/tps-sensor', File::get($this->root.'/cars/ecu/ecu-pinout/ecu-pinout.md'));
        $this->assertGreaterThanOrEqual(1, $result['rewritten']);
    }

    public function test_prune_deletes_named_slugs(): void
    {
        $recat = $this->app->make(Recategorizer::class);
        $recat->execute([], ['overview']);

        $this->assertDirectoryDoesNotExist($this->root.'/cars/electronics/overview');
    }
}
