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
        $this->seedFile('cars/rom/27sf256/27sf256.md', "---\ntags: [tuning, rom, ecu, memory]\n---\n# Flash\n\nText.\n");
        // generic: has rom tag, no chip signal -> tuning, rom stripped
        $this->seedFile('cars/rom/boost/boost.md', "---\ntags: [tuning, rom]\n---\n# Boost\n\nText.\n");
        // pt mirror of the generic article
        $this->seedFile('pt/cars/rom/boost/boost.md', "---\ntags: [tuning, rom]\n---\n# Boost\n\nTexto.\n");

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

    private function seedFile(string $rel, string $contents): void
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
}
