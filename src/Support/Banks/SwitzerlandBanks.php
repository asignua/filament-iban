<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support\Banks;

use Asignua\FilamentIban\Support\BankDetails;
use Asignua\FilamentIban\Support\Banks\Concerns\FetchesRegisters;
use RuntimeException;

/**
 * Switzerland and Liechtenstein: the institution identifier (IID) is IBAN positions 5..9. The register is the SIX
 * "Bank Master" (semicolon CSV, UTF-8); one file serves both countries, because Swiss IIDs are also used by banks
 * of Liechtenstein. Download-only: not shipped.
 */
class SwitzerlandBanks extends FileBankDirectory
{
    use FetchesRegisters;

    public const string URL = 'https://api.six-group.com/api/epcd/bankmaster/v3/bankmaster_V3.csv';

    public function __construct(private readonly string $country = 'CH') {}

    public function minimumBanks(): int
    {
        return 500;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function source(): string
    {
        return 'SIX Interbank Clearing, Bank Master, '.self::URL;
    }

    protected function keys(string $iban): array
    {
        return [substr($iban, 4, 5)];
    }

    public function download(): array
    {
        $banks = $this->parse($this->utf8($this->fetch(self::URL), 'ISO-8859-1'));

        if ($banks === []) {
            throw new RuntimeException('The SIX Bank Master contained no banks.');
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
                $header = array_flip($row);

                if (!isset($header['IID/QR-IID'], $header['Name of bank/institution'])) {
                    throw new RuntimeException('The SIX Bank Master has an unexpected header.');
                }

                continue;
            }

            $get = static fn (string $column): string => $row[$header[$column] ?? -1] ?? '';
            $iid = str_pad($get('IID/QR-IID'), 5, '0', STR_PAD_LEFT);
            $name = $get('Name of bank/institution');

            if (preg_match('/^\d{5}$/', $iid) !== 1 || $name === '' || isset($banks[$iid])) {
                continue;
            }

            $banks[$iid] = (new BankDetails(
                $name,
                $get('BIC') ?: null,
                $get('Town Name') ?: null,
                $this->join($get('Street Name'), $get('Building Number')),
                $get('Post Code') ?: null,
            ))->toEntry();
        }

        ksort($banks, SORT_STRING);

        return $banks;
    }
}
