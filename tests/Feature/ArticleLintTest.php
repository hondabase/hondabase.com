<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArticleLintTest extends TestCase
{
    private string $contentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentPath = storage_path('framework/testing-article-lint');
        File::deleteDirectory($this->contentPath);
        File::ensureDirectoryExists($this->contentPath.'/common/reference/broken-links');
        File::put(
            $this->contentPath.'/common/reference/broken-links/broken-links.md',
            <<<'MD'
---
summary: Detect malformed links before an article is published.
---
# Broken links

[Missing destination]()

[First](https://example.com)[Second](https://example.net)

[Nested archive](https://web.archive.org/web/20200101000000/https://web.archive.org/web/20200101000000/https://example.com)
MD
        );

        config([
            'hondabase.content_path' => $this->contentPath,
            'hondabase.types' => ['common'],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentPath);
        parent::tearDown();
    }

    public function test_linter_warns_about_malformed_markdown_links(): void
    {
        $this->assertSame(0, Artisan::call('app:lint-articles'));

        $output = Artisan::output();
        $this->assertStringContainsString('Contains a Markdown link with an empty destination', $output);
        $this->assertStringContainsString('Contains adjacent Markdown links without separating whitespace', $output);
        $this->assertStringContainsString('Contains a malformed nested Internet Archive URL', $output);
    }
}
