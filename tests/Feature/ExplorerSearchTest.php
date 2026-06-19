<?php

namespace Tests\Feature;

use App\Livewire\Explorer;
use App\Models\Article;
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
}
