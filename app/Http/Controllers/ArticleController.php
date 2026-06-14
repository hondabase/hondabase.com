<?php

namespace App\Http\Controllers;

use App\Services\ArticleService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArticleController extends Controller
{
    public function __construct(private ArticleService $articles) {}

    public function category(string $type, string $category)
    {
        abort_unless($this->articles->categoryExists($type, $category), 404);

        return view('category', [
            'type'           => $type,
            'category'       => $category,
            'type_label'     => ucwords(str_replace('-', ' ', $type)),
            'category_label' => ucwords(str_replace('-', ' ', $category)),
            'articles'       => $this->articles->articlesIn($type, $category),
        ]);
    }

    public function show(string $type, string $category, string $slug)
    {
        $art = $this->articles->find($type, $category, $slug);
        abort_unless($art, 404);

        return view('article', ['art' => $art]);
    }

    public function asset(string $type, string $category, string $slug, string $file): BinaryFileResponse
    {
        $path = $this->articles->assetPath($type, $category, $slug, $file);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'public, max-age=86400']);
    }
}
