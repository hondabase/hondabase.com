<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArticleAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private string $contentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentPath = storage_path('framework/testing-article-attachments');
        File::deleteDirectory($this->contentPath);
        File::ensureDirectoryExists($this->contentPath . '/cars/electronics/test-download');
        File::put(
            $this->contentPath . '/cars/electronics/test-download/test-download.md',
            "# Test download\n\n[Download archive](archive.zip)\n"
        );
        File::put($this->contentPath . '/cars/electronics/test-download/archive.zip', 'archive');

        config(['hondabase.content_path' => $this->contentPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentPath);

        parent::tearDown();
    }

    public function test_relative_attachment_link_uses_the_article_asset_route(): void
    {
        $this->get('/cars/electronics/test-download')
            ->assertOk()
            ->assertSee('href="/cars/electronics/test-download/archive.zip"', false);

        $this->get('/cars/electronics/test-download/archive.zip')
            ->assertOk();
    }
}
