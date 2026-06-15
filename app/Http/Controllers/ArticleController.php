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

    // The knowledgebase serves arbitrary-depth category paths (electronics/ecu/...), which route
    // regex can't disambiguate, so a single catch-all `/{type}/{path}` (+ /{locale}/ mirror) lands
    // here and is resolved against the content/index: co-located asset, then article, then category
    // listing, else 404. Params are read by name: the localized route carries a leading {locale}
    // segment, absent on the canonical unprefixed route (which is therefore always the default,
    // English locale, regardless of any cookie).
    public function resolve(Request $request)
    {
        $type = $request->route('type');
        $path = trim((string) $request->route('path'), '/');
        $locale = $request->route('locale') ?? Locales::default();

        // 1. Co-located asset: the final segment is a filename with an extension. Resolve the
        //    bundle (category path + slug) from the segments before it.
        $last = basename($path);
        if (preg_match('/\.[A-Za-z0-9]+$/', $last) && str_contains($path, '/')) {
            $bundle = ArticleService::splitPath(substr($path, 0, strrpos($path, '/')));
            if ($bundle['category'] !== '') {
                $abs = $this->articles->assetPath($type, $bundle['category'], $bundle['slug'], $last);
                if ($abs) {
                    return response()->file($abs, ['Cache-Control' => 'public, max-age=86400']);
                }
            }
        }

        // 2. Article at this exact path (slug = last segment, category = the rest).
        ['category' => $category, 'slug' => $slug] = ArticleService::splitPath($path);
        if ($category !== '') {
            $art = $this->articles->find($type, $category, $slug, $locale);
            if ($art) {
                return view('article', ['art' => $art]);
            }
        }

        // 3. Category listing: the whole path is a category (any depth).
        if ($this->articles->categoryExists($type, $path)) {
            return view('category', [
                'type' => $type,
                'category' => $path,
                'locale' => $locale,
                'type_label' => ucwords(str_replace('-', ' ', $type)),
                'category_label' => ucwords(str_replace('-', ' ', basename($path))),
            ]);
        }

        abort(404);
    }

    public function stagedAsset(ArticleRevision $revision, string $file): BinaryFileResponse
    {
        $path = $revision->stagedAssetPath($file);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, no-store']);
    }
}
