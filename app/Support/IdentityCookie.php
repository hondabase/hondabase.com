<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Cookie\CookieJar;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cross-stack SSO identity cookie. `www` (the Laravel IdP) issues a signed cookie scoped to
 * `.hondabase.com`; the sibling non-Laravel `files/` app verifies the HMAC and hydrates its
 * own session from it, so a single Discord login on `www` is live on `files.` too.
 *
 * The token is a compact `base64url(json).base64url(hmac)` string (NOT a Laravel encrypted
 * cookie - see the EncryptCookies exception in bootstrap/app.php - so plain PHP can verify it).
 * It carries only already-shared Discord identity (id/name/avatar), never PII, so it is safe
 * to read on either app.
 */
class IdentityCookie
{
    /** Cookie name; kept in sync with the constant of the same purpose in files/app/auth.php. */
    public const NAME = 'hb_identity';

    /** Build the signed cookie carrying this user's Discord identity. */
    public static function make(User $user): Cookie
    {
        $ttl = (int) config('session.lifetime', 43200);

        $payload = [
            'v' => 1,
            'sub' => (string) $user->discord_id,
            'name' => $user->displayName(),
            'av' => $user->avatar,
            'exp' => time() + $ttl * 60,
        ];

        return self::cookie(self::encode($payload), $ttl);
    }

    /**
     * True if the given raw cookie value is a valid, correctly-signed token for this user
     * with enough lifetime left (and current name/avatar) that it needs no re-issue.
     */
    public static function covers(?string $token, User $user): bool
    {
        if (! is_string($token) || substr_count($token, '.') !== 1) {
            return false;
        }

        [$p, $s] = explode('.', $token, 2);
        $json = base64_decode(strtr($p, '-_', '+/'), true);
        $sig = base64_decode(strtr($s, '-_', '+/'), true);
        if ($json === false || $sig === false
            || ! hash_equals(hash_hmac('sha256', $json, self::secret(), true), $sig)) {
            return false;
        }

        $data = json_decode($json, true);

        // Re-issue once less than half the TTL remains, so the exp keeps sliding forward.
        $minExp = time() + (int) config('session.lifetime', 43200) * 60 / 2;

        return is_array($data)
            && ($data['sub'] ?? null) === (string) $user->discord_id
            && ($data['name'] ?? null) === $user->displayName()
            && ($data['av'] ?? null) === $user->avatar
            && ($data['exp'] ?? 0) >= $minExp;
    }

    /** Build the cookie that clears the shared identity (used on logout). */
    public static function forget(): Cookie
    {
        return self::cookie('', -2628000);
    }

    /** Queue the issue/forget cookie onto the response cookie jar. */
    public static function queue(Cookie $cookie): void
    {
        app(CookieJar::class)->queue($cookie);
    }

    private static function cookie(string $value, int $ttlMinutes): Cookie
    {
        return new Cookie(
            self::NAME,
            $value,
            $ttlMinutes === 0 ? 0 : time() + $ttlMinutes * 60,
            config('session.path', '/'),
            config('session.domain'),            // .hondabase.com
            (bool) config('session.secure', true),
            true,                                 // httpOnly
            false,
            config('session.same_site', 'lax'),
        );
    }

    private static function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $json, self::secret(), true);

        return self::b64url($json).'.'.self::b64url($sig);
    }

    private static function secret(): string
    {
        $secret = (string) config('services.hondabase.sso_secret');
        if ($secret === '') {
            throw new \RuntimeException('HONDABASE_SSO_SECRET is not configured.');
        }

        return $secret;
    }

    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
