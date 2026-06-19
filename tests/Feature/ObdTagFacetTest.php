<?php

namespace Tests\Feature;

use App\Livewire\Explorer;
use App\Models\Article;
use App\Models\ArticleFacet;
use App\Models\User;
use App\Services\ArticleIndexer;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class ObdTagFacetTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing-obd-tags-'.uniqid());
        File::deleteDirectory($this->root);
        File::ensureDirectoryExists($this->root);

        config([
            'hondabase.content_path' => $this->root,
            'hondabase.types' => ['cars'],
        ]);
        $this->app->forgetInstance(ArticleService::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_obd_applies_to_is_ignored_but_obd_tag_indexes_as_tag(): void
    {
        $this->seedFile('cars/ecu/obd1-topic/obd1-topic.md', <<<'MD'
---
tags: [ecu, obd1]
applies_to:
  obd: [1]
  ecus: [P28]
---
# OBD1 Topic

Notes.
MD);

        $this->app->make(ArticleIndexer::class)->indexAll();

        $article = Article::where('slug', 'obd1-topic')->firstOrFail();
        $facets = $article->facets->map(fn ($f) => $f->kind.':'.$f->value)->all();

        $this->assertContains('tag:obd1', $facets);
        $this->assertContains('ecu:p28', $facets);
        $this->assertNotContains('obd:1', $facets);
    }

    public function test_old_obd_filter_url_maps_to_obd_tag_filter(): void
    {
        $article = Article::create([
            'type' => 'cars',
            'category' => 'ecu',
            'slug' => 'obd1-topic',
            'locale' => 'en',
            'title' => 'OBD1 Topic',
            'repo_path' => 'cars/ecu/obd1-topic/obd1-topic.md',
        ]);
        ArticleFacet::create(['article_id' => $article->id, 'kind' => 'tag', 'value' => 'obd1', 'label' => 'obd1']);

        Livewire::test(Explorer::class)
            ->set('filters', ['obd:1'])
            ->assertSet('filters', ['tag:obd1'])
            ->assertSee('OBD1 Topic');
    }

    public function test_linter_rejects_obd_under_applies_to(): void
    {
        $this->seedFile('cars/ecu/stale/stale.md', <<<'MD'
---
tags: [obd1]
applies_to:
  obd: [1]
---
# Stale

Notes.
MD);

        $this->assertSame(1, Artisan::call('app:lint-articles'));
        $this->assertStringContainsString("Disallowed key under 'applies_to': 'obd'", Artisan::output());
    }

    public function test_obd_follows_migrate_to_tag_follows_and_merge_duplicates(): void
    {
        $user = User::factory()->create();
        DB::table('follows')->insert([
            ['user_id' => $user->id, 'kind' => 'obd', 'value' => '1', 'label' => 'OBD1', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'kind' => 'tag', 'value' => 'obd1', 'label' => 'obd1', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'kind' => 'obd', 'value' => '2a', 'label' => 'OBD2a', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration = include database_path('migrations/2026_06_19_000000_migrate_obd_follows_to_tags.php');
        $migration->up();

        $this->assertFalse($user->follows()->where('kind', 'obd')->exists());
        $this->assertSame(1, $user->follows()->where('kind', 'tag')->where('value', 'obd1')->count());
        $this->assertTrue($user->follows()->where('kind', 'tag')->where('value', 'obd2a')->exists());
    }

    private function seedFile(string $relative, string $contents): void
    {
        $path = $this->root.'/'.$relative;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }
}
