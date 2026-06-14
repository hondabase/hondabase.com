<?php

namespace App\Markdown;

/**
 * Repairs narrowly identifiable conversion artifacts before CommonMark parses them.
 *
 * Some legacy HTML-to-Markdown conversions collapsed an entire table onto one line:
 * `| A | B | | --- | --- | | 1 | 2 |`. This restores row breaks without changing
 * ordinary Markdown tables or prose.
 */
class MarkdownNormalizer
{
    public function normalize(string $markdown): string
    {
        for ($pass = 0; $pass < 8; $pass++) {
            $changed = false;
            $lines = preg_split('/\r\n|\r|\n/', $markdown);

            foreach ($lines as &$line) {
                $expanded = $this->expandOneCollapsedTable($line);
                if ($expanded !== null) {
                    $line = $expanded;
                    $changed = true;
                }
            }
            unset($line);

            $markdown = implode("\n", $lines);
            if (! $changed) {
                break;
            }
        }

        return $markdown;
    }

    private function expandOneCollapsedTable(string $line): ?string
    {
        if (! preg_match('/\|\s*\|\s*:?-{3,}:?\s*\|/', $line)) {
            return null;
        }

        $pipes = $this->pipePositions($line);
        if (count($pipes) < 6) {
            return null;
        }

        $segments = [];
        $start = 0;
        foreach ($pipes as $pipe) {
            $segments[] = substr($line, $start, $pipe - $start);
            $start = $pipe + 1;
        }
        $segments[] = substr($line, $start);

        $maxInterior = count($pipes) - 1;
        for ($delimiterStart = 1; $delimiterStart <= $maxInterior; $delimiterStart++) {
            if (! $this->isDelimiterCell($segments[$delimiterStart])) {
                continue;
            }

            $delimiterEnd = $delimiterStart;
            while ($delimiterEnd + 1 <= $maxInterior && $this->isDelimiterCell($segments[$delimiterEnd + 1])) {
                $delimiterEnd++;
            }

            $columns = $delimiterEnd - $delimiterStart + 1;
            $boundary = $delimiterStart - 1;
            $headerStart = $boundary - $columns;
            if ($columns < 2 || $headerStart < 1 || trim($segments[$boundary]) !== '') {
                $delimiterStart = $delimiterEnd;

                continue;
            }

            $rows = [
                array_slice($segments, $headerStart, $columns),
                array_slice($segments, $delimiterStart, $columns),
            ];
            $tableEndSegment = $delimiterEnd;
            $cursor = $delimiterEnd + 1;

            while ($cursor <= $maxInterior && trim($segments[$cursor]) === '') {
                $rowStart = $cursor + 1;
                $rowEnd = $rowStart + $columns - 1;
                if ($rowEnd > $maxInterior) {
                    break;
                }

                $rows[] = array_slice($segments, $rowStart, $columns);
                $tableEndSegment = $rowEnd;
                $cursor = $rowEnd + 1;
            }

            $table = implode("\n", array_map(
                fn (array $row) => '| '.implode(' | ', array_map('trim', $row)).' |',
                $rows,
            ));

            $before = rtrim(substr($line, 0, $pipes[$headerStart - 1]));
            $after = ltrim(substr($line, $pipes[$tableEndSegment] + 1));

            return ($before !== '' ? $before."\n\n" : '')
                .$table
                .($after !== '' ? "\n\n".$after : '');
        }

        return null;
    }

    /** @return int[] */
    private function pipePositions(string $line): array
    {
        $positions = [];
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            if ($line[$i] !== '|') {
                continue;
            }

            $slashes = 0;
            for ($j = $i - 1; $j >= 0 && $line[$j] === '\\'; $j--) {
                $slashes++;
            }
            if ($slashes % 2 === 0) {
                $positions[] = $i;
            }
        }

        return $positions;
    }

    private function isDelimiterCell(string $cell): bool
    {
        return preg_match('/^\s*:?-{3,}:?\s*$/', $cell) === 1;
    }
}
