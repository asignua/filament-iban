# Changelog

All notable changes to `asignua/filament-iban` are documented here.

## Unreleased

## v1.0.0

- `IbanInput` (Filament `TextInput`): grouped by four while typing (visual only, caret-friendly), compact upper-case value on save, built-in IBAN rule, `->countries()`, `->showBankName()`, example placeholder for a single allowed country.
- `Rules\Iban`: country, length and BBAN structure of the SWIFT IBAN registry for 88 countries, mod 97 check digits, optional country restriction, translated messages.
- `Support\Iban` helper: `branchCode()`, `normalize()`, `format()`, `isValid()`, `problem()`, `country()`, `bankCode()`, `example()`.
- Bank names: `Contracts\BankDirectory`, `Support\BankDirectories` registry, built-in directories for Ukraine (NBU MFO) and Poland (NBP, full 8-digit settlement numbers first, then 5/4/3-digit bank codes) with the real registers shipped (146 and 3730 entries).
- `filament-iban:update-banks {country?} {--path=}` refreshes the registers into `filament-iban.banks_path` (atomic write, sanity floor); files there win over the shipped ones. `--path` is export-only.
- `IbanEntry` (infolist) and `IbanColumn` (table): formatted, monospaced, copyable (compact value), optional bank name.
- `config/filament-iban.php`, translations in de, en, es, fr, it, nl, pl, pt_BR, tr, uk, Laravel Boost guideline.
