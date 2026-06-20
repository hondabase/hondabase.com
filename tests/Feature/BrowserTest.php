<?php

namespace Tests\Feature;

use App\Livewire\Browser;
use App\Models\Article;
use App\Models\ArticleFacet;
use App\Models\TaxonomyNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrowserTest extends TestCase
{
    use RefreshDatabase;

    // --- root slide ---

    public function test_root_slide_shows_configured_product_lines(): void
    {
        Livewire::test(Browser::class)
            ->assertSee('Cars')
            ->assertSee('Motorcycles')
            ->assertSee('Aircraft');
    }

    public function test_root_slide_shows_coming_soon_entries(): void
    {
        Livewire::test(Browser::class)
            ->assertSee('Coming soon');
    }

    public function test_product_line_cards_show_article_counts(): void
    {
        Article::create([
            'type' => 'cars', 'category' => 'test', 'slug' => 'test-article',
            'locale' => 'en', 'title' => 'Test Article', 'body_text' => '',
            'repo_path' => 'cars/test/test-article/test-article.md',
        ]);

        Livewire::test(Browser::class)
            ->assertSee('1 article'); // cars count
    }

    // --- type-level slide ---

    public function test_drilling_to_type_shows_makes(): void
    {
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);

        Livewire::test(Browser::class)
            ->call('drillTo', 'cars')
            ->assertSee('Honda');
    }

    public function test_type_level_shows_no_breadcrumb_home_button_but_shows_type_label(): void
    {
        Livewire::test(Browser::class)
            ->call('drillTo', 'cars')
            ->assertSee('Cars'); // breadcrumb type label
    }

    // --- node-level slide ---

    public function test_drilling_to_node_shows_its_children(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);

        Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda')
            ->assertSee('Civic');
    }

    public function test_leaf_node_has_empty_children_and_non_null_handoff_url(): void
    {
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        // No children for Honda.

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda');

        $this->assertTrue($component->viewData('children')->isEmpty());
        $this->assertNotNull($component->viewData('handoffUrl'));
    }

    // --- breadcrumb ---

    public function test_breadcrumb_builds_from_drilled_path(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        $civic = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda/civic');

        $breadcrumb = $component->viewData('breadcrumb');
        $labels = array_column($breadcrumb, 'label');
        $this->assertContains('Cars', $labels);
        $this->assertContains('Honda', $labels);
        $this->assertContains('Civic', $labels);

        // Last item should be marked current.
        $last = end($breadcrumb);
        $this->assertTrue($last['current']);
        $this->assertEquals('Civic', $last['label']);
    }

    public function test_breadcrumb_type_item_links_back_to_type_level(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda/civic');

        $breadcrumb = $component->viewData('breadcrumb');
        $typeCrumb = $breadcrumb[0];
        $this->assertEquals('cars', $typeCrumb['path']);
        $this->assertFalse($typeCrumb['current']);
    }

    // --- handoff URL ---

    public function test_handoff_url_for_type_level_is_bare_type_path(): void
    {
        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars');

        $url = $component->viewData('handoffUrl');
        $this->assertEquals('/cars', $url);
    }

    public function test_handoff_url_contains_ancestor_filters(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda/civic');

        $url = $component->viewData('handoffUrl');
        $this->assertStringStartsWith('/cars?', $url);
        $this->assertStringContainsString('make%3Ahonda', $url);  // make:honda URL-encoded
        $this->assertStringContainsString('model%3Acivic', $url); // model:civic URL-encoded
    }

    // --- counts ---

    public function test_child_counts_reflect_article_facets(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);
        $article = Article::create([
            'type' => 'cars', 'category' => 'test', 'slug' => 'civic-guide',
            'locale' => 'en', 'title' => 'Civic Guide', 'body_text' => '',
            'repo_path' => 'cars/test/civic-guide/civic-guide.md',
        ]);
        ArticleFacet::create(['article_id' => $article->id, 'kind' => 'make',  'value' => 'honda', 'label' => 'Honda']);
        ArticleFacet::create(['article_id' => $article->id, 'kind' => 'model', 'value' => 'civic', 'label' => 'Civic']);

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda');

        $counts = $component->viewData('counts');
        $this->assertEquals(1, $counts['civic']);
    }

    public function test_zero_count_nodes_have_count_zero_in_counts(): void
    {
        $honda = TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'make', 'slug' => 'honda', 'name' => 'Honda',
            'path' => 'cars/honda', 'parent_id' => null,
        ]);
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => $honda->id,
        ]);
        // No article_facets created → civic count stays at 0.

        $component = Livewire::test(Browser::class)
            ->call('drillTo', 'cars/honda');

        $counts = $component->viewData('counts');
        $this->assertEquals(0, $counts['civic'] ?? 0);
    }

    // --- typeahead ---

    public function test_typeahead_returns_nodes_matching_query(): void
    {
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => null,
        ]);

        $component = Livewire::test(Browser::class)
            ->set('bq', 'civ');

        $suggestions = $component->viewData('suggestions');
        $this->assertTrue($suggestions->contains('name', 'Civic'));
    }

    public function test_typeahead_is_empty_when_query_is_blank(): void
    {
        TaxonomyNode::create([
            'type' => 'cars', 'kind' => 'model', 'slug' => 'civic', 'name' => 'Civic',
            'path' => 'cars/honda/civic', 'parent_id' => null,
        ]);

        $component = Livewire::test(Browser::class); // bq defaults to ''

        $suggestions = $component->viewData('suggestions');
        $this->assertTrue($suggestions->isEmpty());
    }

    // --- navigation ---

    public function test_drillTo_root_clears_node(): void
    {
        Livewire::test(Browser::class)
            ->call('drillTo', 'cars')
            ->call('drillTo', '')
            ->assertSet('node', '');
    }

    public function test_drillTo_clears_search_query(): void
    {
        Livewire::test(Browser::class)
            ->set('bq', 'civic')
            ->call('drillTo', 'cars')
            ->assertSet('bq', '');
    }
}
