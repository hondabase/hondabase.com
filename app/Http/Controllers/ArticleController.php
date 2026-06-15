<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\Compatibility;
use App\Models\TaxonomyNode;
use App\Services\ArticleService;
use App\Support\BreadcrumbBuilder;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArticleController extends Controller
{
    public function __construct(private ArticleService $articles, private BreadcrumbBuilder $crumbs) {}

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
                return view('article', [
                    'art' => $art,
                    'crumbs' => $this->crumbs->forCategory($type, $category, $locale),
                ]);
            }
        }

        // 3. Taxonomy node landing page (e.g. /cars/honda/civic/eg): a product/generation page
        //    listing everything that fits it (inherited + explicit) plus its child nodes.
        $node = TaxonomyNode::firstWhere('path', "{$type}/{$path}");
        if ($node) {
            return $this->nodePage($node, $locale);
        }

        // 4. Category listing: the whole path is a (subject) category (any depth).
        if ($this->articles->categoryExists($type, $path)) {
            return view('category', [
                'type' => $type,
                'category' => $path,
                'locale' => $locale,
                'type_label' => ucwords(str_replace('-', ' ', $type)),
                'category_label' => ucwords(str_replace('-', ' ', basename($path))),
                'crumbs' => $this->crumbs->forCategory($type, $path, $locale),
            ]);
        }

        abort(404);
    }

    /** Render a taxonomy node landing page: its metadata, child nodes, and the articles that fit
     *  it or any descendant (via the compatibilities pivot). */
    private function nodePage(TaxonomyNode $node, string $locale)
    {
        $articleIds = Compatibility::whereIn('taxonomy_node_id', $node->selfAndDescendantIds())
            ->distinct()->pluck('article_id');

        // Compatibility links live on the default-locale (English) identity row; render that set and
        // mark each row with the requested locale so url() emits the localized link.
        $articles = Article::whereIn('id', $articleIds)
            ->where('locale', Locales::default())
            ->orderByDesc('updated_at')->get()
            ->each(fn (Article $a) => $a->locale = $locale);

        $category = Str::after($node->path, $node->type.'/');

        return view('node', [
            'node' => $node,
            'locale' => $locale,
            'articles' => $articles,
            'children' => $node->children()->orderBy('name')->get(),
            'crumbs' => $this->crumbs->forCategory($node->type, $category, $locale),
        ]);
    }

    public function stagedAsset(ArticleRevision $revision, string $file): BinaryFileResponse
    {
        $path = $revision->stagedAssetPath($file);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, no-store']);
    }
}
