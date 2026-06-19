<?php

namespace Tests\Feature;

use App\Livewire\Explorer;
use App\Models\Article;
use App\Models\ArticleFacet;
use App\Models\Compatibility;
use App\Models\TaxonomyNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExplorerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_slug_words_not_only_exact_phrase(): void
    {
        Article::create([
            'type' => 'cars',
            'category' => 'wiring',
            'slug' => 'how-to-wire-wideband',
            'locale' => 'en',
            'title' => 'Wideband O2 Sensor Installation Guide',
            'summary' => 'Installation and configuration guide for AEM wideband O2 sensors on OBD1 Honda ECUs.',
            'body_text' => 'A wideband oxygen sensor is essential for precise engine tuning.',
            'repo_path' => 'cars/wiring/how-to-wire-wideband/how-to-wire-wideband.md',
        ]);

        Livewire::test(Explorer::class)
            ->set('q', 'How to wire wideband')
            ->assertSee('Wideband O2 Sensor Installation Guide');
    }

    public function test_results_order_by_view_count_first(): void
    {
        Article::create([
            'type' => 'cars',
            'category' => 'wiring',
            'slug' => 'low-view-article',
            'locale' => 'en',
            'title' => 'Low View Article',
            'summary' => 'Wiring guide',
            'body_text' => 'wiring',
            'repo_path' => 'cars/wiring/low-view-article/low-view-article.md',
            'view_count' => 3,
        ]);
        Article::create([
            'type' => 'cars',
            'category' => 'wiring',
            'slug' => 'high-view-article',
            'locale' => 'en',
            'title' => 'High View Article',
            'summary' => 'Wiring guide',
            'body_text' => 'wiring',
            'repo_path' => 'cars/wiring/high-view-article/high-view-article.md',
            'view_count' => 42,
        ]);

        Livewire::test(Explorer::class)
            ->set('q', 'wiring')
            ->assertSeeInOrder(['High View Article', 'Low View Article']);
    }

    public function test_search_matches_all_words_across_body_facets_and_taxonomy(): void
    {
        $match = Article::create([
            'type' => 'cars',
            'category' => 'tuning',
            'slug' => 'cn2-datalogging',
            'locale' => 'en',
            'title' => 'CN2 Datalogging Header',
            'summary' => 'ECU interface setup',
            'body_text' => 'Serial setup for Honda ECU telemetry.',
            'repo_path' => 'cars/tuning/cn2-datalogging/cn2-datalogging.md',
        ]);
        ArticleFacet::create([
            'article_id' => $match->id,
            'kind' => 'tag',
            'value' => 'datalogging',
            'label' => 'datalogging',
        ]);
        $civic = TaxonomyNode::create([
            'type' => 'cars',
            'kind' => 'model',
            'slug' => 'civic',
            'name' => 'Civic',
            'path' => 'cars/honda/civic',
        ]);
        Compatibility::create([
            'article_id' => $match->id,
            'taxonomy_node_id' => $civic->id,
            'source' => 'explicit',
        ]);

        Article::create([
            'type' => 'cars',
            'category' => 'tuning',
            'slug' => 'generic-datalogging',
            'locale' => 'en',
            'title' => 'Generic Datalogging',
            'summary' => 'ECU interface setup',
            'body_text' => 'Serial setup for Honda ECU telemetry.',
            'repo_path' => 'cars/tuning/generic-datalogging/generic-datalogging.md',
        ]);

        Livewire::test(Explorer::class)
            ->set('q', 'civic serial datalogging')
            ->assertSee('CN2 Datalogging Header')
            ->assertDontSee('Generic Datalogging');
    }

    public function test_better_search_match_sorts_before_popularity(): void
    {
        Article::create([
            'type' => 'cars',
            'category' => 'tuning',
            'slug' => 'generic-popular-note',
            'locale' => 'en',
            'title' => 'Generic Popular Note',
            'summary' => 'A popular article',
            'body_text' => 'Civic serial references appear deep in the body.',
            'repo_path' => 'cars/tuning/generic-popular-note/generic-popular-note.md',
            'view_count' => 1000,
        ]);
        Article::create([
            'type' => 'cars',
            'category' => 'tuning',
            'slug' => 'civic-serial-communication',
            'locale' => 'en',
            'title' => 'Civic Serial Communication',
            'summary' => 'Focused article',
            'body_text' => 'Focused article.',
            'repo_path' => 'cars/tuning/civic-serial-communication/civic-serial-communication.md',
            'view_count' => 1,
        ]);

        Livewire::test(Explorer::class)
            ->set('q', 'civic serial')
            ->assertSeeInOrder(['Civic Serial Communication', 'Generic Popular Note']);
    }

    public function test_serial_communication_filter_matches_current_serial_tag(): void
    {
        $article = Article::create([
            'type' => 'cars',
            'category' => 'tuning',
            'slug' => 'serial-communication',
            'locale' => 'en',
            'title' => 'Honda ECU Serial Datalogging (`CN2` TTL Header)',
            'summary' => 'Serial communication guide',
            'body_text' => 'Serial communication is used for ECU datalogging.',
            'repo_path' => 'cars/tuning/serial-communication/serial-communication.md',
        ]);
        ArticleFacet::create([
            'article_id' => $article->id,
            'kind' => 'tag',
            'value' => 'serial',
            'label' => 'serial',
        ]);

        Livewire::withQueryParams([
            'q' => 'Serial communication',
            'filters' => ['tag:serial-communication'],
        ])->test(Explorer::class)
            ->assertSee('Honda ECU Serial Datalogging');
    }
}
