<?php

declare(strict_types=1);

return [

    /*
    | Directory with bank data files (`ua.php`, `pl.php`) written by `php artisan filament-iban:update-banks`.
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

];
