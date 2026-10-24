<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Contracts;

use Asignua\FilamentIban\Support\BankDetails;

/**
 * A {@see BankDirectory} that knows more than the name (BIC, city, status). Optional: a directory implementing only
 * `BankDirectory` keeps working and yields details with just the name.
 */
interface ResolvesBankDetails
{
    /**
     * @param string $iban compact, upper case, already validated for the directory's country
     */
    public function bankDetails(string $iban): ?BankDetails;
}
