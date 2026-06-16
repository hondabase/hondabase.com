<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Compatibility;
use App\Models\TaxonomyNode;
use App\Services\ArticleIndexer;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * the sync links articles to taxonomy nodes three ways - inherited (folder location), explicit
 * (`fits:`), and the legacy `applies_to` bridge - and derives make/model/generation facets from the
 * linked nodes. A purely generic article links to nothing.
 */
class CompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing-compat-'.uniqid());

        $honda = TaxonomyNode::create(['type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda', 'path' => 'cars/honda']);
        $civic = TaxonomyNode::create(['parent_id' => $honda->id, 'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic', 'path' => 'cars/honda/civic']);
        TaxonomyNode::create(['parent_id' => $civic->id, 'type' => 'cars', 'kind' => 'generation', 'slug' => 'eg', 'name' => '5th Gen (EG)', 'path' => 'cars/honda/civic/eg', 'meta' => ['chassis_codes' => ['eg', 'eh']]]);
        $integra = TaxonomyNode::create(['parent_id' => $honda->id, 'type' => 'cars', 'kind' => 'model', 'slug' => 'integra', 'name' => 'Integra', 'path' => 'cars/honda/integra']);
        TaxonomyNode::create(['parent_id' => $integra->id, 'type' => 'cars', 'kind' => 'generation', 'slug' => 'dc2', 'name' => '3rd Gen (DC2)', 'path' => 'cars/honda/integra/dc2', 'meta' => ['chassis_codes' => ['dc2', 'db8']]]);

        // 1. inherited - physically under the EG generation folder
        $this->seedFile('cars/honda/civic/eg/engine/d15-timing/d15-timing.md', "# D15 Timing\n\nBelt routing for the D15.\n");
        // 2. explicit - generic article that declares a fit
        $this->seedFile('cars/electronics/k-swap-guide/k-swap-guide.md', "---\nfits:\n  - path: cars/honda/civic/eg\n    notes: needs mounts\n---\n# K Swap Guide\n\nSwap notes.\n");
        // 3. bridge - legacy applies_to chassis value
        $this->seedFile('cars/electronics/dc2-brakes/dc2-brakes.md', "---\napplies_to:\n  chassis: [dc2]\n---\n# DC2 Brakes\n\nBig brake notes.\n");
        // 4. generic - no fit signal at all
        $this->seedFile('cars/electronics/generic-sensor/generic-sensor.md', "---\napplies_to:\n  obd: [1]\n---\n# Generic Sensor\n\nWorks on anything.\n");

        config(['hondabase.content_path' => $this->root]);
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

    private function compatFor(string $slug): Collection
    {
        $id = Article::where('slug', $slug)->where('locale', 'en')->value('id');

        return Compatibility::where('article_id', $id)->get();
    }

    public function test_inherited_link_from_folder_location(): void
    {
        $compat = $this->compatFor('d15-timing');
        $eg = TaxonomyNode::where('path', 'cars/honda/civic/eg')->first();

        $this->assertCount(1, $compat);
        $this->assertSame($eg->id, $compat->first()->taxonomy_node_id);
        $this->assertSame('inherited', $compat->first()->source);

        // node-derived facets give make/model/generation drill-down
        $facets = Article::where('slug', 'd15-timing')->first()->facets->map(fn ($f) => $f->kind.':'.$f->value)->all();
        $this->assertContains('make:honda', $facets);
        $this->assertContains('model:civic', $facets);
        $this->assertContains('generation:eg', $facets);
    }

    public function test_explicit_fits_link(): void
    {
        $compat = $this->compatFor('k-swap-guide');

        $this->assertCount(1, $compat);
        $this->assertSame('explicit', $compat->first()->source);
        $this->assertSame('cars/honda/civic/eg', $compat->first()->node->path);
        $this->assertSame('needs mounts', $compat->first()->meta['notes']);
    }

    public function test_applies_to_chassis_bridge(): void
    {
        $compat = $this->compatFor('dc2-brakes');

        $this->assertSame('cars/honda/integra/dc2', $compat->first()->node->path);
        $this->assertSame('explicit', $compat->first()->source);
    }

    public function test_generic_article_links_to_nothing(): void
    {
        $this->assertCount(0, $this->compatFor('generic-sensor'));
    }
}
