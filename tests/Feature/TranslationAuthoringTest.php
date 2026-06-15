<?php

namespace Tests\Feature;

use App\Jobs\CommitArticle;
use App\Livewire\ArticleEditor;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Services\ArticleAuthorService;
use App\Services\ArticleIndexer;
use App\Services\FollowerNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase C: authoring translations through the TipTap editor. A non-default locale edit flows
 * through the same revision -> review -> commit pipeline but writes to the /{locale}/... mirror
 * path, reindexes the translation row, and never notifies followers (facets stay on English).
 */
class TranslationAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing-translate-'.uniqid());
        File::ensureDirectoryExists($this->root.'/cars/electronics/knock-sensor');
        File::put(
            $this->root.'/cars/electronics/knock-sensor/knock-sensor.md',
            "# Knock Sensor\n\nThe knock sensor resistance is critical to ignition timing.\n",
        );
        config(['hondabase.content_path' => $this->root]);

        // CommitArticle commits into a real git repo; init one with an initial commit so HEAD exists.
        foreach ([
            ['git', 'init', '-q'],
            ['git', '-c', 'user.name=t', '-c', 'user.email=t@t', 'add', '-A'],
            ['git', '-c', 'user.name=t', '-c', 'user.email=t@t', 'commit', '-q', '-m', 'seed'],
        ] as $cmd) {
            Process::path($this->root)->run($cmd);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_new_translation_seeds_the_canvas_from_english(): void
    {
        $user = User::factory()->create(['is_staff' => false]);

        $component = Livewire::actingAs($user)->test(ArticleEditor::class, [
            'type' => 'cars', 'category' => 'electronics', 'slug' => 'knock-sensor', 'locale' => 'pt',
        ]);

        $component->assertSet('isTranslation', true)
            ->assertSet('isNewTranslation', true)
            ->assertSet('locale', 'pt')
            ->assertSet('repoPath', 'pt/cars/electronics/knock-sensor/knock-sensor.md')
            // body is pre-filled from the English source so the translator has the structure...
            ->assertSet('original', '');           // ...but the on-disk base is empty (new file)
        $this->assertStringContainsString('knock sensor resistance', $component->get('bodyMarkdown'));
    }

    public function test_member_translation_is_pending_and_targets_the_locale_path(): void
    {
        $user = User::factory()->create(['is_staff' => false]);

        Livewire::actingAs($user)->test(ArticleEditor::class, [
            'type' => 'cars', 'category' => 'electronics', 'slug' => 'knock-sensor', 'locale' => 'pt',
        ])
            ->set('bodyMarkdown', "# Sensor de Detonação\n\nA resistência do sensor é fundamental para a ignição.\n")
            ->call('submit')->assertHasNoErrors();

        $rev = ArticleRevision::sole();
        $this->assertSame('pt', $rev->locale);
        $this->assertSame('pending', $rev->status);
        $this->assertSame('pt/cars/electronics/knock-sensor/knock-sensor.md', $rev->repo_path);
        $this->assertSame('', $rev->original_body);
        // No translation file written yet (still pending review), and no pt index row.
        $this->assertFileDoesNotExist($this->root.'/pt/cars/electronics/knock-sensor/knock-sensor.md');
        $this->assertDatabaseMissing('articles', ['slug' => 'knock-sensor', 'locale' => 'pt']);
    }

    public function test_staff_translation_commits_to_the_locale_tree_and_indexes_a_pt_row(): void
    {
        $staff = User::factory()->create(['is_staff' => true]);

        Livewire::actingAs($staff)->test(ArticleEditor::class, [
            'type' => 'cars', 'category' => 'electronics', 'slug' => 'knock-sensor', 'locale' => 'pt',
        ])
            ->set('bodyMarkdown', "# Sensor de Detonação\n\nA resistência do sensor é fundamental para a ignição.\n")
            ->call('submit')->assertHasNoErrors();

        $rev = ArticleRevision::sole();
        $this->assertSame('approved', $rev->status);

        // Staff edits auto-apply: run the queued commit job.
        (new CommitArticle($rev->id))->handle(
            app(ArticleIndexer::class),
            app(ArticleAuthorService::class),
            app(FollowerNotifier::class),
        );

        $rev->refresh();
        $this->assertNotNull($rev->commit_sha);
        $file = $this->root.'/pt/cars/electronics/knock-sensor/knock-sensor.md';
        $this->assertFileExists($file);
        $this->assertStringContainsString('Sensor de Detonação', File::get($file));
        // The English source is untouched and a pt index row now exists.
        $this->assertStringContainsString('Knock Sensor', File::get($this->root.'/cars/electronics/knock-sensor/knock-sensor.md'));
        $this->assertDatabaseHas('articles', ['slug' => 'knock-sensor', 'locale' => 'pt']);
    }

    public function test_english_edit_still_defaults_to_the_default_locale(): void
    {
        $user = User::factory()->create(['is_staff' => false]);

        $component = Livewire::actingAs($user)->test(ArticleEditor::class, [
            'type' => 'cars', 'category' => 'electronics', 'slug' => 'knock-sensor',
        ]);

        $component->assertSet('locale', 'en')
            ->assertSet('isTranslation', false)
            ->assertSet('repoPath', 'cars/electronics/knock-sensor/knock-sensor.md');
    }

    public function test_translation_of_a_missing_article_404s(): void
    {
        $user = User::factory()->create(['is_staff' => false]);

        Livewire::actingAs($user)->test(ArticleEditor::class, [
            'type' => 'cars', 'category' => 'electronics', 'slug' => 'does-not-exist', 'locale' => 'pt',
        ])->assertStatus(404);
    }
}
