<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Contracts;

/**
 * A bank source of one country that `filament-iban:update-banks` can refresh.
 */
interface DownloadsBanks
{
    /**
     * ISO 3166-1 alpha-2 code of the country.
     */
    public function country(): string;

    /**
     * A download with fewer entries is treated as broken and never replaces the current file.
     */
    public function minimumBanks(): int;

    /**
     * Where the data comes from (shown in the generated file and in the command output).
     */
    public function source(): string;

    /**
     * Downloads the register and returns bank code => entry, sorted by code. An entry is the bank name or, when the
     * register has more, an array with the keys name (required), bic, city, address, postcode and status (null/absent =
     * active); see {@see \Asignua\FilamentIban\Support\BankDetails::fromEntry()}.
     *
     * @return array<string, array<string, string>|string>
     */
    public function download(): array;
}
