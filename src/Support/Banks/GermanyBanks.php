<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support\Banks;

use Asignua\FilamentIban\Support\BankDetails;
use Asignua\FilamentIban\Support\Banks\Concerns\FetchesRegisters;
use RuntimeException;

/**
 * Germany: the Bankleitzahl is IBAN positions 5..12. The register is the Deutsche Bundesbank "Bankleitzahlendatei"
 * (fixed width, 168 characters per line, ISO 8859-1). Only main entries (Merkmal 1) are used. The file's URL carries a
 * content hash that changes with every release, so it is looked up on the download page. Download-only: not shipped.
 */
class GermanyBanks extends FileBankDirectory
{
    use FetchesRegisters;

    public const string PAGE = 'https://www.bundesbank.de/de/aufgaben/unbarer-zahlungsverkehr/serviceangebot/bankleitzahlen/download-bankleitzahlen-602592';

    public function minimumBanks(): int
    {
        return 2000;
    }

    public function country(): string
    {
        return 'DE';
    }

    public function source(): string
    {
        return 'Deutsche Bundesbank, Bankleitzahlendatei, '.self::PAGE;
    }

    protected function keys(string $iban): array
    {
        return [substr($iban, 4, 8)];
    }

    public function download(): array
    {
        $page = $this->fetch(self::PAGE);

        if (preg_match('~href="([^"]*blz-aktuell-txt-data\.txt)"~', $page, $match) !== 1) {
            throw new RuntimeException('The Bundesbank download page no longer links the Bankleitzahlendatei (txt).');
        }

        $url = str_starts_with($match[1], 'http') ? $match[1] : 'https://www.bundesbank.de/'.ltrim($match[1], '/');
        $banks = $this->parse($this->utf8($this->fetch($url), 'ISO-8859-1'));

        if ($banks === []) {
            throw new RuntimeException('The Bundesbank register contained no banks.');
        }

        return $banks;
    }

    /**
     * Columns (1-based): 1-8 BLZ, 9 Merkmal, 10-67 name, 68-72 PLZ, 73-107 Ort, 108-134 short name, 135-139 PAN, 140-150 BIC.
     *
     * @return array<string, array<string, string>|string>
     */
    public function parse(string $text): array
    {
        $banks = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (mb_strlen($line) < 150 || mb_substr($line, 8, 1) !== '1') {
                continue;
            }

            $blz = mb_substr($line, 0, 8);
            $name = trim(mb_substr($line, 9, 58));

            if (preg_match('/^\d{8}$/', $blz) !== 1 || $name === '' || isset($banks[$blz])) {
                continue;
            }

            $banks[$blz] = (new BankDetails(
                $name,
                $this->nullable(mb_substr($line, 139, 11)),
                $this->nullable(mb_substr($line, 72, 35)),
                null,
                $this->nullable(mb_substr($line, 67, 5)),
            ))->toEntry();
        }

        ksort($banks, SORT_STRING);

        return $banks;
    }

    private function nullable(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
