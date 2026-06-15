<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active UI locale for each request and applies it.
 *
 * Precedence: a leading /{locale} URL segment (so a shared localized link always renders in
 * its own language) → the `locale` cookie (set by the language switcher) → the browser's
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
        $supported = Locales::codes();

        // A leading prefix segment for a non-default locale wins (e.g. /pt/...). The default
        // locale is never prefixed, so only the "others" are matched here.
        $first = $request->segment(1);
        if (is_string($first) && in_array($first, Locales::others(), true)) {
            return $first;
        }

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
