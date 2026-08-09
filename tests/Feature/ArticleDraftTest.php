<?php

namespace Tests\Feature;

use App\Livewire\ArticleCreator;
use App\Models\ArticleDraft;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Support\ArticleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleDraftTest extends TestCase
{
    use RefreshDatabase;

    private string $contentPath;

    private string $draftAssetPath;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid();
        $this->contentPath = storage_path('framework/testing-article-draft-content-'.$suffix);
        $this->draftAssetPath = storage_path('framework/testing-article-draft-assets-'.$suffix);
        File::ensureDirectoryExists($this->contentPath);
        config([
            'hondabase.content_path' => $this->contentPath,
            'hondabase.draft_asset_path' => $this->draftAssetPath,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentPath);
        File::deleteDirectory($this->draftAssetPath);

        parent::tearDown();
    }

    public function test_user_can_save_and_resume_a_private_article_draft(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(ArticleCreator::class)
            ->set('type', 'cars')
            ->set('bodyMarkdown', "# Draft knock sensor guide\n\nThis private draft has enough body text to resume later.\n")
            ->set('category', 'electronics')
            ->set('slug', 'draft-knock-sensor')
            ->set('fmSummary', 'A saved summary.')
            ->set('fmTags', 'sensors, diagnostics')
            ->set('note', 'Still gathering resistance values.')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $draft = ArticleDraft::sole();
        $component->assertRedirect(route('article.new.draft', $draft));
        $this->assertSame($user->id, $draft->user_id);
        $this->assertSame('Draft knock sensor guide', $draft->title);
        $this->assertDatabaseCount('article_revisions', 0);

        $document = ArticleDocument::parse($draft->document);
        $this->assertSame('A saved summary.', $document['fm']['summary']);
        $this->assertSame(['sensors', 'diagnostics'], $document['fm']['tags']);

        $this->actingAs($user)->get(route('article.new.draft', $draft))->assertOk();

        Livewire::actingAs($user)->test(ArticleCreator::class, ['draftId' => $draft->id])
            ->assertSet('category', 'electronics')
            ->assertSet('slug', 'draft-knock-sensor')
            ->assertSet('fmSummary', 'A saved summary.')
            ->assertSet('fmTags', 'sensors, diagnostics')
            ->assertSet('note', 'Still gathering resistance values.')
            ->assertSee('Draft knock sensor guide');
    }

    public function test_other_users_cannot_open_a_draft_or_its_assets(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $draft = ArticleDraft::create([
            'user_id' => $owner->id,
            'title' => 'Private draft',
            'type' => 'cars',
            'category' => 'electronics',
            'slug' => 'private-draft',
            'document' => "# Private draft\n\nSecret work in progress.\n",
            'assets' => ['diagram.png'],
        ]);
        File::ensureDirectoryExists($draft->assetDirectory());
        File::put($draft->assetDirectory().'/diagram.png', 'private-image');

        $this->actingAs($other)->get(route('article.new.draft', $draft))->assertNotFound();
        $this->actingAs($other)->get(route('article.draft.asset', [$draft, 'diagram.png']))->assertNotFound();
        $this->actingAs($owner)->get(route('article.draft.asset', [$draft, 'diagram.png']))->assertOk();

        Livewire::actingAs($other)->test(ArticleCreator::class, ['draftId' => $draft->id])
            ->assertNotFound();
    }

    public function test_saved_draft_image_is_resumed_and_staged_when_submitted(): void
    {
        $user = User::factory()->create(['is_staff' => false]);

        Livewire::actingAs($user)->test(ArticleCreator::class)
            ->set('type', 'cars')
            ->set('bodyMarkdown', "# Sensor diagram\n\nA detailed sensor diagram for this new article.\n\n![Circuit](diagram.png)\n")
            ->set('category', 'electronics')
            ->set('slug', 'sensor-diagram')
            ->set('images', [UploadedFile::fake()->image('Diagram.PNG')])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $draft = ArticleDraft::sole();
        $draftAsset = $draft->assetDirectory().'/diagram.png';
        $this->assertSame(['diagram.png'], $draft->assets);
        $this->assertFileExists($draftAsset);

        $resumed = Livewire::actingAs($user)->test(ArticleCreator::class, ['draftId' => $draft->id]);
        $this->assertContains('diagram.png', array_column($resumed->instance()->editorAssets(), 'name'));

        $resumed->call('submit')->assertHasNoErrors();

        $revision = ArticleRevision::sole();
        $this->assertSame(['diagram.png'], $revision->assets);
        $this->assertFileExists($revision->assetStagingDir().'/diagram.png');
        $this->assertDatabaseMissing('article_drafts', ['id' => $draft->id]);
        $this->assertFileDoesNotExist($draftAsset);

        $revision->cleanupStagedAssets();
    }

    public function test_deleting_a_draft_removes_its_saved_assets(): void
    {
        $user = User::factory()->create();
        $draft = ArticleDraft::create([
            'user_id' => $user->id,
            'title' => 'Disposable draft',
            'type' => 'cars',
            'document' => "# Disposable draft\n",
            'assets' => ['photo.jpg'],
        ]);
        File::ensureDirectoryExists($draft->assetDirectory());
        File::put($draft->assetDirectory().'/photo.jpg', 'draft-image');
        $directory = $draft->assetDirectory();

        Livewire::actingAs($user)->test(ArticleCreator::class, ['draftId' => $draft->id])
            ->call('deleteDraft', $draft->id)
            ->assertRedirect(route('article.new'));

        $this->assertDatabaseMissing('article_drafts', ['id' => $draft->id]);
        $this->assertDirectoryDoesNotExist($directory);
    }

    public function test_frontmatter_disclosures_stay_open_while_rows_are_added(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ArticleCreator::class)
            ->assertSeeHtml('class="ed-disclosure" wire:ignore.self')
            ->call('addAppliesTo')
            ->assertSet('fmAppliesTo.0.key', '')
            ->call('addSource')
            ->assertSet('fmSources.0.name', '')
            ->assertSeeHtml('class="ed-disclosure" wire:ignore.self');
    }
}
