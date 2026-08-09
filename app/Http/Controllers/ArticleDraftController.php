<?php

namespace App\Http\Controllers;

use App\Models\ArticleDraft;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArticleDraftController extends Controller
{
    public function asset(ArticleDraft $draft, string $file): BinaryFileResponse
    {
        abort_unless($draft->user_id === Auth::id(), 404);

        $path = $draft->assetPath($file);
        abort_if($path === null, 404);

        return response()->file($path, ['Cache-Control' => 'private, no-store']);
    }
}
