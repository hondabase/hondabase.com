<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Persist the chosen UI locale in a long-lived cookie and return to the
     * referring page. Unknown locales are ignored (no cookie change).
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $supported = array_keys(config('hondabase.locales', []));

        $back = redirect()->back(fallback: '/');

        if (! in_array($locale, $supported, true)) {
            return $back;
        }

        // forever() = ~5 years; the cookie is not sensitive, so it can stay unencrypted-safe
        // and survive across sessions.
        return $back->withCookie(cookie()->forever(SetLocale::COOKIE, $locale));
    }
}
