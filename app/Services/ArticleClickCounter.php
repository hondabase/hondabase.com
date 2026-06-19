<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleLinkClick;
use App\Support\Locales;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArticleClickCounter
{
    private const ARTICLE_TTL = 1800;

    private const LINK_TTL = 300;

    /** @return array{html: string, counters: list<ArticleLinkClick>} */
    public function decorate(array $article): array
    {
        $html = (string) ($article['html'] ?? '');
        if ($html === '' || ! str_contains($html, '<a')) {
            return ['html' => $html, 'counters' => []];
        }

        $identity = $this->identity($article);
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<!DOCTYPE html><html><body><div id="hb-fragment">'.$html.'</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $dom->getElementById('hb-fragment');
        if (! $root) {
            return ['html' => $html, 'counters' => []];
        }

        $anchors = [];
        foreach ($root->getElementsByTagName('a') as $anchor) {
            if ($this->trackableAnchor($anchor)) {
                $anchors[] = $anchor;
            }
        }

        $seenUrls = [];
        $counters = [];
        foreach ($anchors as $ordinal => $anchor) {
            $url = html_entity_decode($anchor->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $seenUrls[$url] = ($seenUrls[$url] ?? 0) + 1;
            $occurrence = $seenUrls[$url];
            $key = hash('sha256', $url.'|'.$occurrence);
            $label = Str::limit(preg_replace('/\s+/u', ' ', trim($anchor->textContent)), 180, '');

            $counter = ArticleLinkClick::query()->updateOrCreate(
                $identity + ['occurrence_key' => $key],
                [
                    'ordinal' => $ordinal + 1,
                    'url' => $url,
                    'label' => $label !== '' ? $label : null,
                ],
            );
            $counters[] = $counter;

            $anchor->setAttribute('data-article-link-counter', (string) $counter->id);
            $anchor->setAttribute('class', trim($anchor->getAttribute('class').' article-click-link'));

            $badge = $dom->createElement('span', number_format((int) $counter->click_count));
            $badge->setAttribute('class', 'article-click-count');
            $badge->setAttribute('title', trans_choice(':count click|:count clicks', (int) $counter->click_count, ['count' => (int) $counter->click_count]));
            $badge->setAttribute('aria-label', trans_choice(':count click|:count clicks', (int) $counter->click_count, ['count' => (int) $counter->click_count]));
            $this->insertAfter($badge, $anchor);
        }

        return ['html' => $this->fragmentHtml($root), 'counters' => $counters];
    }

    public function countArticleView(?Article $article, Request $request): void
    {
        if (! $article || ! $this->shouldCount($request, 'article:'.$article->id, self::ARTICLE_TTL)) {
            return;
        }

        Article::whereKey($article->id)->update([
            'view_count' => DB::raw('view_count + 1'),
            'last_viewed_at' => Carbon::now(),
        ]);
        $article->view_count = (int) $article->view_count + 1;
    }

    public function countLinkClick(ArticleLinkClick $counter, Request $request): bool
    {
        if (! $this->shouldCount($request, 'link:'.$counter->id, self::LINK_TTL)) {
            return false;
        }

        ArticleLinkClick::whereKey($counter->id)->update([
            'click_count' => DB::raw('click_count + 1'),
            'last_clicked_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return true;
    }

    private function trackableAnchor(DOMElement $anchor): bool
    {
        $href = trim($anchor->getAttribute('href'));
        if ($href === '' || str_starts_with($href, '#')) {
            return false;
        }

        $class = ' '.$anchor->getAttribute('class').' ';
        if (str_contains($class, ' heading-anchor ')) {
            return false;
        }

        return true;
    }

    /** @return array{type: string, category: string, slug: string, locale: string} */
    private function identity(array $article): array
    {
        return [
            'type' => (string) $article['type'],
            'category' => (string) $article['category'],
            'slug' => (string) $article['slug'],
            'locale' => ! empty($article['is_fallback']) ? Locales::default() : (string) ($article['locale'] ?? Locales::default()),
        ];
    }

    private function shouldCount(Request $request, string $subject, int $ttl): bool
    {
        if ($this->isBotOrPrefetch($request)) {
            return false;
        }

        $visitor = 'request:'.hash_hmac('sha256', ($request->ip() ?? '').'|'.(string) $request->userAgent(), (string) config('app.key'));
        $key = 'article-click-counter:'.hash('sha256', $visitor.'|'.$subject);
        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, $ttl);

        return true;
    }

    private function isBotOrPrefetch(Request $request): bool
    {
        $ua = strtolower((string) $request->userAgent());
        if ($ua === '') {
            return true;
        }

        foreach (['bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'twitterbot', 'discordbot', 'linkedinbot', 'preview', 'wget', 'curl'] as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }

        foreach (['purpose', 'sec-purpose', 'x-moz'] as $header) {
            if (str_contains(strtolower((string) $request->headers->get($header)), 'prefetch')) {
                return true;
            }
        }

        return false;
    }

    private function insertAfter(DOMNode $newNode, DOMNode $reference): void
    {
        if ($reference->nextSibling) {
            $reference->parentNode?->insertBefore($newNode, $reference->nextSibling);
        } else {
            $reference->parentNode?->appendChild($newNode);
        }
    }

    private function fragmentHtml(DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $root->ownerDocument->saveHTML($child);
        }

        return $html;
    }
}
