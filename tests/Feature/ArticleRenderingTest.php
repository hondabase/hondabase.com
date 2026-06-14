<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArticleRenderingTest extends TestCase
{
    use RefreshDatabase;

    private string $contentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentPath = storage_path('framework/testing-article-rendering');
        File::deleteDirectory($this->contentPath);
        File::ensureDirectoryExists($this->contentPath.'/cars/electronics/render-test');
        File::put(
            $this->contentPath.'/cars/electronics/render-test/render-test.md',
            <<<'MD'
---
summary: Diagnose and repair a Honda ECU safely with correctly rendered warnings and specifications.
tags: [ecu, diagnostics]
---
# Honda ECU render test

Use this guide to diagnose the ECU.

> [!CAUTION]
> Disconnect the battery first.

| Component | Value | | --- | --- | | R1 | 10k ohm |

[https://example.com/reference?part=ecu&format=html]()

[Archived reference](http://web.archive.org/web/20200101000000/http://web.archive.org/web/20200101000000/http://example.com/reference)

[Missing reference]()
MD
        );

        config([
            'hondabase.content_path' => $this->contentPath,
            'app.url' => 'https://www.hondabase.com',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentPath);
        parent::tearDown();
    }

    public function test_article_renders_alerts_tables_and_seo_metadata(): void
    {
        $this->get('/cars/electronics/render-test')
            ->assertOk()
            ->assertSee('class="markdown-alert markdown-alert-caution"', false)
            ->assertSee('class="markdown-alert-title"', false)
            ->assertSee('class="table-scroll"', false)
            ->assertSee('<th>Component</th>', false)
            ->assertSee('<td>R1</td>', false)
            ->assertSee('<meta name="description" content="Diagnose and repair a Honda ECU safely with correctly rendered warnings and specifications.">', false)
            ->assertSee('<link rel="canonical" href="https://www.hondabase.com/cars/electronics/render-test">', false)
            ->assertSee('"@type":"TechArticle"', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('href="https://example.com/reference?part=ecu&amp;format=html"', false)
            ->assertSee('href="https://web.archive.org/web/20200101000000/http://example.com/reference"', false)
            ->assertSee('<p>Missing reference</p>', false)
            ->assertDontSee('<a href="">Missing reference</a>', false);
    }
}
