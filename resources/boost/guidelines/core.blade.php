## Filament IBAN (asignua/filament-iban)

- Optional on the panel: `->plugin(Asignua\FilamentIban\IbanPlugin::make())`. The classes work without it.
- Form field: `Asignua\FilamentIban\Forms\Components\IbanInput::make('iban')->countries(['UA', 'PL'])->showBankName()`. It validates itself (SWIFT registry + mod 97) and **dehydrates the compact form** (upper case, no spaces). The grouping by four is only visual - never store or compare the grouped string.
- Outside Filament: `new Asignua\FilamentIban\Rules\Iban` (or `Iban::make()->countries([...])`), `Asignua\FilamentIban\Support\Iban::{normalize,format,isValid,country,bankCode}`.
- Display: `Infolists\Components\IbanEntry::make('iban')`, `Tables\Columns\IbanColumn::make('iban')`, both with `->showBankName()`.
- `->showBankDetails()` (same three classes, config `show_bank_details`) shows "Name · BIC · City" plus a danger-coloured warning for a bank in liquidation; `app(BankDirectories::class)->details($iban)` returns a `Support\BankDetails` (name, bic, city, address, postcode, status; null status = active). The BIC cannot be derived from an IBAN, so there is no `Iban::bic()`. A custom directory adds details by also implementing `Contracts\ResolvesBankDetails`; data-file entries may be a plain name string or an array with those keys.
- DE, AT, CH, LI and CZ are **download-only**: no bank is shown until `php artisan filament-iban:update-banks DE` (etc.) has been run; the package does not ship their data.
- Bank names ship for UA (MFO) and PL; add a country with `app(BankDirectories::class)->register('DE', MyDirectory::class)` (`Contracts\BankDirectory`). Refresh the registers with `php artisan filament-iban:update-banks` (writes into `filament-iban.banks_path`, which wins over the shipped data).
- The rule skips empty values: add `required` when the IBAN is mandatory.
