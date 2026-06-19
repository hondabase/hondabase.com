<?php

namespace App\Services;

/**
 * Renders embeddable article widgets (the `::: widget <name> ::: ` directive).
 *
 * Widgets are trusted, code-reviewed components in the site repo (Blade + Alpine), not
 * arbitrary code from the content repo. Add a widget by allowing its name here and
 * creating resources/views/widgets/<name>.blade.php.
 */
class WidgetRenderer
{
    private array $allowed = ['error-codes', 'wideband-wiring-table'];

    public function render(string $name, array $attrs = []): ?string
    {
        if (! in_array($name, $this->allowed, true)) {
            return null;
        }
        $view = "widgets.{$name}";
        if (! view()->exists($view)) {
            return null;
        }

        return view($view, $this->data($name, $attrs) + ['attrs' => $attrs])->render();
    }

    private function data(string $name, array $attrs): array
    {
        return match ($name) {
            'error-codes' => ['codes' => $this->errorCodes()],
            default => [],
        };
    }

    /** Normalized Honda OBD trouble codes from the legacy reference dataset. */
    private function errorCodes(): array
    {
        $path = public_path('reference/error-codes/error-codes.json');
        $raw = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $out = [];
        $locale = app()->getLocale();

        foreach ((array) $raw as $e) {
            $out[] = [
                'code' => array_map('strval', (array) ($e['code'] ?? [])),
                'system' => (string) ($e['system'] ?? 'ECU'),
                'short' => (string) ($e['short'] ?? ''),
                'long' => (string) ($e['long'][$locale] ?? $e['long']['en'] ?? ''),
                'causes' => (string) ($e['causes'][$locale] ?? $e['causes']['en'] ?? ''),
            ];
        }

        return $out;
    }
}
