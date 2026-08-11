<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\TaxonomyNode;
use App\Services\ArticleService;
use App\Support\Locales;
use Illuminate\Support\Facades\Cache;

/**
 * XML sitemap for the whole knowledgebase: home, explore, type indexes,
 * category listings, taxonomy node pages and articles, in every locale.
 * Each <url> carries xhtml:link hreflang alternates (including itself and
 * x-default) so search engines pair the locale variants.
 *
 * The response is cached; hondabase:reindex busts the cache so the sitemap
 * follows content updates without rebuilding on every crawl.
 */
class SitemapController extends Controller
{
    public const CACHE_KEY = 'sitemap.xml';

    public function __invoke(ArticleService $articles)
    {
        $xml = Cache::remember(self::CACHE_KEY, now()->addHours(6), fn () => $this->build($articles));

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function build(ArticleService $articles): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        // Home + search, then the structural pages. These all exist in every locale.
        $xml .= $this->entries('/');
        $xml .= $this->entries('/explore');
        foreach (config('hondabase.types') as $type) {
            $xml .= $this->entries('/'.$type);
        }
        foreach (config('hondabase.types') as $type) {
            foreach ($articles->categories($type) as $cat) {
                $xml .= $this->entries('/'.$type.'/'.$cat['slug']);
            }
        }
        foreach (TaxonomyNode::orderBy('path')->pluck('path') as $path) {
            $xml .= $this->entries('/'.$path);
        }

        // Articles: only locales that actually exist are listed and paired.
        $grouped = Article::where('is_hidden', false)
            ->orderBy('type')->orderBy('category')->orderBy('slug')->orderBy('locale')
            ->get(['type', 'category', 'slug', 'locale', 'updated_at'])
            ->groupBy(fn ($a) => $a->type.'/'.$a->category.'/'.$a->slug);
        foreach ($grouped as $path => $variants) {
            $locales = $variants->pluck('locale')->all();
            foreach ($variants as $a) {
                $xml .= $this->entry('/'.$path, $a->locale, $locales, $a->updated_at?->toDateString());
            }
        }

        return $xml.'</urlset>'."\n";
    }

    /** One <url> per supported locale for a page that exists in all of them. */
    private function entries(string $path): string
    {
        $out = '';
        foreach (Locales::codes() as $locale) {
            $out .= $this->entry($path, $locale, Locales::codes());
        }

        return $out;
    }

    private function entry(string $path, string $locale, array $availableLocales, ?string $lastmod = null): string
    {
        $out = '  <url><loc>'.$this->loc($path, $locale).'</loc>';
        if ($lastmod) {
            $out .= '<lastmod>'.$lastmod.'</lastmod>';
        }
        if (count($availableLocales) > 1) {
            foreach ($availableLocales as $alt) {
                $out .= '<xhtml:link rel="alternate" hreflang="'.Locales::hreflang($alt).'" href="'.$this->loc($path, $alt).'"/>';
            }
            if (in_array(Locales::default(), $availableLocales, true)) {
                $out .= '<xhtml:link rel="alternate" hreflang="x-default" href="'.$this->loc($path, Locales::default()).'"/>';
            }
        }

        return $out.'</url>'."\n";
    }

    /** Absolute, XML-escaped URL for a path in a locale ('/' is the homepage). */
    private function loc(string $path, string $locale): string
    {
        $prefix = Locales::isDefault($locale) ? '' : '/'.$locale;
        $path = $path === '/' ? ($prefix === '' ? '/' : '') : $path;

        return htmlspecialchars(rtrim(config('app.url'), '/').$prefix.$path);
    }
}
