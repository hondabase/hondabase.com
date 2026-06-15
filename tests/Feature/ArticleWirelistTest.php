<?php

namespace Tests\Feature;

use App\Markdown\WirelistParser;
use App\Services\ArticleService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArticleWirelistTest extends TestCase
{
    private string $root;

    private string $wirelist = <<<'JSON'
{
  "title": "ECU connections",
  "variants": [{
    "id": "p28",
    "label": "USDM P28",
    "groups": [{
      "label": "ROM socket",
      "rows": [{"pin": "Pin 1", "signal": "VCC", "path": "373 Pin 10 -> FT -> ROM Pin 14 (GND)", "note": ""}]
    }]
  }]
}
JSON;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing-wirelist');
        File::deleteDirectory($this->root);
        File::ensureDirectoryExists($this->root.'/cars/electronics/wirelist-test');
        File::put($this->root.'/cars/electronics/wirelist-test/wirelist-test.md', "# Wirelist test\n\n```wirelist\n{$this->wirelist}\n```\n");
        config(['hondabase.content_path' => $this->root]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_parser_rejects_invalid_wirelists(): void
    {
        $parser = app(WirelistParser::class);
        $this->assertNotNull($parser->parse($this->wirelist));
        $this->assertCount(1, $parser->errors("```wirelist\n{\"title\":\"Broken\"}\n```"));
    }

    public function test_article_renders_searchable_wirelist(): void
    {
        $article = app(ArticleService::class)->find('cars', 'electronics', 'wirelist-test');
        $this->assertStringContainsString('class="wirelist"', $article['html']);
        $this->assertStringContainsString('USDM P28', $article['html']);
        $this->assertStringContainsString('class="wirelist-path"', $article['html']);
        $this->assertStringContainsString('<strong>ROM Pin 14</strong>', $article['html']);
        $this->assertStringContainsString('<code>GND</code>', $article['html']);
        $this->assertStringNotContainsString('```wirelist', $article['html']);
    }
}
