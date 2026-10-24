<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support\Banks;

use Asignua\FilamentIban\Support\BankDetails;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Ukraine: the bank identifier is the MFO, IBAN positions 5..10 (after `UA` and the check digits). The register is
 * the National Bank of Ukraine open data "Довідник МФО" (every MFO, branch ones included, resolved to the head bank:
 * name, city, address, postcode and status are those of the head office). The register has no BIC.
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

        /** @var array<string, BankDetails> $heads head MFO => details */
        $heads = [];

        foreach ($rows as $row) {
            if (is_array($row) && ($row['TYP'] ?? null) === 0 && isset($row['MFO'])) {
                $heads[(string) $row['MFO']] = $this->details($row);
            }
        }

        $banks = [];

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['MFO'])) {
                continue;
            }

            $mfo = str_pad((string) $row['MFO'], 6, '0', STR_PAD_LEFT);
            $details = $heads[(string) ($row['GLMFO'] ?? '')] ?? $this->details($row);

            if ($details->name !== '' && !isset($banks[$mfo])) {
                $banks[$mfo] = $details->toEntry();
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
    private function details(array $row): BankDetails
    {
        $text = static fn (string $key): ?string => is_string($row[$key] ?? null) && trim($row[$key]) !== '' ? trim($row[$key]) : null;

        return new BankDetails(
            trim((string) ($row['SHORTNAME'] ?? $row['N_GOL'] ?? '')),
            null,
            $text('NP'),
            $text('ADRESS'),
            $text('P_IND'),
            $this->status($row),
        );
    }

    /**
     * KSTAN 1 = normal (null); 4 = liquidation; 5 = activity suspended; anything else keeps the register's own text.
     *
     * @param array<mixed> $row
     */
    private function status(array $row): ?string
    {
        return match ($row['KSTAN'] ?? null) {
            null, 1 => null,
            4 => 'liquidation',
            5 => 'suspended',
            default => is_string($row['N_STAN'] ?? null) && trim($row['N_STAN']) !== '' ? trim($row['N_STAN']) : null,
        };
    }
}
