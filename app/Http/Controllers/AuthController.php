<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\IdentityCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

/**
 * Discord OAuth, sharing the files app's Discord application. Membership of the community
 * guild is required; users are auto-joined where possible (ported from the files app).
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        // www is the sole identity provider: sibling apps (e.g. files/) send users here with
        // ?return=<their URL> and we bounce them back after login (the SSO cookie is set by
        // then). Stash it across the Discord round-trip; only allow .hondabase.com targets.
        $return = $request->query('return');
        if (is_string($return) && self::isSafeReturn($return)) {
            $request->session()->put('sso_return', $return);
        }

        // setScopes (not scopes) so we do NOT request `email` (the provider's default). We
        // never collect or store email, so it can never appear in a backup dump.
        return Socialite::driver('discord')
            ->setScopes(['identify', 'guilds', 'guilds.join'])
            ->redirect();
    }

    /** Only permit post-login redirects back to https://*.hondabase.com (no open redirect). */
    private static function isSafeReturn(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);

        return $host === 'hondabase.com' || str_ends_with($host, '.hondabase.com');
    }

    public function callback(Request $request)
    {
        try {
            $discord = Socialite::driver('discord')->user();
        } catch (\Throwable $e) {
            report($e);
            return redirect('/')->with('flash', 'Discord login failed. Please try again.');
        }

        if (!$this->ensureGuildMember($discord->getId(), $discord->token)) {
            return response()->view('auth.guild-required', [], 403);
        }

        $raw = $discord->getRaw();
        $username = trim((string) ($raw['username'] ?? $discord->getName() ?? ''));
        $globalName = trim((string) ($raw['global_name'] ?? '')) ?: null;

        $user = User::updateOrCreate(
            ['discord_id' => $discord->getId()],
            [
                'name'                => $globalName ?: ($username ?: ('user' . $discord->getId())),
                'discord_username'    => $username ?: null,
                'discord_global_name' => $globalName,
                'avatar'              => $discord->getAvatar(),
            ],
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Issue the shared .hondabase.com identity cookie so files/ sees this login too.
        IdentityCookie::queue(IdentityCookie::make($user));

        // If a sibling app sent us here, bounce back to it now that the cookie is set.
        if ($return = $request->session()->pull('sso_return')) {
            return redirect()->away($return);
        }

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Clear the shared identity cookie: logging out of www logs out of files/ too.
        IdentityCookie::queue(IdentityCookie::forget());

        return redirect('/');
    }

    /** True if the user is in the community guild, joining them via the bot if needed. */
    private function ensureGuildMember(string $discordId, string $token): bool
    {
        $guild = config('services.discord.guild_id');
        if (!$guild) {
            return true;
        }

        $guilds = Http::withToken($token)->acceptJson()
            ->get('https://discord.com/api/users/@me/guilds')->json();
        if (is_array($guilds) && collect($guilds)->contains(fn ($g) => ($g['id'] ?? null) === $guild)) {
            return true;
        }

        $bot = config('services.discord.bot_token');
        if (!$bot) {
            return false;
        }
        $resp = Http::withHeaders(['Authorization' => 'Bot ' . $bot])
            ->put("https://discord.com/api/guilds/{$guild}/members/{$discordId}", ['access_token' => $token]);

        return in_array($resp->status(), [201, 204], true);
    }
}
