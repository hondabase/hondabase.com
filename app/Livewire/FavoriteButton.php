<?php

namespace App\Livewire;

use App\Models\Article;
use App\Models\Favorite;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Save/unsave the current article. Resolves the derived `articles` row from the
 * type/category/slug so the article page (file-rendered) needs no DB lookup of its own.
 */
class FavoriteButton extends Component
{
    public string $type;
    public string $category;
    public string $slug;

    public ?int $articleId = null;
    public bool $saved = false;

    public function mount(): void
    {
        $this->articleId = Article::where('type', $this->type)
            ->where('category', $this->category)
            ->where('slug', $this->slug)
            ->value('id');

        if ($this->articleId && ($user = auth()->user())) {
            $this->saved = $user->favorites()->where('article_id', $this->articleId)->exists();
        }
    }

    public function toggle()
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login', ['return' => url()->current()]);
        }
        if (!$this->articleId) {
            return null;
        }

        $existing = $user->favorites()->where('article_id', $this->articleId)->first();
        if ($existing) {
            $existing->delete();
            $this->saved = false;
        } else {
            Favorite::firstOrCreate(['user_id' => $user->id, 'article_id' => $this->articleId]);
            $this->saved = true;
        }
        return null;
    }

    public function render(): View
    {
        return view('livewire.favorite-button');
    }
}
