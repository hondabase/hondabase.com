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
