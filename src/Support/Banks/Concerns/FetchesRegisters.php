<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support\Banks\Concerns;

use Illuminate\Support\Facades\Http;

/**
 * Helpers of the downloadable registers: the HTTP fetch, character set conversion and CSV reading.
 */
trait FetchesRegisters
{
    protected function fetch(string $url): string
    {
        return Http::timeout(60)->retry(2, 500)->get($url)->throw()->body();
    }

    /**
     * Converts to UTF-8 (a body that already is valid UTF-8 is kept) and drops a byte order mark.
     */
    protected function utf8(string $body, string $from): string
    {
        $body = str_starts_with($body, "\u{FEFF}") ? substr($body, 3) : $body;

        return mb_check_encoding($body, 'UTF-8') ? $body : (string) mb_convert_encoding($body, 'UTF-8', $from);
    }

    /**
     * @return list<list<string>>
     */
    protected function csv(string $text, string $delimiter = ';'): array
    {
        $text = str_starts_with($text, "\u{FEFF}") ? substr($text, 3) : $text;
        $handle = fopen('php://memory', 'r+');

        if ($handle === false) {
            return [];
        }

        fwrite($handle, $text);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if ($row !== [null]) {
                $rows[] = array_map(static fn (?string $cell): string => trim((string) $cell), $row);
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Joins the street and the house number of a register that keeps them apart.
     */
    protected function join(string ...$parts): ?string
    {
        $joined = trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));

        return $joined === '' ? null : $joined;
    }
}
