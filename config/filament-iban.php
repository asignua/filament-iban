<?php

declare(strict_types=1);

return [

    /*
    | Directory with bank data files (`ua.php`, `pl.php`, and `de.php`, `at.php`, `ch.php`, `li.php`, `cz.php` once downloaded) written by `php artisan filament-iban:update-banks`.
    | A file here wins over the data shipped with the package. null = storage/app/filament-iban/banks.
    */
    'banks_path' => env('FILAMENT_IBAN_BANKS_PATH'),

    /*
    | Default country restriction for every IbanInput (ISO 3166-1 alpha-2 codes). null = all IBAN countries.
    | ->countries([...]) on a field overrides it.
    */
    'countries' => null,

    /*
    | Show the bank name under every IbanInput / IbanEntry / IbanColumn by default.
    */
    'show_bank_name' => false,

    /*
    | Show the bank's name, BIC and city (and a warning when the bank is in liquidation) under every IbanInput /
    | IbanEntry / IbanColumn by default. Replaces the plain bank name where both are on.
    */
    'show_bank_details' => false,

];
