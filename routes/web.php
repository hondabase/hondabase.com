<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleDraftController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ManualFavoriteController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SitemapController;
use App\Models\ArticleDraft;
use App\Services\ArticleService;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home'))->name('home');

// UI language switcher: persists the chosen locale in a cookie, then returns back.
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// Auth (Discord OAuth, shared application with the files app).
Route::get('/auth/login', [AuthController::class, 'login'])->name('login');
Route::get('/auth/callback', [AuthController::class, 'callback']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('logout');

// Personalization (auth-gated): "My Hondabase" dashboard + garage CRUD. `me` is not a
// content type, so it never collides with the knowledgebase routes below.
Route::middleware('auth')->group(function () {
    Route::get('/me', fn () => view('me'))->name('me');
    Route::get('/me/garage', fn () => view('garage'))->name('me.garage');

    // Web Push subscription lifecycle (called by the service-worker subscribe toggle).
    Route::post('/me/push', [PushSubscriptionController::class, 'store'])->name('push.store');
    Route::delete('/me/push', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
});

// New-article creation + private draft assets (auth-gated). `new` is not a content type, so it
// never collides with the knowledgebase routes below. Draft lookups are owner-scoped to avoid
// exposing even their metadata to another signed-in user.
Route::middleware('auth')->group(function () {
    Route::get('/new', fn () => view('new'))->name('article.new');
    Route::get('/new/{draft}', function (int $draft) {
        abort_unless(ArticleDraft::whereKey($draft)->where('user_id', auth()->id())->exists(), 404);

        return view('new', ['draftId' => $draft]);
    })->whereNumber('draft')->name('article.new.draft');
    Route::get('/new/{draft}/assets/{file}', [ArticleDraftController::class, 'asset'])
        ->where(['draft' => '[0-9]+', 'file' => '[A-Za-z0-9._-]+\.[A-Za-z0-9]+'])
        ->name('article.draft.asset');
});

// In-browser editor (auth-gated). `edit` is not a content type, so it never collides with
// the knowledgebase routes below. The Livewire component re-checks existence + auth too.
Route::get('/edit/{type}/{path}', fn (string $type, string $path) => view('edit', ['type' => $type] + ArticleService::splitPath($path)))
    ->middleware('auth')
    ->where(['type' => 'cars|motorcycles|aircraft|common', 'path' => '[A-Za-z0-9._/-]+'])
    ->name('article.edit');

// Staff-only article management: the pending-edit review queue, and per-article history with
// revert. `manage-articles` = staff or owner (see AppServiceProvider).
Route::middleware(['auth', 'can:manage-articles'])->group(function () {
    Route::get('/admin/reviews', fn () => view('admin.reviews'))->name('admin.reviews');
    Route::get('/admin/reviews/{revision}/assets/{file}', [ArticleController::class, 'stagedAsset'])
        ->where('file', '[A-Za-z0-9._-]+\.[A-Za-z0-9]+')
        ->name('admin.revision.asset');

    Route::get('/admin/history', fn () => view('admin.history', ['type' => null, 'category' => null, 'slug' => null]))
        ->name('admin.history');

    // Product taxonomy control panel (the DB is the live source of truth).
    Route::get('/admin/taxonomy', fn () => view('admin.taxonomy'))->name('admin.taxonomy');

    Route::get('/admin/history/{type}/{path}', fn (string $type, string $path) => view('admin.history', ['type' => $type] + ArticleService::splitPath($path)))
        ->where(['type' => 'cars|motorcycles|aircraft|common', 'path' => '[A-Za-z0-9._/-]+'])
        ->name('admin.history.article');

    Route::delete('/{type}/{path}', [ArticleController::class, 'destroy'])
        ->where(['type' => 'cars|motorcycles|aircraft|common', 'path' => '[A-Za-z0-9._/-]+'])
        ->name('article.destroy');
});

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/feed.xml', FeedController::class)->name('feed');

Route::get('/explore', fn () => view('explore'))->name('explore');

Route::post('/_click/article-links/{counter}', [ArticleController::class, 'clickLink'])
    ->whereNumber('counter')
    ->name('article-link-clicks.store');

Route::options('/manuals/favorites', [ManualFavoriteController::class, 'preflight'])
    ->name('manuals.favorites.preflight');
Route::get('/manuals/favorites', [ManualFavoriteController::class, 'status'])
    ->name('manuals.favorites.status');
Route::post('/manuals/favorites', [ManualFavoriteController::class, 'toggle'])
    ->name('manuals.favorites.toggle');
Route::delete('/manuals/favorites/{manualFavorite}', [ManualFavoriteController::class, 'destroy'])
    ->middleware('auth')
    ->name('manuals.favorites.destroy');
Route::post('/manuals/logout', [ManualFavoriteController::class, 'logout'])
    ->name('manuals.logout');

// Knowledgebase. Types are constrained to the content top-level folders so these
// patterns never shadow other app routes or the legacy /pgmfi, /guides, /reference paths.
$types = 'cars|motorcycles|engines|aircraft|common';
$seg = '[A-Za-z0-9._-]+';
// A category is an arbitrary-depth path (electronics/ecu/...), so the article/category path tail
// may contain slashes. One catch-all per locale resolves it in the controller (article vs category
// listing vs co-located asset), since route regex alone can't disambiguate nested depth.
$pathTail = '[A-Za-z0-9._/-]+';

// Locale-prefixed mirrors for non-default locales (e.g. /pt/...) are registered BEFORE the
// unprefixed catch-all so the {locale} segment is consumed first; the {locale} constraint is the
// declared "others" alternation, so it never shadows a content type. The default locale is
// always served unprefixed.
$locales = Locales::othersPattern();
if ($locales !== '') {
    // Localized homepage (e.g. /pt)
    Route::get('/{locale}', fn () => view('home'))
        ->where('locale', $locales)
        ->name('home.localized');

    // Translation authoring (auth-gated): the literal `edit` segment keeps it distinct from the
    // localized resolve catch-all below (which requires segment 2 to be a content type).
    Route::get('/{locale}/edit/{type}/{path}', fn (string $locale, string $type, string $path) => view('translate', ['locale' => $locale, 'type' => $type] + ArticleService::splitPath($path)))
        ->middleware('auth')
        ->where(['locale' => $locales, 'type' => $types, 'path' => $pathTail])
        ->name('article.translate');

    Route::get('/{locale}/explore', fn () => view('explore'))
        ->where('locale', $locales)
        ->name('explore.localized');

    Route::get('/{locale}/{type}', [ArticleController::class, 'typeIndex'])
        ->where(['locale' => $locales, 'type' => $types])
        ->name('type.index.localized');

    Route::get('/{locale}/{type}/{path}', [ArticleController::class, 'resolve'])
        ->where(['locale' => $locales, 'type' => $types, 'path' => $pathTail])
        ->name('article.show.localized');
}

Route::get('/{type}', [ArticleController::class, 'typeIndex'])
    ->where('type', $types)
    ->name('type.index');

Route::get('/{type}/{path}', [ArticleController::class, 'resolve'])
    ->where(['type' => $types, 'path' => $pathTail])
    ->name('article.show');

Route::fallback(fn () => abort(404));
