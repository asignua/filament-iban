# Filament IBAN

[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://stand-with-ukraine.pp.ua)

<img class="filament-hidden" src="https://raw.githubusercontent.com/asignua/filament-iban/v1.1.0/art/cover.jpg" alt="Filament IBAN">

[![Tests](https://img.shields.io/github/actions/workflow/status/asignua/filament-iban/tests.yml?branch=main&label=tests)](https://github.com/asignua/filament-iban/actions/workflows/tests.yml)

An IBAN field for Filament 5 that behaves: the user sees and edits the number in groups of four, the database gets the
compact form, and the value is validated against the SWIFT IBAN registry (country, length, account structure, mod 97)
for every IBAN country. Optionally it shows the bank name, BIC and city for Ukrainian and Polish accounts (and, once downloaded, German, Austrian,
Swiss, Liechtenstein and Czech ones), with a warning when the bank is in liquidation. Also ships the validation rule,
an infolist entry, a table column and a value helper. No dependencies besides Filament, light and dark mode friendly.

## Screenshots

![A table with IbanColumn: grouped numbers with bank, BIC and city](https://raw.githubusercontent.com/asignua/filament-iban/v1.1.0/art/list.jpg)

The field groups the number as you type and shows the bank, BIC and city underneath - with a warning when the bank is in liquidation:

![IbanInput with bank details and a liquidation warning under the field](https://raw.githubusercontent.com/asignua/filament-iban/v1.1.0/art/form.jpg)

Every IBAN country is checked for length, account structure and check digits:

![A validation error for a wrong IBAN](https://raw.githubusercontent.com/asignua/filament-iban/v1.1.0/art/validation.jpg)

![IbanEntry on a view page](https://raw.githubusercontent.com/asignua/filament-iban/v1.1.0/art/view.jpg)

Dark mode:

![Dark mode](https://raw.githubusercontent.com/asignua/filament-iban/v1.1.0/art/list-dark.jpg)

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
- A helper text you set with `->helperText()` is shown together with the bank name or details (`Your hint — Bank name`).
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
Iban::problem($iban);                                     // null | empty | format | unknown_country | length | structure | checksum ('empty': nothing left after removing separators)
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
IbanColumn::make('iban')->showBankDetails();
```

Both show the grouped number in a monospaced font and are copyable; copying puts the compact form on the clipboard. The bank name or details are rendered through `belowContent()` / `description()`, so setting your own `->belowContent()` / `->description()` replaces them.

### Bank details

`showBankName()` shows the name only. `showBankDetails()` (on `IbanInput`, `IbanEntry` and `IbanColumn`, also with a closure) shows
`Name · BIC · City` and, when the bank is being liquidated, a danger-coloured status after it ("In liquidation"):

```php
IbanInput::make('iban')->countries(['UA', 'PL'])->showBankDetails();
```

It replaces the plain name where both are on; the config key `show_bank_details` turns it on everywhere. Which parts appear depends
on the register: Ukraine has the town, address and status but **no BIC**; Poland has the BIC and the town of the branch; Germany,
Austria, Switzerland and Liechtenstein have BIC, town, address and postcode; Czechia has the BIC. Everything shown comes from
external registers and is escaped. The BIC is not derivable from an IBAN, so there is no `Iban::bic()`; read it from the directory:

```php
use Asignua\FilamentIban\Support\BankDirectories;

$details = app(BankDirectories::class)->details($iban); // ?BankDetails
$details?->name;      // 'АТ КБ "ПриватБанк"'
$details?->bic;       // 'BPKOPLPWXXX' (null when the register has none)
$details?->city;      // 'Київ'
$details?->address;   // 'вул. Грушевського, 1Д'
$details?->postcode;  // '01001'
$details?->status;    // null = active, 'liquidation' | 'closed' | 'suspended' or the register's own text
$details?->isActive();
```

### Bank names

```php
use Asignua\FilamentIban\Support\BankDirectories;

app(BankDirectories::class)->bankName($iban); // 'АТ "Укрексімбанк"', null when unknown
```

Shipped with data: **Ukraine** (the bank identifier is the MFO, IBAN positions 5-10) and **Poland**. Download-only (see below): **Germany**, **Austria**,
**Switzerland**, **Liechtenstein** and **Czechia**. A Polish bank number (8 digits from
position 5) is looked up in full first, because after mergers a number can belong to another bank than the code it starts with
(144xxxxx is PKO BP, 150xxxxx is Erste, 106xxxxx is Alior); unlisted numbers fall back to the 5, 4 and 3 digit bank codes
(cooperative banks have 4, a few institutions 5). Add your own country:

```php
use Asignua\FilamentIban\Contracts\BankDirectory;

class FrenchBanks implements BankDirectory
{
    public function bankName(string $iban): ?string
    {
        return ['30004' => 'BNP Paribas'][substr($iban, 4, 5)] ?? null;
    }
}

app(BankDirectories::class)->register('FR', FrenchBanks::class); // in a service provider's boot()
```

A directory that also implements `Contracts\ResolvesBankDetails` (`bankDetails(string $iban): ?BankDetails`) feeds `showBankDetails()`; one that
implements only `BankDirectory` keeps working and shows just the name. The data files of the built-in directories accept two entry
shapes in the same file: a plain string (the name only, as before) or an array
`['name' => …, 'bic' => …, 'city' => …, 'address' => …, 'postcode' => …, 'status' => …]` (only `name` is required). A downloader
(`Contracts\DownloadsBanks`) may return either per code.

### Keeping the bank registers current

The package ships the registers as they were on the day of the release. Refresh them any time:

```bash
php artisan filament-iban:update-banks        # UA, PL and every country you have already downloaded
php artisan filament-iban:update-banks UA
php artisan filament-iban:update-banks DE     # download-only countries: DE, AT, CH, LI, CZ
php artisan filament-iban:update-banks PL --path=/some/dir
```

**DE, AT, CH, LI and CZ are not shipped**: the licences of their registers are unclear for redistribution, so the package contains no data for them
and a German, Austrian, Swiss, Liechtenstein or Czech IBAN shows no bank until you run `filament-iban:update-banks <country>` yourself (check
the terms of the source first, see the table below). After that the bare command keeps them fresh. CH and LI come from the same SIX file but have
a data file each, so update both (`CH`, `LI`).

The files are PHP arrays, written to `filament-iban.banks_path` (default `storage/app/filament-iban/banks`) through a temp file and
`rename()` (opcache is invalidated for the CLI process only), and read before the shipped ones. A download below a sanity floor (50 UA, 200 PL, 20 CZ,
300 AT, 500 CH/LI, 2000 DE entries) or an HTTP failure leaves the current file alone; an override that cannot be loaded is ignored in favour of the shipped data. Only
write to a directory your app alone can write to - the file is executed by PHP.
`--path=<dir>` is an **export** (used to regenerate the data shipped with the package: `php vendor/bin/testbench filament-iban:update-banks --path=resources/data/banks`); the app reads only `banks_path`.
Schedule it if you care: `Schedule::command('filament-iban:update-banks')->monthly();`.
The command cannot reach other processes: PHP-FPM with `opcache.validate_timestamps=0`, Octane and queue workers keep the
previous data (opcache, and the in-memory directory of a long-lived worker) until they are reloaded or restarted after an update.

## Configuration

```bash
php artisan vendor:publish --tag=filament-iban-config
```

| Key | Default | |
| --- | --- | --- |
| `banks_path` | `null` (`storage/app/filament-iban/banks`, env `FILAMENT_IBAN_BANKS_PATH`) | where `update-banks` writes and where data files are read first |
| `countries` | `null` | default country restriction of every `IbanInput` |
| `show_bank_name` | `false` | default of `showBankName()` for field, entry and column |
| `show_bank_details` | `false` | default of `showBankDetails()` for field, entry and column (wins over `show_bank_name`) |

## Gotchas

- **Store the compact form.** The grouping is only a mask in the browser. The state is compact after `getState()` and in the database; a stored compact value is regrouped when the form is filled. Do not compare or search with the grouped string.
- **Empty is valid.** The rule skips empty values like every Laravel rule; use `->required()`.
- **Bank name and details appear on blur**, not on every key stroke: they are resolved on the server (live on blur, enabled by `showBankName()` / `showBankDetails()` or the config default; a `->live()` you call later wins). They stay hidden for an invalid IBAN or an unknown bank.
- **Details depend on the register.** Ukraine has no BIC; a Polish bank code (as opposed to a full settlement number) has a BIC but no town; Czechia has only the BIC. A missing part is simply left out of the line.
- **The Ukrainian status is the head office's.** A branch MFO shows the status of its bank, as it shows its name. Only "In liquidation" and "Activity suspended" exist in the NBU data today.
- **Warnings are only as fresh as the data.** A bank put into liquidation after the release still looks active until you refresh the register.
- **Check digits do not prove the account exists**, only that the number is well-formed.
- **Registry data ages.** New IBAN countries are added to the SWIFT registry from time to time (`resources/data/countries.php`, 88 countries); an IBAN of a country missing there is reported as an unknown country.
- **Ukrainian IBANs of branches and closed banks.** The NBU register contains every MFO, branches resolved to the head bank, liquidated banks included. Some MFOs of old accounts (for example Raiffeisen Bank Aval's former 380805) are no longer listed and show no name.

## Data sources and licences

| Country | Source | Licence |
| --- | --- | --- |
| UA | National Bank of Ukraine open data, `https://bank.gov.ua/NBU_BankInfo/get_data_branch?json` (reference book of MFO) | NBU open data, free to use with attribution |
| PL | Narodowy Bank Polski, plan numeracji rachunków bankowych, `https://ewib.nbp.pl/plewibnra?dokNazwa=plewibnra.txt` | public register published by NBP |
| DE | Deutsche Bundesbank, Bankleitzahlendatei (fixed-width TXT, main entries only), linked from the Bundesbank download page | **not shipped** - check the Bundesbank's terms before use |
| AT | Oesterreichische Nationalbank, SEPA-Verzeichnis, `https://www.oenb.at/docroot/downloads_observ/sepa-zv-vz_gesamt.csv` | **not shipped** - the file comes with OeNB copyright and liability terms |
| CH, LI | SIX Interbank Clearing, Bank Master, `https://api.six-group.com/api/epcd/bankmaster/v3/bankmaster_V3.csv` | **not shipped** - check the SIX terms before use |
| CZ | Česká národní banka, kódy bank, `https://www.cnb.cz/cs/platebni-styk/.galleries/ucty_kody_bank/download/kody_bank_CR.csv` | **not shipped** - check the CNB terms before use |

The UA and PL files were downloaded with `filament-iban:update-banks` and reduced to code/number, name, BIC (PL), town, address and postcode (UA) and status (UA): for UA, branch MFOs carry the details of the head bank; for PL, every settlement number (3.7k) and bank code is kept, with the BIC and branch town but without the street address (it would quadruple the file). The
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
