<?php

namespace App\Livewire;

use App\Models\Article;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HideArticleButton extends Component
{
    public string $type;

    public string $category;

    public string $slug;

    public string $locale;

    public bool $hidden = false;

    public function mount(): void
    {
        $article = Article::where([
            'type' => $this->type,
            'category' => $this->category,
            'slug' => $this->slug,
            'locale' => $this->locale,
        ])->first();

        $this->hidden = (bool) $article?->is_hidden;
    }

    public function toggle(): void
    {
        abort_unless(auth()->user()?->isStaff(), 403);

        $this->hidden = ! $this->hidden;

        Article::where('type', $this->type)
            ->where('category', $this->category)
            ->where('slug', $this->slug)
            ->where('locale', $this->locale)
            ->update(['is_hidden' => $this->hidden]);
    }

    public function render(): View
    {
        return view('livewire.hide-article-button');
    }
}
