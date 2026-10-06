# Changelog

All notable changes to `asignua/filament-iban` are documented here.

## Unreleased

## v1.0.0

- `IbanInput` (Filament `TextInput`): grouped by four while typing (visual only, caret-friendly), compact upper-case value on save, built-in IBAN rule, `->countries()`, `->showBankName()`, example placeholder for a single allowed country.
- `Rules\Iban`: country, length and BBAN structure of the SWIFT IBAN registry for 88 countries, mod 97 check digits, optional country restriction, translated messages.
- `Support\Iban` helper: `normalize()`, `format()`, `isValid()`, `problem()`, `country()`, `bankCode()`, `example()`.
- Bank names: `Contracts\BankDirectory`, `Support\BankDirectories` registry, built-in directories for Ukraine (NBU MFO) and Poland (NBP bank number, 3/4/5-digit codes) with the real registers shipped (146 and 583 entries).
- `filament-iban:update-banks {country?} {--path=}` refreshes the registers into `filament-iban.banks_path`; files there win over the shipped ones.
- `IbanEntry` (infolist) and `IbanColumn` (table): formatted, monospaced, copyable (compact value), optional bank name.
- `config/filament-iban.php`, translations in de, en, es, fr, it, nl, pl, pt_BR, tr, uk, Laravel Boost guideline.
