<?php

namespace App\Http\Middleware;

use App\Support\IdentityCookie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep the cross-stack SSO cookie alive for the whole login, not just its first 30 days.
 * Remember-me sessions outlive the cookie's TTL, so without this a returning visitor is
 * signed in on www while files/ (which trusts only the cookie) treats them as a guest.
 */
class RefreshIdentityCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (($user = Auth::user()) !== null
            && ! IdentityCookie::covers($request->cookie(IdentityCookie::NAME), $user)) {
            IdentityCookie::queue(IdentityCookie::make($user));
        }

        return $next($request);
    }
}
