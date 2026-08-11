<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Support\Locales;
use Illuminate\Support\Facades\Cache;

/**
 * Atom feed of the latest default-locale articles. Hand-built XML like the
 * sitemap: cached, and busted by hondabase:reindex so it follows content.
 */
class FeedController extends Controller
{
    public const CACHE_KEY = 'feed.xml';

    private const LIMIT = 50;

    public function __invoke()
    {
        $xml = Cache::remember(self::CACHE_KEY, now()->addHour(), fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/atom+xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $base = rtrim(config('app.url'), '/');
        $articles = Article::where('is_hidden', false)
            ->where('locale', Locales::default())
            ->orderByDesc('updated_at')
            ->limit(self::LIMIT)
            ->get(['type', 'category', 'slug', 'title', 'summary', 'updated_at']);

        $updated = $articles->max('updated_at') ?? now();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<feed xmlns="http://www.w3.org/2005/Atom">'."\n"
            .'  <title>Hondabase</title>'."\n"
            .'  <subtitle>'.$this->esc(__('Hondabase - a community-driven, GitHub-preserved technical knowledgebase for Honda and Acura vehicles.')).'</subtitle>'."\n"
            .'  <link href="'.$this->esc($base.'/feed.xml').'" rel="self"/>'."\n"
            .'  <link href="'.$this->esc($base.'/').'"/>'."\n"
            .'  <id>'.$this->esc($base.'/').'</id>'."\n"
            .'  <updated>'.$updated->toAtomString().'</updated>'."\n";

        foreach ($articles as $a) {
            $url = $base.'/'.$a->type.'/'.$a->category.'/'.$a->slug;
            $xml .= '  <entry>'."\n"
                .'    <title>'.$this->esc($a->title).'</title>'."\n"
                .'    <link href="'.$this->esc($url).'"/>'."\n"
                .'    <id>'.$this->esc($url).'</id>'."\n"
                .($a->updated_at ? '    <updated>'.$a->updated_at->toAtomString().'</updated>'."\n" : '')
                .($a->summary ? '    <summary>'.$this->esc($a->summary).'</summary>'."\n" : '')
                .'  </entry>'."\n";
        }

        return $xml.'</feed>'."\n";
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
