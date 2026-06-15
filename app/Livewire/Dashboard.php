<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\Favorite;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * "My Hondabase" - the personalized dashboard. Pulls together the user's garage, a
 * Following feed (recent articles matching any followed facet), and their saved articles.
 * When the account is empty it shows light onboarding instead.
 */
class Dashboard extends Component
{
    public function unfollow(string $kv): void
    {
        [$kind, $value] = array_pad(explode(':', $kv, 2), 2, '');
        auth()->user()->follows()->where('kind', $kind)->where('value', $value)->delete();
    }

    public function unsave(int $articleId): void
    {
        auth()->user()->favorites()->where('article_id', $articleId)->delete();
    }

    public function render(): View
    {
        $user = auth()->user();
        $follows = $user->follows()->orderBy('kind')->orderBy('label')->get();
        $followed = $follows->map(fn ($f) => $f->kind.':'.$f->value)->all();
        $vehicles = $user->products()->latest()->get();

        $favorites = Favorite::where('user_id', $user->id)
            ->join('articles', 'articles.id', '=', 'favorites.article_id')
            ->orderByDesc('favorites.created_at')
            ->get(['articles.id', 'articles.type', 'articles.category', 'articles.slug', 'articles.title', 'articles.summary']);

        $isEmpty = $vehicles->isEmpty() && $follows->isEmpty() && $favorites->isEmpty();

        return view('livewire.dashboard', [
            'vehicles' => $vehicles,
            'follows' => $follows,
            'favorites' => $favorites,
            'feed' => $this->feed($followed),
            'isEmpty' => $isEmpty,
        ]);
    }

    /** Recent articles matching anything the user follows (the "Following" feed). */
    private function feed(array $followed)
    {
        if (empty($followed)) {
            return collect();
        }
        $place = implode(',', array_fill(0, count($followed), '?'));

        return Article::query()
            ->whereRaw(
                "EXISTS (SELECT 1 FROM article_facets af WHERE af.article_id = articles.id AND CONCAT(af.kind, ':', af.value) IN ($place))",
                $followed
            )
            ->orderByRaw('updated_at IS NULL, updated_at DESC')
            ->orderBy('title')
            ->limit(18)
            ->get(['id', 'type', 'category', 'slug', 'title', 'summary', 'updated_at']);
    }
}
