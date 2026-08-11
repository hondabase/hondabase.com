<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OgImageTest extends TestCase
{
    use RefreshDatabase;

    private string $contentPath;

    private string $cardPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentPath = storage_path('framework/testing-og-images');
        File::deleteDirectory($this->contentPath);
        File::ensureDirectoryExists($this->contentPath.'/cars/electronics/test-og');
        File::put(
            $this->contentPath.'/cars/electronics/test-og/test-og.md',
            "# Test OG\n\nBody.\n"
        );
        config(['hondabase.content_path' => $this->contentPath]);

        Article::create(['type' => 'cars', 'category' => 'electronics', 'slug' => 'test-og', 'locale' => 'en', 'title' => 'Test OG', 'body_text' => '', 'repo_path' => 'cars/electronics/test-og/test-og.md']);

        $this->cardPath = public_path('assets/og/cars/electronics/test-og.png');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentPath);
        File::delete($this->cardPath);

        parent::tearDown();
    }

    public function test_og_image_falls_back_to_the_site_card_when_none_is_generated(): void
    {
        $this->get('/cars/electronics/test-og')
            ->assertOk()
            ->assertSee('content="'.asset('assets/og-image.png').'"', false);
    }

    public function test_og_image_uses_the_generated_per_article_card_when_present(): void
    {
        File::ensureDirectoryExists(dirname($this->cardPath));
        File::put($this->cardPath, 'png');

        $this->get('/cars/electronics/test-og')
            ->assertOk()
            ->assertSee('property="og:image" content="'.asset('assets/og/cars/electronics/test-og.png').'"', false);
    }
}
