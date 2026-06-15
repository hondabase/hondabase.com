<?php

namespace App\Markdown;

/**
 * Parses structured ECU wirelists stored as JSON inside a fenced Markdown block.
 *
 * Keeping the dataset inside the article means prose and pin mappings travel through the
 * same approval, diff, commit, and revert pipeline.
 */
class WirelistParser
{
    private const BLOCK_PATTERN = '/^(`{3,})wirelist[ \t]*\r?\n(.*?)^\1[ \t]*$/ms';

    public function replace(string $markdown, callable $replacement): string
    {
        return preg_replace_callback(self::BLOCK_PATTERN, function (array $match) use ($replacement) {
            $wirelist = $this->parse($match[2]);

            return $wirelist === null ? $match[0] : $replacement($wirelist);
        }, $markdown);
    }

    public function errors(string $markdown): array
    {
        preg_match_all(self::BLOCK_PATTERN, $markdown, $matches, PREG_SET_ORDER);

        $errors = [];
        foreach ($matches as $index => $match) {
            if ($this->parse($match[2]) === null) {
                $errors[] = 'Wirelist #'.($index + 1).' must be valid JSON with a title and at least one ECU variant, component group, and pin row.';
            }
        }

        return $errors;
    }

    public function parse(string $json): ?array
    {
        try {
            $data = json_decode(trim($json), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($data) || ! $this->text($data['title'] ?? null) || ! $this->list($data['variants'] ?? null)) {
            return null;
        }

        $variants = [];
        foreach ($data['variants'] as $variant) {
            if (! is_array($variant) || ! $this->text($variant['id'] ?? null)
                || ! preg_match('/^[A-Za-z0-9._-]+$/', $variant['id'])
                || ! $this->text($variant['label'] ?? null) || ! $this->list($variant['groups'] ?? null)) {
                return null;
            }

            $groups = [];
            foreach ($variant['groups'] as $group) {
                if (! is_array($group) || ! $this->text($group['label'] ?? null) || ! $this->list($group['rows'] ?? null)) {
                    return null;
                }

                $rows = [];
                foreach ($group['rows'] as $row) {
                    if (! is_array($row) || ! $this->text($row['pin'] ?? null)
                        || ! $this->text($row['signal'] ?? null) || ! $this->text($row['path'] ?? null)) {
                        return null;
                    }
                    $rows[] = [
                        'pin' => trim((string) $row['pin']),
                        'signal' => trim((string) $row['signal']),
                        'path' => trim((string) $row['path']),
                        'note' => trim((string) ($row['note'] ?? '')),
                    ];
                }

                $groups[] = ['label' => trim((string) $group['label']), 'rows' => $rows];
            }

            $variants[] = [
                'id' => trim((string) $variant['id']),
                'label' => trim((string) $variant['label']),
                'groups' => $groups,
            ];
        }

        return ['title' => trim((string) $data['title']), 'variants' => $variants];
    }

    private function text(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && ! str_contains($value, "\n");
    }

    private function list(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && $value !== [];
    }
}
