<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ArticlePresentationAuditTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing-presentation-audit');
        File::deleteDirectory($this->root);
        File::ensureDirectoryExists($this->root.'/cars/electronics/broken');
        File::ensureDirectoryExists($this->root.'/pt/cars/electronics/traduzido');
        File::put($this->root.'/cars/electronics/broken/broken.md', "# Broken\n\nIntro ## Glued\n\n| A | B | | --- | --- | | 1 | 2 |\n");
        File::put($this->root.'/pt/cars/electronics/traduzido/traduzido.md', "# Traduzido\n\nText ```\n");
        config(['hondabase.content_path' => $this->root]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_audit_reports_all_locales_without_failing(): void
    {
        $this->assertSame(0, Artisan::call('hondabase:audit-presentation'));
        $output = Artisan::output();
        $this->assertStringContainsString('cars/electronics/broken/broken.md', $output);
        $this->assertStringContainsString('pt/cars/electronics/traduzido/traduzido.md', $output);
        $this->assertStringContainsString('collapsed table', $output);
        $this->assertStringContainsString('malformed fence', $output);
    }

    public function test_audit_can_limit_to_one_locale(): void
    {
        $this->assertSame(0, Artisan::call('hondabase:audit-presentation', ['--locale' => ['pt']]));
        $output = Artisan::output();
        $this->assertStringContainsString('traduzido.md', $output);
        $this->assertStringNotContainsString('broken.md', $output);
    }
}
