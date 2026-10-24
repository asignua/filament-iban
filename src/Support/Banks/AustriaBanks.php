<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support\Banks;

use Asignua\FilamentIban\Support\BankDetails;
use Asignua\FilamentIban\Support\Banks\Concerns\FetchesRegisters;
use RuntimeException;

/**
 * Austria: the Bankleitzahl is IBAN positions 5..9. The register is the OeNB "SEPA-Verzeichnis" (semicolon CSV,
 * ISO 8859-1, a few lines of notice above the header). Download-only: not shipped.
 */
class AustriaBanks extends FileBankDirectory
{
    use FetchesRegisters;

    public const string URL = 'https://www.oenb.at/docroot/downloads_observ/sepa-zv-vz_gesamt.csv';

    public function minimumBanks(): int
    {
        return 300;
    }

    public function country(): string
    {
        return 'AT';
    }

    public function source(): string
    {
        return 'Oesterreichische Nationalbank, SEPA-Verzeichnis, '.self::URL;
    }

    protected function keys(string $iban): array
    {
        return [substr($iban, 4, 5)];
    }

    public function download(): array
    {
        $banks = $this->parse($this->utf8($this->fetch(self::URL), 'ISO-8859-1'));

        if ($banks === []) {
            throw new RuntimeException('The OeNB register contained no banks.');
        }

        return $banks;
    }

    /**
     * @return array<string, array<string, string>|string>
     */
    public function parse(string $text): array
    {
        $header = null;
        $banks = [];

        foreach ($this->csv($text) as $row) {
            if ($header === null) {
                if (in_array('Bankleitzahl', $row, true)) {
                    $header = array_flip($row);
                }

                continue;
            }

            $get = static fn (string $column): string => $row[$header[$column] ?? -1] ?? '';
            $blz = str_pad($get('Bankleitzahl'), 5, '0', STR_PAD_LEFT);
            $name = $get('Bankenname');

            if (preg_match('/^\d{5}$/', $blz) !== 1 || $name === '' || isset($banks[$blz])) {
                continue;
            }

            $banks[$blz] = (new BankDetails(
                $name,
                $get('SWIFT-Code') ?: null,
                $get('Ort') ?: null,
                $get('Straße') ?: null,
                $get('PLZ') ?: null,
            ))->toEntry();
        }

        ksort($banks, SORT_STRING);

        return $banks;
    }
}
