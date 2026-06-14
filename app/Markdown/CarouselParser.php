<?php

namespace App\Markdown;

/**
 * Parses Hondabase image-carousel fences.
 *
 * A valid carousel contains at least two image slides separated by an HTML comment. Each
 * slide is deliberately narrow: one co-located image, required alt text, and an optional
 * plain-text italic caption. Keeping this parser shared prevents the renderer, linter, and
 * editor submission path from accepting subtly different formats.
 */
class CarouselParser
{
    private const BLOCK_PATTERN = '/^(`{3,})carousel[ \t]*\r?\n(.*?)^\1[ \t]*$/ms';

    private const SLIDE_SEPARATOR = '/^[ \t]*<!--\s*slide\s*-->[ \t]*$/mi';

    private const SLIDE_PATTERN = '/^!\[(?<alt>[^\]\r\n]+)\]\((?<src>[^)\r\n]+)\)(?:\r?\n+[ \t]*\*(?<caption>[^\r\n]*)\*)?[ \t]*$/';

    private const LOCAL_IMAGE_PATTERN = '/^(?:\.\/)?[A-Za-z0-9._-]+\.(?:jpe?g|png|gif|svg|webp)$/i';

    /**
     * Replace every valid carousel fence. Invalid fences are left unchanged so content is
     * visible and recoverable instead of silently disappearing.
     */
    public function replace(string $markdown, callable $replacement): string
    {
        return preg_replace_callback(self::BLOCK_PATTERN, function (array $match) use ($replacement) {
            $slides = $this->parseSlides($match[2]);

            return $slides === null ? $match[0] : $replacement($slides);
        }, $markdown);
    }

    /** Return human-readable validation errors for every malformed carousel fence. */
    public function errors(string $markdown): array
    {
        preg_match_all(self::BLOCK_PATTERN, $markdown, $matches, PREG_SET_ORDER);

        $errors = [];
        foreach ($matches as $index => $match) {
            if ($this->parseSlides($match[2]) === null) {
                $errors[] = 'Carousel #'.($index + 1).' must contain at least two slides; each slide needs one local image with alt text and may have one italic caption.';
            }
        }

        return $errors;
    }

    /** Parse a fence body to normalized slide data, or null when it is outside the contract. */
    public function parseSlides(string $body): ?array
    {
        $parts = preg_split(self::SLIDE_SEPARATOR, trim($body));
        if (! is_array($parts) || count($parts) < 2) {
            return null;
        }

        $slides = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (! preg_match(self::SLIDE_PATTERN, $part, $match)) {
                return null;
            }

            $alt = trim($match['alt']);
            $src = trim($match['src']);
            $caption = trim($match['caption'] ?? '');
            if ($alt === '' || ! preg_match(self::LOCAL_IMAGE_PATTERN, $src)) {
                return null;
            }

            $slides[] = [
                'src' => preg_replace('#^\./#', '', $src),
                'alt' => $alt,
                'caption' => $caption,
            ];
        }

        return $slides;
    }
}
