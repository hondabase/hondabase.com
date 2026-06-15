<?php

namespace App\Support;

/**
 * Thin accessor over the config('hondabase.locales') map. Centralizes the locale facts the
 * middleware, routes, content layer and views all need: the declared codes, which one is the
 * default (= first entry, English; canonical + fallback), the non-default "others" that get a
 * URL prefix, and the BCP-47 hreflang tag per code.
 */
class Locales
{
    /** @return array<string, array{native: string, hreflang: string}> */
    public static function all(): array
    {
        return (array) config('hondabase.locales', []);
    }

    /** All declared app locale codes, e.g. ['en', 'pt']. */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /** The default locale (first entry): canonical, unprefixed, and the fallback. */
    public static function default(): string
    {
        return (string) (array_key_first(self::all()) ?? 'en');
    }

    /** Non-default locales, which are served under a /{locale} URL prefix. */
    public static function others(): array
    {
        return array_values(array_diff(self::codes(), [self::default()]));
    }

    public static function isSupported(string $code): bool
    {
        return in_array($code, self::codes(), true);
    }

    public static function isDefault(string $code): bool
    {
        return $code === self::default();
    }

    /** BCP-47 tag emitted in <html lang> and hreflang alternates (e.g. 'pt-PT'). */
    public static function hreflang(string $code): string
    {
        return (string) (self::all()[$code]['hreflang'] ?? $code);
    }

    /** Regex alternation of the prefixed locales for route ->where(), e.g. 'pt'. */
    public static function othersPattern(): string
    {
        return implode('|', self::others());
    }
}
