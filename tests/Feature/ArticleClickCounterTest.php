<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleLinkClick;
use App\Services\ArticleIndexer;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArticleClickCounterTest extends TestCase
{
    use RefreshDatabase;

    private string $contentPath;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->contentPath = storage_path('framework/testing-article-clicks');
        File::deleteDirectory($this->contentPath);
        File::ensureDirectoryExists($this->contentPath.'/cars/electronics/click-test');
        File::put(
            $this->contentPath.'/cars/electronics/click-test/click-test.md',
            <<<'MD'
---
summary: Click counter fixture.
sources:
  - name: Source Site
    url: https://source.example/manual
---
# Click Test

[Repeated link](https://example.com/repeated)

[Repeated link again](https://example.com/repeated)

[Internal link](/cars/electronics/other-article)

## Heading With Anchor
MD
        );

        config([
            'hondabase.content_path' => $this->contentPath,
            'hondabase.types' => ['cars'],
        ]);
        $this->app->forgetInstance(ArticleService::class);
        $this->app->make(ArticleIndexer::class)->indexAll();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentPath);
        parent::tearDown();
    }

    public function test_article_view_is_counted_and_displayed(): void
    {
        $this->get('/cars/electronics/click-test')
            ->assertOk()
            ->assertSee('class="article-view-count"', false)
            ->assertSee('aria-label="1 view"', false);

        $this->assertDatabaseHas('articles', [
            'type' => 'cars',
            'category' => 'electronics',
            'slug' => 'click-test',
            'locale' => 'en',
            'view_count' => 1,
        ]);
    }

    public function test_bot_and_duplicate_article_views_are_suppressed(): void
    {
        $this->withHeader('User-Agent', 'Googlebot')->get('/cars/electronics/click-test')->assertOk();
        $this->assertSame(0, Article::firstWhere('slug', 'click-test')->view_count);

        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/cars/electronics/click-test')->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/cars/electronics/click-test')->assertOk();

        $this->assertSame(1, Article::firstWhere('slug', 'click-test')->fresh()->view_count);
    }

    public function test_duplicate_urls_get_distinct_prose_link_counters(): void
    {
        $response = $this->get('/cars/electronics/click-test')->assertOk();

        $rows = ArticleLinkClick::where('slug', 'click-test')->orderBy('ordinal')->get();
        $this->assertCount(3, $rows);
        $this->assertSame('https://example.com/repeated', $rows[0]->url);
        $this->assertSame('https://example.com/repeated', $rows[1]->url);
        $this->assertNotSame($rows[0]->occurrence_key, $rows[1]->occurrence_key);
        $this->assertSame('/cars/electronics/other-article', $rows[2]->url);

        $response->assertSee('data-article-link-counter="'.$rows[0]->id.'"', false)
            ->assertSee('data-article-link-counter="'.$rows[1]->id.'"', false)
            ->assertSee('class="article-click-count"', false)
            ->assertDontSee('data-article-link-counter="'.$rows->firstWhere('url', 'https://source.example/manual')?->id.'"', false);

        $this->assertDatabaseMissing('article_link_clicks', [
            'url' => 'https://source.example/manual',
        ]);
    }

    public function test_click_endpoint_increments_only_one_link_occurrence(): void
    {
        $this->get('/cars/electronics/click-test')->assertOk();
        $rows = ArticleLinkClick::where('slug', 'click-test')->orderBy('ordinal')->get();

        $this->post(route('article-link-clicks.store', $rows[1]))->assertNoContent();

        $this->assertSame(0, $rows[0]->fresh()->click_count);
        $this->assertSame(1, $rows[1]->fresh()->click_count);
        $this->assertNotNull($rows[1]->fresh()->last_clicked_at);
    }

    public function test_reindex_preserves_article_views_and_link_counters(): void
    {
        $this->get('/cars/electronics/click-test')->assertOk();
        $counter = ArticleLinkClick::where('slug', 'click-test')->orderBy('ordinal')->first();
        $this->post(route('article-link-clicks.store', $counter))->assertNoContent();

        $this->app->make(ArticleIndexer::class)->indexAll();

        $this->assertSame(1, Article::firstWhere('slug', 'click-test')->view_count);
        $this->assertSame(3, ArticleLinkClick::where('slug', 'click-test')->count());
        $this->assertSame(1, $counter->fresh()->click_count);
    }
}
