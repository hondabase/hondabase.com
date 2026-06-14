<?php

namespace Tests\Feature;

use App\Models\ArticleAuthor;
use App\Models\AuthorAlias;
use App\Models\User;
use App\Services\ArticleAuthorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArticleAttributionTest extends TestCase
{
    use RefreshDatabase;

    private string $contentPath;
    private string $repoPath = 'cars/electronics/test-credit/test-credit.md';

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentPath = storage_path('framework/testing-article-attribution');
        File::deleteDirectory($this->contentPath);
        File::ensureDirectoryExists(dirname($this->contentPath . '/' . $this->repoPath));
        File::put($this->contentPath . '/' . $this->repoPath, <<<'MD'
---
sources:
  - name: pgmfi.org wiki
    title: Test Credit
    url: /pgmfi/wiki/library/test-credit
    license: CC BY-NC-SA 1.0
    license_url: https://creativecommons.org/licenses/by-nc-sa/1.0/
    adapted: true
---
# Test Credit

Article body.
MD);

        config(['hondabase.content_path' => $this->contentPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentPath);
        parent::tearDown();
    }

    public function test_article_shows_one_combined_human_author_list_and_pgmfi_source(): void
    {
        $legacy = User::create([
            'name' => 'LegacyWriter',
            'is_legacy_author' => true,
            'legacy_source' => 'pgmfi',
            'legacy_handle' => 'LegacyWriter',
            'legacy_key' => 'pgmfi:legacywriter',
        ]);
        $modern = User::create([
            'name' => 'Display Name',
            'discord_id' => '1234',
            'discord_username' => 'stable_username',
            'discord_global_name' => 'Display Name',
        ]);

        ArticleAuthor::create(['repo_path' => $this->repoPath, 'user_id' => $legacy->id, 'is_original' => true, 'sort_order' => 0]);
        ArticleAuthor::create(['repo_path' => $this->repoPath, 'user_id' => $modern->id, 'is_contributor' => true, 'sort_order' => 1]);

        $this->get('/cars/electronics/test-credit')
            ->assertOk()
            ->assertSeeText('LegacyWriter, Display Name (@stable_username)')
            ->assertSeeText('Adapted from pgmfi.org wiki')
            ->assertSeeText('Licensed under CC BY-NC-SA 1.0.');
    }

    public function test_legacy_author_can_be_merged_and_contributor_credit_is_idempotent(): void
    {
        $legacy = User::create([
            'name' => 'LegacyWriter',
            'is_legacy_author' => true,
            'legacy_source' => 'pgmfi',
            'legacy_handle' => 'LegacyWriter',
            'legacy_key' => 'pgmfi:legacywriter',
        ]);
        AuthorAlias::create(['user_id' => $legacy->id, 'source' => 'pgmfi', 'handle' => 'LegacyWriter', 'alias_key' => 'pgmfi:legacywriter']);
        $modern = User::create(['name' => 'Modern', 'discord_id' => '5678', 'discord_username' => 'modern']);
        ArticleAuthor::create(['repo_path' => $this->repoPath, 'user_id' => $legacy->id, 'is_original' => true, 'sort_order' => 0]);
        ArticleAuthor::create(['repo_path' => $this->repoPath, 'user_id' => $modern->id, 'is_contributor' => true, 'sort_order' => 2]);

        $service = app(ArticleAuthorService::class);
        $service->creditContributor($this->repoPath, $modern);
        $service->creditContributor($this->repoPath, $modern);
        $service->mergeLegacyAuthor($legacy, $modern);

        $credit = ArticleAuthor::where('repo_path', $this->repoPath)->sole();
        $this->assertTrue($credit->is_original);
        $this->assertTrue($credit->is_contributor);
        $this->assertSame(0, $credit->sort_order);
        $this->assertSame($modern->id, AuthorAlias::sole()->user_id);
        $this->assertDatabaseMissing('users', ['id' => $legacy->id]);
    }

    public function test_independent_wideband_guide_is_excluded_from_pgmfi_import(): void
    {
        File::deleteDirectory($this->contentPath);
        $path = $this->contentPath . '/cars/electronics/how-to-wire-wideband/how-to-wire-wideband.md';
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "---\ntitle: Independent Wideband Guide\n---\n\nArticle body.\n");

        $this->assertSame(0, Artisan::call('hondabase:import-pgmfi-authors', ['--check' => true]));
        $this->assertStringNotContainsString('pgmfi.org wiki', File::get($path));
    }
}
