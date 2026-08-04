<?php

namespace App\Http\Controllers;

use App\Models\ManualFavorite;
use App\Support\IdentityCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ManualFavoriteController extends Controller
{
    private const ALLOWED_ORIGIN = 'https://manuals.hondabase.com';

    public function status(Request $request): JsonResponse
    {
        $paths = collect($request->query('paths', []))
            ->filter(fn ($path) => is_string($path))
            ->map(fn ($path) => $this->normalizePath($path))
            ->filter()
            ->unique()
            ->values();

        $saved = [];
        if ($request->user() && $paths->isNotEmpty()) {
            $saved = ManualFavorite::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('path', $paths)
                ->pluck('path')
                ->map(fn (string $path) => $this->encodePath($path))
                ->all();
        }

        $user = $request->user();

        return $this->cors($request, response()->json([
            'authenticated' => (bool) $request->user(),
            'saved' => $saved,
            'login_url' => route('login', ['return' => self::ALLOWED_ORIGIN.'/']),
            'user' => $user ? [
                'name' => $user->displayName(),
                'profile_url' => route('me'),
                'favorites_url' => route('me'),
                'logout_url' => route('manuals.logout'),
            ] : null,
        ]));
    }

    public function toggle(Request $request): JsonResponse
    {
        if (! $request->user()) {
            return $this->cors($request, response()->json([
                'authenticated' => false,
                'login_url' => route('login', ['return' => $this->safeReturnUrl($request)]),
            ], 401));
        }

        $data = $request->validate([
            'path' => ['required', 'string', 'max:1024'],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:1200'],
        ]);

        $path = $this->normalizePath($data['path']);
        abort_if($path === null, 422, 'Invalid manual path.');
        $this->safeManualUrl($data['url'], $path);

        $existing = ManualFavorite::query()
            ->where('user_id', $request->user()->id)
            ->where('path', $path)
            ->first();

        if ($existing) {
            $existing->delete();
            $saved = false;
        } else {
            ManualFavorite::query()->create([
                'user_id' => $request->user()->id,
                'path' => $path,
                'name' => Str::limit($data['name'], 255, ''),
                'url' => self::ALLOWED_ORIGIN.$this->encodePath($path),
            ]);
            $saved = true;
        }

        return $this->cors($request, response()->json([
            'authenticated' => true,
            'saved' => $saved,
            'path' => $path,
        ]));
    }

    public function destroy(Request $request, ManualFavorite $manualFavorite)
    {
        abort_unless($manualFavorite->user_id === $request->user()->id, 404);

        $manualFavorite->delete();

        return back();
    }

    public function preflight(Request $request): JsonResponse
    {
        return $this->cors($request, response()->json(null, 204));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        IdentityCookie::queue(IdentityCookie::forget());

        return $this->cors($request, response()->json(['authenticated' => false]));
    }

    private function normalizePath(string $path): ?string
    {
        $path = rawurldecode(parse_url($path, PHP_URL_PATH) ?: $path);
        $path = '/'.ltrim($path, '/');

        if (str_contains($path, "\0") || str_contains($path, '/../') || str_ends_with($path, '/..')) {
            return null;
        }

        return $path;
    }

    private function safeManualUrl(string $url, string $expectedPath): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'manuals.hondabase.com') {
            abort(422, 'Invalid manual URL.');
        }

        $path = $this->normalizePath($parts['path'] ?? '/');
        abort_if($path === null, 422, 'Invalid manual path.');
        abort_if($path !== $expectedPath, 422, 'Manual URL does not match path.');
    }

    private function encodePath(string $path): string
    {
        return '/'.implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
    }

    private function safeReturnUrl(Request $request): string
    {
        $return = (string) $request->input('return', self::ALLOWED_ORIGIN.'/');
        $parts = parse_url($return);

        return (($parts['scheme'] ?? '') === 'https' && ($parts['host'] ?? '') === 'manuals.hondabase.com')
            ? $return
            : self::ALLOWED_ORIGIN.'/';
    }

    private function cors(Request $request, JsonResponse $response): JsonResponse
    {
        if ($request->headers->get('Origin') === self::ALLOWED_ORIGIN) {
            $response->headers->set('Access-Control-Allow-Origin', self::ALLOWED_ORIGIN);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
