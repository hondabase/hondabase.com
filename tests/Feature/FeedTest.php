<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_is_valid_atom_and_excludes_hidden_and_translated_articles(): void
    {
        Article::create(['type' => 'cars', 'category' => 'electronics', 'slug' => 'visible', 'locale' => 'en', 'title' => 'Visible article', 'summary' => 'A visible article.', 'body_text' => '', 'repo_path' => 'cars/electronics/visible/visible.md']);
        Article::create(['type' => 'cars', 'category' => 'electronics', 'slug' => 'hidden', 'locale' => 'en', 'title' => 'Hidden article', 'body_text' => '', 'repo_path' => 'cars/electronics/hidden/hidden.md', 'is_hidden' => true]);
        Article::create(['type' => 'cars', 'category' => 'electronics', 'slug' => 'visible', 'locale' => 'pt', 'title' => 'Artigo visivel', 'body_text' => '', 'repo_path' => 'pt/cars/electronics/visible/visible.md']);

        $response = $this->get('/feed.xml')->assertOk();
        $this->assertStringStartsWith('application/atom+xml', $response->headers->get('Content-Type'));

        $xml = $response->getContent();
        $this->assertNotFalse(simplexml_load_string($xml), 'feed is not valid XML');
        $this->assertStringContainsString('<title>Visible article</title>', $xml);
        $this->assertStringContainsString('/cars/electronics/visible', $xml);
        $this->assertStringNotContainsString('Hidden article', $xml);
        $this->assertStringNotContainsString('/pt/cars', $xml);
    }

    public function test_feed_caps_at_fifty_entries(): void
    {
        for ($i = 1; $i <= 55; $i++) {
            Article::create(['type' => 'cars', 'category' => 'electronics', 'slug' => "article-{$i}", 'locale' => 'en', 'title' => "Article {$i}", 'body_text' => '', 'repo_path' => "cars/electronics/article-{$i}/article-{$i}.md"]);
        }

        $xml = $this->get('/feed.xml')->assertOk()->getContent();
        $this->assertSame(50, substr_count($xml, '<entry>'));
    }
}
