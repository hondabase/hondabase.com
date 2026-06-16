<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\TaxonomyNode;
use App\Services\PathParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product taxonomy + subjects are DB-canonical. PathParser splits an article's category
 * path into its taxonomy-node prefix + subject remainder.
 */
class TaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_pathparser_splits_node_prefix_from_subject(): void
    {
        $honda = TaxonomyNode::create(['type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda', 'path' => 'cars/honda']);
        $civic = TaxonomyNode::create(['parent_id' => $honda->id, 'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic', 'path' => 'cars/honda/civic']);
        $eg = TaxonomyNode::create(['parent_id' => $civic->id, 'type' => 'cars', 'kind' => 'generation', 'slug' => 'eg', 'name' => '5th Gen (EG)', 'path' => 'cars/honda/civic/eg']);

        $p = new PathParser;

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

    public function test_subject_ensure_registers_a_discovered_slug(): void
    {
        Subject::create(['slug' => 'engine', 'name' => 'Engine']);

        Subject::ensure('wiring');
        $this->assertDatabaseHas('subjects', ['slug' => 'wiring', 'name' => 'Wiring']);

        Subject::ensure('engine'); // already present, no duplicate
        $this->assertSame(1, Subject::where('slug', 'engine')->count());
    }
}
