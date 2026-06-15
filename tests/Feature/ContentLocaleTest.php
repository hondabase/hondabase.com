<?php

namespace Tests\Feature;

use App\Services\ArticleIndexer;
use App\Services\ArticleService;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Content i18n: locale-prefixed article routes, English fallback, hreflang/canonical, and the
 * per-locale index. Backed by a throwaway content tree so it never depends on the live clone.
 */
class ContentLocaleTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/content-'.uniqid());
        config(['hondabase.content_path' => $this->root]);

        // English article with a translation.
        $this->seedFile('cars/electronics/knock-sensor/knock-sensor.md', "# Knock Sensor\n\nThe knock sensor resistance is critical.\n");
        $this->seedFile('pt/cars/electronics/knock-sensor/knock-sensor.md', "# Sensor de Detonação\n\nA resistência do sensor de detonação é fundamental.\n");
        // English-only article (no translation) to exercise the fallback path.
        $this->seedFile('cars/electronics/map-sensor/map-sensor.md', "# MAP Sensor\n\nManifold absolute pressure basics.\n");

        // ArticleService reads content_path in its constructor; rebuild it + the index against the fixture.
        $this->app->forgetInstance(ArticleService::class);
        $this->app->make(ArticleIndexer::class)->indexAll();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function seedFile(string $relative, string $contents): void
    {
        $path = $this->root.'/'.$relative;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    public function test_english_article_is_canonical_and_unprefixed(): void
    {
        $this->get('/cars/electronics/knock-sensor')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('Knock Sensor')
            ->assertSee('<link rel="canonical" href="'.url('/cars/electronics/knock-sensor').'">', false)
            ->assertSee('hreflang="pt-PT"', false)
            ->assertSee(url('/pt/cars/electronics/knock-sensor'), false);
    }

    public function test_localized_route_renders_the_translation(): void
    {
        $this->get('/pt/cars/electronics/knock-sensor')
            ->assertOk()
            ->assertSee('lang="pt-PT"', false)
            ->assertSee('Sensor de Detonação')
            ->assertSee('<link rel="canonical" href="'.url('/pt/cars/electronics/knock-sensor').'">', false);
    }

    public function test_missing_translation_falls_back_to_english(): void
    {
        $this->get('/pt/cars/electronics/map-sensor')
            ->assertOk()
            ->assertSee('lang="pt-PT"', false)
            ->assertSee('MAP Sensor')
            ->assertSee('A mostrar a versão em inglês'); // fallback notice (pt)
    }

    public function test_unsupported_locale_prefix_is_not_routed(): void
    {
        $this->get('/xx/cars/electronics/knock-sensor')->assertNotFound();
    }

    public function test_reindex_creates_a_translation_row(): void
    {
        $this->assertDatabaseHas('articles', [
            'type' => 'cars', 'category' => 'electronics', 'slug' => 'knock-sensor', 'locale' => 'pt',
        ]);
        $this->assertDatabaseHas('articles', [
            'slug' => 'knock-sensor', 'locale' => 'en',
        ]);
        // The English-only article has no pt row.
        $this->assertDatabaseMissing('articles', [
            'slug' => 'map-sensor', 'locale' => 'pt',
        ]);
    }

    public function test_available_locales_lists_only_existing_translations(): void
    {
        $svc = $this->app->make(ArticleService::class);
        $this->assertEqualsCanonicalizing(['en', 'pt'], $svc->availableLocales('cars', 'electronics', 'knock-sensor'));
        $this->assertSame(['en'], $svc->availableLocales('cars', 'electronics', 'map-sensor'));
        $this->assertSame(Locales::default(), 'en');
    }
}
