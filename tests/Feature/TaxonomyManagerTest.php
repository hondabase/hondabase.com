<?php

namespace Tests\Feature;

use App\Livewire\TaxonomyManager;
use App\Models\Article;
use App\Models\Subject;
use App\Models\TaxonomyNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P3b: the taxonomy control panel CRUDs the DB-canonical taxonomy. Adding nodes + editing metadata
 * is free; renaming/removing a node with articles filed under it is blocked (it would orphan the
 * content folders). Slug renames cascade to descendant node paths.
 */
class TaxonomyManagerTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['is_staff' => true]);
    }

    public function test_requires_manage_articles(): void
    {
        Livewire::actingAs(User::factory()->create(['is_staff' => false]))
            ->test(TaxonomyManager::class)->assertStatus(403);
    }

    public function test_add_top_node_and_child_compute_paths(): void
    {
        Livewire::actingAs($this->staff())->test(TaxonomyManager::class)
            ->call('newNode', 'cars')
            ->set('nodeKind', 'make')->set('nodeSlug', 'honda')->set('nodeName', 'Honda')
            ->call('saveNode')->assertHasNoErrors();

        $honda = TaxonomyNode::where('path', 'cars/honda')->firstOrFail();
        $this->assertSame('cars/honda', $honda->path);

        Livewire::actingAs($this->staff())->test(TaxonomyManager::class)
            ->call('newNode', 'cars', $honda->id)
            ->set('nodeKind', 'model')->set('nodeSlug', 'civic')->set('nodeName', 'Civic')
            ->call('saveNode')->assertHasNoErrors();

        $civic = TaxonomyNode::where('path', 'cars/honda/civic')->firstOrFail();
        $this->assertSame($honda->id, $civic->parent_id);
    }

    public function test_edit_metadata_sets_chassis_and_years(): void
    {
        $eg = TaxonomyNode::create(['type' => 'cars', 'kind' => 'generation', 'slug' => 'eg', 'name' => 'EG', 'path' => 'cars/eg']);

        Livewire::actingAs($this->staff())->test(TaxonomyManager::class)
            ->call('editNode', $eg->id)
            ->set('nodeChassis', 'eg, eh')->set('nodeStartYear', 1992)->set('nodeEndYear', 1995)
            ->call('saveNode')->assertHasNoErrors();

        $eg->refresh();
        $this->assertSame(['eg', 'eh'], $eg->chassisCodes());
        $this->assertSame('1992-1995', $eg->yearRange());
    }

    public function test_slug_rename_cascades_descendant_paths(): void
    {
        $honda = TaxonomyNode::create(['type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda', 'path' => 'cars/honda']);
        $civic = TaxonomyNode::create(['type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic', 'path' => 'cars/honda/civic', 'parent_id' => $honda->id]);

        Livewire::actingAs($this->staff())->test(TaxonomyManager::class)
            ->call('editNode', $honda->id)
            ->set('nodeSlug', 'honda-motor')
            ->call('saveNode')->assertHasNoErrors();

        $this->assertSame('cars/honda-motor', $honda->fresh()->path);
        $this->assertSame('cars/honda-motor/civic', $civic->fresh()->path); // descendant repathed
    }

    public function test_rename_blocked_when_articles_are_filed_under_node(): void
    {
        $eg = TaxonomyNode::create(['type' => 'cars', 'kind' => 'generation', 'slug' => 'eg', 'name' => 'EG', 'path' => 'cars/honda/civic/eg']);
        Article::create(['type' => 'cars', 'category' => 'honda/civic/eg/engine', 'slug' => 'timing', 'locale' => 'en', 'title' => 'Timing', 'repo_path' => 'cars/honda/civic/eg/engine/timing/timing.md']);

        Livewire::actingAs($this->staff())->test(TaxonomyManager::class)
            ->call('editNode', $eg->id)
            ->set('nodeSlug', 'eg-renamed')
            ->call('saveNode')
            ->assertHasErrors('nodeSlug');

        $this->assertSame('cars/honda/civic/eg', $eg->fresh()->path); // unchanged
    }

    public function test_delete_blocked_when_articles_filed_under_node(): void
    {
        $eg = TaxonomyNode::create(['type' => 'cars', 'kind' => 'generation', 'slug' => 'eg', 'name' => 'EG', 'path' => 'cars/honda/civic/eg']);
        Article::create(['type' => 'cars', 'category' => 'honda/civic/eg', 'slug' => 'overview', 'locale' => 'en', 'title' => 'Overview', 'repo_path' => 'cars/honda/civic/eg/overview/overview.md']);

        Livewire::actingAs($this->staff())->test(TaxonomyManager::class)->call('deleteNode', $eg->id);

        $this->assertNotNull($eg->fresh()); // still there
    }

    public function test_subject_crud(): void
    {
        Livewire::actingAs($this->staff())->test(TaxonomyManager::class)
            ->set('subjectSlug', 'suspension')->set('subjectName', 'Suspension & Handling')
            ->call('saveSubject')->assertHasNoErrors();

        $this->assertDatabaseHas('subjects', ['slug' => 'suspension']);

        $id = Subject::where('slug', 'suspension')->value('id');
        Livewire::actingAs($this->staff())->test(TaxonomyManager::class)->call('deleteSubject', $id);
        $this->assertDatabaseMissing('subjects', ['slug' => 'suspension']);
    }
}
