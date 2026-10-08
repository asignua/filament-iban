# Changelog

All notable changes to `asignua/filament-iban` are documented here.

## v1.0.2 - 2026-10-08

- Docs: screenshots and a cover in `art/`, wired into the README; a workbench demo (Acme Supply data, `DemoSeeder`) to reproduce them.
- Fix: `IbanInput` keeps an acronym label as written in validation messages ("The IBAN must..." instead of "The iBAN must..."); Filament's `lcfirst` still applies to other labels and an explicit `->validationAttribute()` wins.
- `IbanInput` shows the label "IBAN" by default for a field named `iban` (Filament would print "Iban").

## v1.0.1 - 2026-10-08

- Fix: `IbanInput` inside a Repeater/Builder - the rule, placeholder and helper text closures no longer capture the template component, so the bank name shows in every item and `$get`-based closures (`countries()`, `showBankName()`, `helperText()`) see the item.
- Fix: `->maxLength()` / `->length()` no longer block typing a full IBAN: the HTML `maxlength` accounts for the group separators, `minlength` is dropped (validation still uses the compact value).
- Fix: the field is made live (on blur) for bank names without overriding a parent container's `live()` when bank names are off.
- Docs: `Iban::problem()` can return `empty`; the update command's opcache invalidation covers the CLI only.

## v1.0.0

- `IbanInput` (Filament `TextInput`): grouped by four while typing (visual only, caret-friendly), compact upper-case value on save, built-in IBAN rule, `->countries()`, `->showBankName()`, example placeholder for a single allowed country.
- `Rules\Iban`: country, length and BBAN structure of the SWIFT IBAN registry for 88 countries, mod 97 check digits, optional country restriction, translated messages.
- `Support\Iban` helper: `branchCode()`, `normalize()`, `format()`, `isValid()`, `problem()`, `country()`, `bankCode()`, `example()`.
- Bank names: `Contracts\BankDirectory`, `Support\BankDirectories` registry, built-in directories for Ukraine (NBU MFO) and Poland (NBP, full 8-digit settlement numbers first, then 5/4/3-digit bank codes) with the real registers shipped (146 and 3730 entries).
- `filament-iban:update-banks {country?} {--path=}` refreshes the registers into `filament-iban.banks_path` (atomic write, sanity floor); files there win over the shipped ones. `--path` is export-only.
- `IbanEntry` (infolist) and `IbanColumn` (table): formatted, monospaced, copyable (compact value), optional bank name.
- `config/filament-iban.php`, translations in de, en, es, fr, it, nl, pl, pt_BR, tr, uk, Laravel Boost guideline.
