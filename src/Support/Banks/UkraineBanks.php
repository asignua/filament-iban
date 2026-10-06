<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support\Banks;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Ukraine: the bank identifier is the MFO, IBAN positions 5..10 (after `UA` and the check digits). The register is
 * the National Bank of Ukraine open data "Довідник МФО" (every MFO, branch ones included, resolved to the head bank).
 */
class UkraineBanks extends FileBankDirectory
{
    public const string URL = 'https://bank.gov.ua/NBU_BankInfo/get_data_branch?json';

    public function minimumBanks(): int
    {
        return 50;
    }

    public function country(): string
    {
        return 'UA';
    }

    public function source(): string
    {
        return 'National Bank of Ukraine open data, '.self::URL;
    }

    protected function keys(string $iban): array
    {
        return [substr($iban, 4, 6)];
    }

    public function download(): array
    {
        $rows = Http::timeout(60)->retry(2, 500)->acceptJson()->get(self::URL)->throw()->json();

        if (!is_array($rows)) {
            throw new RuntimeException('The NBU register did not return a JSON list.');
        }

        /** @var array<int|string, string> $heads head MFO => name */
        $heads = [];

        foreach ($rows as $row) {
            if (is_array($row) && ($row['TYP'] ?? null) === 0 && isset($row['MFO'])) {
                $heads[(string) $row['MFO']] = $this->name($row);
            }
        }

        $banks = [];

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['MFO'])) {
                continue;
            }

            $mfo = str_pad((string) $row['MFO'], 6, '0', STR_PAD_LEFT);
            $name = $heads[(string) ($row['GLMFO'] ?? '')] ?? $this->name($row);

            if ($name !== '' && !isset($banks[$mfo])) {
                $banks[$mfo] = $name;
            }
        }

        if ($banks === []) {
            throw new RuntimeException('The NBU register contained no banks.');
        }

        ksort($banks, SORT_STRING);

        return $banks;
    }

    /**
     * @param array<mixed> $row
     */
    private function name(array $row): string
    {
        return trim((string) ($row['SHORTNAME'] ?? $row['N_GOL'] ?? ''));
    }
}
