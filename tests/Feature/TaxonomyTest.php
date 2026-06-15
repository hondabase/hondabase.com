<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\TaxonomyNode;
use App\Services\PathParser;
use App\Services\TaxonomySync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * P1: the product taxonomy + subjects are bootstrapped from a JSON seed by TaxonomySync::import
 * (the DB table is the live source of truth thereafter), and PathParser splits an article's category
 * path into its taxonomy-node prefix + subject remainder.
 */
class TaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing-taxonomy-'.uniqid());
        File::ensureDirectoryExists($this->root.'/_data');
        File::put($this->root.'/_data/taxonomy.json', json_encode([
            'cars' => [
                'honda' => [
                    'kind' => 'make', 'name' => 'Honda', 'children' => [
                        'civic' => [
                            'kind' => 'model', 'name' => 'Civic', 'children' => [
                                'eg' => ['kind' => 'generation', 'name' => '5th Gen (EG)', 'meta' => ['chassis_codes' => ['eg', 'eh'], 'start_year' => 1992, 'end_year' => 1995]],
                            ],
                        ],
                    ],
                ],
            ],
            'motorcycles' => [],
        ]));
        File::put($this->root.'/_data/subjects.json', json_encode([
            'subjects' => ['engine' => 'Engine & Drivetrain', 'electronics' => 'Electronics'],
        ]));
        config(['hondabase.content_path' => $this->root]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_sync_seeds_nodes_and_subjects(): void
    {
        $counts = app(TaxonomySync::class)->import($this->root.'/_data/taxonomy.json', $this->root.'/_data/subjects.json');

        $this->assertSame(['nodes' => 3, 'subjects' => 2], $counts); // honda, civic, eg
        $this->assertDatabaseHas('taxonomy_nodes', ['path' => 'cars/honda/civic/eg', 'kind' => 'generation']);
        $this->assertDatabaseHas('subjects', ['slug' => 'engine']);

        $eg = TaxonomyNode::where('path', 'cars/honda/civic/eg')->first();
        $this->assertSame(['eg', 'eh'], $eg->chassisCodes());
        $this->assertSame('1992-1995', $eg->yearRange());
        $this->assertSame('cars/honda/civic', $eg->parent->path); // hierarchy linked
    }

    public function test_sync_is_idempotent(): void
    {
        app(TaxonomySync::class)->import($this->root.'/_data/taxonomy.json', $this->root.'/_data/subjects.json');
        app(TaxonomySync::class)->import($this->root.'/_data/taxonomy.json', $this->root.'/_data/subjects.json');

        $this->assertSame(3, TaxonomyNode::count());
        $this->assertSame(2, Subject::count());
    }

    public function test_pathparser_splits_node_prefix_from_subject(): void
    {
        app(TaxonomySync::class)->import($this->root.'/_data/taxonomy.json', $this->root.'/_data/subjects.json');
        $p = new PathParser; // fresh (uncached) instance

        $deep = $p->parse('cars', 'honda/civic/eg/engine');
        $this->assertSame('cars/honda/civic/eg', $deep['node']->path);
        $this->assertSame('engine', $deep['subject']);

        $node = $p->parse('cars', 'honda/civic/eg');
        $this->assertSame('cars/honda/civic/eg', $node['node']->path);
        $this->assertSame('', $node['subject']);

        // No taxonomy match at all -> whole path is the subject (generic, subject-centric article).
        $generic = $p->parse('cars', 'electronics/ecu');
        $this->assertNull($generic['node']);
        $this->assertSame('electronics/ecu', $generic['subject']);

        // Partial match: honda is a node, the unknown model segment begins the subject.
        $partial = $p->parse('cars', 'honda/unknownmodel/foo');
        $this->assertSame('cars/honda', $partial['node']->path);
        $this->assertSame('unknownmodel/foo', $partial['subject']);
    }

    public function test_ensure_subject_registers_a_discovered_slug(): void
    {
        app(TaxonomySync::class)->import($this->root.'/_data/taxonomy.json', $this->root.'/_data/subjects.json');
        $sync = app(TaxonomySync::class);

        $sync->ensureSubject('wiring');
        $this->assertDatabaseHas('subjects', ['slug' => 'wiring', 'name' => 'Wiring']);

        $sync->ensureSubject('engine'); // already present, no duplicate
        $this->assertSame(1, Subject::where('slug', 'engine')->count());
    }
}
