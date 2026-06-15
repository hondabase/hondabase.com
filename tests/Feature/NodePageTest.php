<?php

namespace Tests\Feature;

use App\Services\ArticleIndexer;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * P3: taxonomy node landing pages + product/generation-aware breadcrumbs. A node path renders a
 * page listing the articles that fit it (or any descendant) and its child nodes; breadcrumbs label
 * node segments with their generation/model names. Flat subject articles/categories still resolve.
 */
class NodePageTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing-node-'.uniqid());
        File::ensureDirectoryExists($this->root.'/_data');
        File::put($this->root.'/_data/taxonomy.json', json_encode([
            'cars' => [
                'honda' => [
                    'kind' => 'make', 'name' => 'Honda', 'children' => [
                        'civic' => ['kind' => 'model', 'name' => 'Civic', 'children' => [
                            'eg' => ['kind' => 'generation', 'name' => '5th Gen (EG)', 'meta' => ['chassis_codes' => ['eg'], 'start_year' => 1992, 'end_year' => 1995]],
                        ]],
                    ],
                ],
            ],
        ]));
        File::put($this->root.'/_data/subjects.json', json_encode(['subjects' => ['engine' => 'Engine & Drivetrain']]));

        $this->seedFile('cars/honda/civic/eg/engine/d15-timing/d15-timing.md', "# D15 Timing\n\nBelt routing.\n");
        $this->seedFile('cars/electronics/obd1-pinout/obd1-pinout.md', "# OBD1 Pinout\n\nGeneric pinout.\n");

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

    public function test_generation_node_page_lists_compatible_articles(): void
    {
        $this->get('/cars/honda/civic/eg')
            ->assertOk()
            ->assertSee('5th Gen (EG)')
            ->assertSee('1992-1995')
            ->assertSee('D15 Timing');           // inherited (lives under eg/)
    }

    public function test_model_node_page_lists_child_generations(): void
    {
        $this->get('/cars/honda/civic')
            ->assertOk()
            ->assertSee('Civic')
            ->assertSee('5th Gen (EG)')           // child node link
            ->assertSee('D15 Timing');            // descendant compatible article
    }

    public function test_article_breadcrumbs_use_taxonomy_names(): void
    {
        $res = $this->get('/cars/honda/civic/eg/engine/d15-timing')->assertOk();
        $res->assertSee('Civic');                  // model node name
        $res->assertSee('5th Gen (EG)');           // generation node name (not "Eg")
        $res->assertSee('Engine &amp; Drivetrain', false); // subject name (not "Engine")
    }

    public function test_flat_subject_article_and_category_still_resolve(): void
    {
        $this->get('/cars/electronics/obd1-pinout')->assertOk()->assertSee('OBD1 Pinout');
        $this->get('/cars/electronics')->assertOk();
    }

    public function test_unknown_node_child_path_404s(): void
    {
        $this->get('/cars/honda/nonexistent')->assertNotFound();
    }
}
