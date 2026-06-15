<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PushSubscriptionController;
use App\Models\Article;
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

// New-article creation (auth-gated). `new` is not a content type, so it never collides with
// the knowledgebase routes below.
Route::get('/new', fn () => view('new'))->middleware('auth')->name('article.new');

// In-browser editor (auth-gated). `edit` is not a content type, so it never collides with
// the knowledgebase routes below. The Livewire component re-checks existence + auth too.
Route::get('/edit/{type}/{category}/{slug}', fn (string $type, string $category, string $slug) => view('edit', compact('type', 'category', 'slug')))
    ->middleware('auth')
    ->where(['type' => 'cars|motorcycles|aircraft|common', 'category' => '[A-Za-z0-9._-]+', 'slug' => '[A-Za-z0-9._-]+'])
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

    // Granting/revoking staff is owner-only (the UI form of `php artisan hondabase:staff`).
    Route::get('/admin/staff', fn () => view('admin.staff'))
        ->middleware('can:manage-staff')
        ->name('admin.staff');

    Route::get('/admin/history/{type}/{category}/{slug}', fn (string $type, string $category, string $slug) => view('admin.history', compact('type', 'category', 'slug')))
        ->where(['type' => 'cars|motorcycles|aircraft|common', 'category' => '[A-Za-z0-9._-]+', 'slug' => '[A-Za-z0-9._-]+'])
        ->name('admin.history.article');
});

Route::get('/sitemap.xml', function () {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
        .'  <url><loc>https://www.hondabase.com/</loc></url>'."\n";
    foreach (Article::orderBy('type')->orderBy('category')->orderBy('slug')->orderBy('locale')->get(['type', 'category', 'slug', 'locale', 'updated_at']) as $a) {
        $prefix = Locales::isDefault($a->locale) ? '' : '/'.$a->locale;
        $loc = 'https://www.hondabase.com'.$prefix.'/'.$a->type.'/'.$a->category.'/'.$a->slug;
        $xml .= '  <url><loc>'.htmlspecialchars($loc).'</loc>'
            .($a->updated_at ? '<lastmod>'.$a->updated_at->toDateString().'</lastmod>' : '')
            .'</url>'."\n";
    }
    $xml .= '</urlset>'."\n";

    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

// Knowledgebase. Types are constrained to the content top-level folders so these
// patterns never shadow other app routes or the legacy /pgmfi, /guides, /reference paths.
$types = 'cars|motorcycles|aircraft|common';
$seg = '[A-Za-z0-9._-]+';

// Co-located article asset (filename must have an extension) - registered before the
// 3-segment article route so it wins for 4-segment paths.
Route::get('/{type}/{category}/{slug}/{file}', [ArticleController::class, 'asset'])
    ->where(['type' => $types, 'category' => $seg, 'slug' => $seg, 'file' => $seg.'\.[A-Za-z0-9]+'])
    ->name('article.asset');

Route::get('/{type}/{category}/{slug}', [ArticleController::class, 'show'])
    ->where(['type' => $types, 'category' => $seg, 'slug' => $seg])
    ->name('article.show');

Route::get('/{type}/{category}', [ArticleController::class, 'category'])
    ->where(['type' => $types, 'category' => $seg])
    ->name('article.category');

// Locale-prefixed mirrors for non-default locales (e.g. /pt/...). The {locale} constraint is
// the declared "others" alternation, so it never shadows a content type or the unprefixed
// (canonical, English) routes above. The default locale is always served unprefixed.
$locales = Locales::othersPattern();
if ($locales !== '') {
    Route::get('/{locale}/{type}/{category}/{slug}', [ArticleController::class, 'show'])
        ->where(['locale' => $locales, 'type' => $types, 'category' => $seg, 'slug' => $seg])
        ->name('article.show.localized');

    Route::get('/{locale}/{type}/{category}', [ArticleController::class, 'category'])
        ->where(['locale' => $locales, 'type' => $types, 'category' => $seg])
        ->name('article.category.localized');
}
