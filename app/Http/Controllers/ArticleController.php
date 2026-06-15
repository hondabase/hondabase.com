<?php

namespace App\Http\Controllers;

use App\Models\ArticleRevision;
use App\Services\ArticleService;
use App\Support\Locales;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArticleController extends Controller
{
    public function __construct(private ArticleService $articles) {}

    // Route params are read by name (not positional method args): the localized routes carry an
    // extra leading {locale} segment, so positional binding would shift type/category/slug. The
    // {locale} param is present only on the /{locale}/... routes; the canonical unprefixed routes
    // have no such param, so this resolves to the default locale and an unprefixed URL is always
    // English (regardless of any cookie).
    public function category(Request $request)
    {
        $type = $request->route('type');
        $category = $request->route('category');
        $locale = $request->route('locale') ?? Locales::default();
        abort_unless($this->articles->categoryExists($type, $category), 404);

        return view('category', [
            'type' => $type,
            'category' => $category,
            'locale' => $locale,
            'type_label' => ucwords(str_replace('-', ' ', $type)),
            'category_label' => ucwords(str_replace('-', ' ', $category)),
            'articles' => $this->articles->articlesIn($type, $category, $locale),
        ]);
    }

    public function show(Request $request)
    {
        $type = $request->route('type');
        $category = $request->route('category');
        $slug = $request->route('slug');
        $locale = $request->route('locale') ?? Locales::default();
        $art = $this->articles->find($type, $category, $slug, $locale);
        abort_unless($art, 404);

        return view('article', ['art' => $art]);
    }

    public function asset(string $type, string $category, string $slug, string $file): BinaryFileResponse
    {
        $path = $this->articles->assetPath($type, $category, $slug, $file);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function stagedAsset(ArticleRevision $revision, string $file): BinaryFileResponse
    {
        $path = $revision->stagedAssetPath($file);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, no-store']);
    }
}
