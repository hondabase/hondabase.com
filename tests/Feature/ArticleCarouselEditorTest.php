<?php

namespace Tests\Feature;

use App\Jobs\CommitArticle;
use App\Livewire\ArticleEditor;
use App\Livewire\RevisionReview;
use App\Markdown\CarouselParser;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class ArticleCarouselEditorTest extends TestCase
{
    use RefreshDatabase;

    private string $contentPath;

    private string $articleDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentPath = storage_path('framework/testing-carousel-editor');
        $this->articleDir = $this->contentPath.'/cars/electronics/carousel-test';
        File::deleteDirectory($this->contentPath);
        File::ensureDirectoryExists($this->articleDir);
        File::put($this->articleDir.'/carousel-test.md', "# Carousel test\n\nExisting article body with enough text.\n");
        File::put($this->articleDir.'/existing.jpg', 'existing-image');
        config(['hondabase.content_path' => $this->contentPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentPath);
        parent::tearDown();
    }

    public function test_parser_accepts_image_slides_and_rejects_malformed_carousels(): void
    {
        $parser = app(CarouselParser::class);
        $valid = "```carousel\n![Front](front.jpg)\n*Front caption*\n<!-- slide -->\n![Rear](rear.jpg)\n```";
        $invalid = "```carousel\n![](front.jpg)\n<!-- slide -->\n![Rear](https://example.com/rear.jpg)\n```";

        $this->assertSame([], $parser->errors($valid));
        $this->assertCount(1, $parser->errors($invalid));
    }

    public function test_editor_uploads_are_collision_safe_and_only_referenced_files_are_staged(): void
    {
        $user = User::factory()->create(['is_staff' => false]);
        $component = Livewire::actingAs($user)->test(ArticleEditor::class, [
            'type' => 'cars',
            'category' => 'electronics',
            'slug' => 'carousel-test',
        ])->set('images', [
            UploadedFile::fake()->image('existing.jpg'),
            UploadedFile::fake()->image('unused.png'),
        ]);

        $assets = $component->instance()->editorAssets();
        $this->assertContains('existing.jpg', array_column($assets, 'name'));
        $this->assertContains('existing-1.jpg', array_column($assets, 'name'));
        $this->assertContains('unused.png', array_column($assets, 'name'));
        $this->assertTrue(collect($assets)->firstWhere('name', 'existing-1.jpg')['pending']);

        $component->set('bodyMarkdown', <<<'MD'
# Carousel test

Updated article body with enough text.

```carousel
![Existing image](existing.jpg)
<!-- slide -->
![Uploaded image](existing-1.jpg)
```
MD)->call('submit')->assertHasNoErrors();

        $revision = ArticleRevision::sole();
        $this->assertSame(['existing-1.jpg'], $revision->assets);
        $this->assertFileExists($revision->assetStagingDir().'/existing-1.jpg');
        $this->assertFileDoesNotExist($revision->assetStagingDir().'/unused.png');
    }

    public function test_pending_upload_urls_are_used_in_server_preview(): void
    {
        $preview = app(ArticleService::class)->preview(
            "# Preview\n\n![Pending image](pending.jpg)\n",
            'cars',
            'electronics',
            'carousel-test',
            ['pending.jpg' => '/livewire/preview-file?signature=abc&expires=123'],
        );

        $this->assertStringContainsString('src="/livewire/preview-file?signature=abc&amp;expires=123"', $preview['html']);
    }

    public function test_staff_can_preview_staged_assets_and_rejection_cleans_them_up(): void
    {
        $author = User::factory()->create(['is_staff' => false]);
        $staff = User::factory()->create(['is_staff' => true]);
        $revision = ArticleRevision::create([
            'user_id' => $author->id,
            'type' => 'cars',
            'category' => 'electronics',
            'slug' => 'carousel-test',
            'title' => 'Carousel test',
            'repo_path' => 'cars/electronics/carousel-test/carousel-test.md',
            'original_body' => 'old',
            'proposed_body' => 'new',
            'assets' => ['pending.jpg'],
            'status' => 'pending',
        ]);
        File::ensureDirectoryExists($revision->assetStagingDir());
        File::put($revision->assetStagingDir().'/pending.jpg', 'pending-image');

        $this->actingAs($staff)
            ->get(route('admin.revision.asset', ['revision' => $revision, 'file' => 'pending.jpg']))
            ->assertOk();

        Livewire::actingAs($staff)->test(RevisionReview::class)->call('reject', $revision->id);

        $this->assertFileDoesNotExist($revision->assetStagingDir().'/pending.jpg');
        $this->assertSame('rejected', $revision->fresh()->status);
    }

    public function test_differing_concurrent_asset_filename_is_a_conflict(): void
    {
        $revision = new ArticleRevision(['assets' => ['same.jpg']]);
        $revision->id = 999999;
        File::ensureDirectoryExists($revision->assetStagingDir());
        File::put($revision->assetStagingDir().'/same.jpg', 'new-image');
        File::put($this->articleDir.'/same.jpg', 'other-image');

        $method = new ReflectionMethod(CommitArticle::class, 'hasAssetConflict');
        $this->assertTrue($method->invoke(new CommitArticle(1), $revision, $this->articleDir));

        $revision->cleanupStagedAssets();
    }
}
