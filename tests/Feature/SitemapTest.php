<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\TaxonomyNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private string $contentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentPath = storage_path('framework/testing-sitemap');
        File::deleteDirectory($this->contentPath);
        File::ensureDirectoryExists($this->contentPath.'/cars/electronics/test-article');
        File::put(
            $this->contentPath.'/cars/electronics/test-article/test-article.md',
            "# Test article\n\nBody.\n"
        );
        config(['hondabase.content_path' => $this->contentPath]);

        TaxonomyNode::create(['type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda', 'path' => 'cars/honda']);

        Article::create(['type' => 'cars', 'category' => 'electronics', 'slug' => 'test-article', 'locale' => 'en', 'title' => 'Test article', 'body_text' => '', 'repo_path' => 'cars/electronics/test-article/test-article.md', 'is_hidden' => false]);
        Article::create(['type' => 'cars', 'category' => 'electronics', 'slug' => 'test-article', 'locale' => 'pt', 'title' => 'Artigo de teste', 'body_text' => '', 'repo_path' => 'pt/cars/electronics/test-article/test-article.md', 'is_hidden' => false]);
        Article::create(['type' => 'cars', 'category' => 'electronics', 'slug' => 'hidden-article', 'locale' => 'en', 'title' => 'Hidden', 'body_text' => '', 'repo_path' => 'cars/electronics/hidden-article/hidden-article.md', 'is_hidden' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentPath);

        parent::tearDown();
    }

    public function test_sitemap_lists_all_page_classes_in_both_locales(): void
    {
        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');

        $xml = $response->getContent();
        $base = rtrim(config('app.url'), '/');

        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap is not valid XML');

        // Home, search, type index, category, taxonomy node, article: en + pt.
        $this->assertStringContainsString('<loc>'.$base.'/</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$base.'/pt</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$base.'/explore</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$base.'/cars</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$base.'/cars/electronics</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$base.'/cars/honda</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$base.'/pt/cars/honda</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$base.'/cars/electronics/test-article</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$base.'/pt/cars/electronics/test-article</loc>', $xml);

        // hreflang alternates pair the locale variants.
        $this->assertStringContainsString('hreflang="pt-PT"', $xml);
        $this->assertStringContainsString('hreflang="x-default"', $xml);

        // Hidden articles stay out.
        $this->assertStringNotContainsString('hidden-article', $xml);
    }
}
