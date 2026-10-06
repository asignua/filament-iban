<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Contracts;

/**
 * Resolves the name of the bank an IBAN belongs to. Register an implementation for a country with
 * {@see \Asignua\FilamentIban\Support\BankDirectories::register()}.
 */
interface BankDirectory
{
    /**
     * @param string $iban compact, upper case, already validated for the directory's country
     */
    public function bankName(string $iban): ?string;
}
