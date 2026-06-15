<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active UI locale for each request and applies it.
 *
 * Precedence: the `locale` cookie (set by the language switcher) → the browser's
 * Accept-Language header → the app default. Only locales declared in
 * config('hondabase.locales') are honoured, so an unknown value falls through.
 */
class SetLocale
{
    public const COOKIE = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    protected function resolve(Request $request): string
    {
        $supported = array_keys(config('hondabase.locales', []));

        $cookie = $request->cookie(self::COOKIE);
        if (is_string($cookie) && in_array($cookie, $supported, true)) {
            return $cookie;
        }

        $preferred = $request->getPreferredLanguage($supported);
        if ($preferred && in_array($preferred, $supported, true)) {
            return $preferred;
        }

        return config('app.locale');
    }
}
