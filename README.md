# Filament IBAN

[![Tests](https://img.shields.io/github/actions/workflow/status/asignua/filament-iban/tests.yml?branch=main&label=tests)](https://github.com/asignua/filament-iban/actions/workflows/tests.yml)

An IBAN field for Filament 5 that behaves: the user sees and edits the number in groups of four, the database gets the
compact form, and the value is validated against the SWIFT IBAN registry (country, length, account structure, mod 97)
for every IBAN country. Optionally it shows the bank name for Ukrainian and Polish accounts. Also ships the validation rule,
an infolist entry, a table column and a value helper. No dependencies besides Filament, light and dark mode friendly.

## Screenshots

Screenshots are not added yet (`art/`).

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament 5

## Installation

```bash
composer require asignua/filament-iban
```

Registering the plugin on a panel is optional - every class works without it:

```php
use Asignua\FilamentIban\IbanPlugin;

$panel->plugin(IbanPlugin::make());
```

## Usage

### Form field

```php
use Asignua\FilamentIban\Forms\Components\IbanInput;

IbanInput::make('iban')
    ->countries(['UA', 'PL'])   // optional: accept only these countries (ISO 3166-1 alpha-2)
    ->showBankName();           // optional: bank name under the field once it loses focus
```

- Typing, pasting and editing show `UA21 3223 1300 0002 6007 2335 6600 1`; the caret stays where you are.
- The saved value is `UA213223130000026007233566001`.
- The IBAN rule is built in. With exactly one allowed country the placeholder is a valid example of that country.
- Separators are ignored on input and on the server: spaces, hyphens, dots, slashes, non-breaking and zero-width spaces, soft hyphens and BOM.
- Validation (`->unique()`, `->maxLength()` and the rest) sees the **compact** value, not the grouped text.
- Backspace / Delete next to a group separator removes the neighbouring character too; the mask pauses during IME composition.
- A helper text you set with `->helperText()` is shown together with the bank name (`Your hint — Bank name`).
- It is a `TextInput`, so `->required()`, `->label()`, `->helperText()`, `->live()` and the rest work as usual.

### Validation rule

```php
use Asignua\FilamentIban\Rules\Iban;

$request->validate([
    'iban' => ['required', new Iban],
    'payout_iban' => ['nullable', Iban::make()->countries(['UA', 'PL'])],
]);
```

Spaces and case are ignored. Empty values pass - add `required` yourself.

### Helper

```php
use Asignua\FilamentIban\Support\Iban;

Iban::normalize('ua21 3223 1300 0002 6007 2335 6600 1'); // UA213223130000026007233566001
Iban::format('UA213223130000026007233566001');            // UA21 3223 1300 0002 6007 2335 6600 1
Iban::isValid($iban);                                     // bool
Iban::problem($iban);                                     // null | format | unknown_country | length | structure | checksum
Iban::country($iban);                                     // 'UA'
Iban::bankCode($iban);                                    // the SWIFT bank identifier: '322313' (the MFO) for UA, '10901014' for PL, 'WEST' for GB
Iban::branchCode($iban);                                  // branch identifier where the registry has one ('123456' sort code for GB), else null
Iban::example('PL');                                      // a valid example IBAN
```

### Infolist entry and table column

```php
use Asignua\FilamentIban\Infolists\Components\IbanEntry;
use Asignua\FilamentIban\Tables\Columns\IbanColumn;

IbanEntry::make('iban')->showBankName();
IbanColumn::make('iban')->showBankName();
```

Both show the grouped number in a monospaced font and are copyable; copying puts the compact form on the clipboard. The bank name is rendered through `belowContent()` / `description()`, so setting your own `->belowContent()` / `->description()` replaces it.

### Bank names

```php
use Asignua\FilamentIban\Support\BankDirectories;

app(BankDirectories::class)->bankName($iban); // 'АТ "Укрексімбанк"', null when unknown
```

Built in: **Ukraine** (the bank identifier is the MFO, IBAN positions 5-10) and **Poland**. A Polish bank number (8 digits from
position 5) is looked up in full first, because after mergers a number can belong to another bank than the code it starts with
(144xxxxx is PKO BP, 150xxxxx is Erste, 106xxxxx is Alior); unlisted numbers fall back to the 5, 4 and 3 digit bank codes
(cooperative banks have 4, a few institutions 5). Add your own country:

```php
use Asignua\FilamentIban\Contracts\BankDirectory;

class GermanBanks implements BankDirectory
{
    public function bankName(string $iban): ?string
    {
        return ['37040044' => 'Commerzbank'][substr($iban, 4, 8)] ?? null;
    }
}

app(BankDirectories::class)->register('DE', GermanBanks::class); // in a service provider's boot()
```

### Keeping the bank registers current

The package ships the registers as they were on the day of the release. Refresh them any time:

```bash
php artisan filament-iban:update-banks        # UA and PL
php artisan filament-iban:update-banks UA
php artisan filament-iban:update-banks PL --path=/some/dir
```

The files are PHP arrays, written to `filament-iban.banks_path` (default `storage/app/filament-iban/banks`) through a temp file and
`rename()` (opcache is invalidated), and read before the shipped ones. A download below a sanity floor (50 UA / 200 PL entries)
or an HTTP failure leaves the current file alone; an override that cannot be loaded is ignored in favour of the shipped data. Only
write to a directory your app alone can write to - the file is executed by PHP.
`--path=<dir>` is an **export** (used to regenerate the data shipped with the package); the app reads only `banks_path`.
Schedule it if you care: `Schedule::command('filament-iban:update-banks')->monthly();`.

## Configuration

```bash
php artisan vendor:publish --tag=filament-iban-config
```

| Key | Default | |
| --- | --- | --- |
| `banks_path` | `null` (`storage/app/filament-iban/banks`, env `FILAMENT_IBAN_BANKS_PATH`) | where `update-banks` writes and where data files are read first |
| `countries` | `null` | default country restriction of every `IbanInput` |
| `show_bank_name` | `false` | default of `showBankName()` for field, entry and column |

## Gotchas

- **Store the compact form.** The grouping is only a mask in the browser. The state is compact after `getState()` and in the database; a stored compact value is regrouped when the form is filled. Do not compare or search with the grouped string.
- **Empty is valid.** The rule skips empty values like every Laravel rule; use `->required()`.
- **Bank name appears on blur**, not on every key stroke: it is resolved on the server (`live(onBlur: true)`, enabled by `showBankName()` or the config default; a `->live()` you call later wins). It stays hidden for an invalid IBAN or an unknown bank.
- **Check digits do not prove the account exists**, only that the number is well-formed.
- **Registry data ages.** New IBAN countries are added to the SWIFT registry from time to time (`resources/data/countries.php`, 88 countries); an IBAN of a country missing there is reported as an unknown country.
- **Ukrainian IBANs of branches and closed banks.** The NBU register contains every MFO, branches resolved to the head bank, liquidated banks included. Some MFOs of old accounts (for example Raiffeisen Bank Aval's former 380805) are no longer listed and show no name.

## Data sources and licences

| Country | Source | Licence |
| --- | --- | --- |
| UA | National Bank of Ukraine open data, `https://bank.gov.ua/NBU_BankInfo/get_data_branch?json` (reference book of MFO) | NBU open data, free to use with attribution |
| PL | Narodowy Bank Polski, plan numeracji rachunków bankowych, `https://ewib.nbp.pl/plewibnra?dokNazwa=plewibnra.txt` | public register published by NBP |

Both files were downloaded with `filament-iban:update-banks` and reduced to code/number and name: for UA, branch MFOs carry the name of the head bank; for PL, every settlement number (3.1k) and bank code is kept. The
IBAN country structures follow the SWIFT IBAN registry; examples that are not published ones are synthetic (valid check digits,
made-up accounts). The code is MIT; check the terms of the registers before redistributing the data files beyond this package.

## Translations

The interface ships in English, Ukrainian, German, Spanish, French, Italian, Dutch, Polish, Brazilian Portuguese and Turkish
under the `filament-iban::filament-iban` namespace. A test keeps every language in step with the English keys. Override a
string by publishing the translations (`--tag=filament-iban-translations`) and editing the copy in
`lang/vendor/filament-iban`.

## AI agents

The package ships [Laravel Boost](https://laravel.com/docs/boost) guidelines (`resources/boost/guidelines/core.blade.php`) so a
coding agent wires it up correctly.

## Testing

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
