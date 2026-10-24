<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support\Banks;

use Asignua\FilamentIban\Support\BankDetails;
use Asignua\FilamentIban\Support\Banks\Concerns\FetchesRegisters;
use RuntimeException;

/**
 * Czechia: the bank code is IBAN positions 5..8. The register is the ČNB list of payment service providers
 * ("Kódy bank", semicolon CSV, UTF-8 with BOM: code, name, BIC, CERTIS flag). Download-only: not shipped.
 */
class CzechBanks extends FileBankDirectory
{
    use FetchesRegisters;

    public const string URL = 'https://www.cnb.cz/cs/platebni-styk/.galleries/ucty_kody_bank/download/kody_bank_CR.csv';

    public function minimumBanks(): int
    {
        return 20;
    }

    public function country(): string
    {
        return 'CZ';
    }

    public function source(): string
    {
        return 'Česká národní banka, kódy bank, '.self::URL;
    }

    protected function keys(string $iban): array
    {
        return [substr($iban, 4, 4)];
    }

    public function download(): array
    {
        $banks = $this->parse($this->utf8($this->fetch(self::URL), 'ISO-8859-2'));

        if ($banks === []) {
            throw new RuntimeException('The CNB register contained no banks.');
        }

        return $banks;
    }

    /**
     * @return array<string, array<string, string>|string>
     */
    public function parse(string $text): array
    {
        $banks = [];

        foreach ($this->csv($text) as $row) {
            $code = $row[0] ?? '';
            $name = $row[1] ?? '';

            // The header row and any footer fail the code check.
            if (preg_match('/^\d{4}$/', $code) !== 1 || $name === '' || isset($banks[$code])) {
                continue;
            }

            $banks[$code] = (new BankDetails($name, ($row[2] ?? '') ?: null))->toEntry();
        }

        ksort($banks, SORT_STRING);

        return $banks;
    }
}
