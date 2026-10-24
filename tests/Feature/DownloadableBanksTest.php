<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Feature;

use Asignua\FilamentIban\Support\BankDirectories;
use Asignua\FilamentIban\Support\Banks\AustriaBanks;
use Asignua\FilamentIban\Support\Banks\CzechBanks;
use Asignua\FilamentIban\Support\Banks\GermanyBanks;
use Asignua\FilamentIban\Support\Banks\SwitzerlandBanks;
use Asignua\FilamentIban\Tests\Concerns\BuildsIbans;
use Asignua\FilamentIban\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * The download-only registers, parsed from small fixtures in the real formats (verified against the live files).
 */
class DownloadableBanksTest extends TestCase
{
    use BuildsIbans;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/filament-iban-'.uniqid();
        config()->set('filament-iban.banks_path', $this->dir);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /**
     * One 168-character line of the Bundesbank file.
     */
    private function blzLine(string $blz, string $merkmal, string $name, string $plz, string $city, string $bic): string
    {
        return $blz.$merkmal
            .mb_str_pad($name, 58).mb_str_pad($plz, 5).mb_str_pad($city, 35).mb_str_pad($name, 27)
            .'20100'.mb_str_pad($bic, 11).'09'.'011380'.'U'.'0'.'00000000';
    }

    private function germany(int $filler = 0): string
    {
        $lines = [
            $this->blzLine('10000000', '1', 'Bundesbank', '10591', 'Berlin', 'MARKDEF1100'),
            $this->blzLine('10000000', '2', 'Bundesbank Zweitstelle', '10591', 'Berlin', 'MARKDEF1100'),
            $this->blzLine('37040044', '1', 'Commerzbank <b>Köln</b>', '50667', 'Köln', 'COBADEFFXXX'),
            $this->blzLine('37040044', '2', 'Commerzbank Filiale', '50667', 'Köln', 'COBADEFFXXX'),
            $this->blzLine('50050201', '1', 'Frankfurter Sparkasse', '60311', 'Frankfurt am Main', ''),
        ];

        for ($i = 0; $i < $filler; $i++) {
            $lines[] = $this->blzLine((string) (70000000 + $i), '1', 'Filler '.$i, '80331', 'München', 'FILLDEMMXXX');
        }

        return mb_convert_encoding(implode("\r\n", $lines), 'ISO-8859-1', 'UTF-8')."\r\n";
    }

    private function austria(): string
    {
        $header = 'Kennzeichen;Identnummer;Bankleitzahl;Institutsart;Sektor;Firmenbuchnummer;Bankenname;Straße;PLZ;Ort;Postadresse / Straße;Postadresse / PLZ;Postadresse / Ort;Postfach;Bundesland;Telefon;Fax;E-Mail;SWIFT-Code;Homepage;Gründungsdatum;LEI';

        return mb_convert_encoding(implode("\r\n", [
            'Es wird darauf hingewiesen, dass die Daten täglich aktualisiert werden.',
            '',
            'SEPA-Verzeichnis-Abfrage vom 23.10.2026',
            $header,
            'Hauptanstalt;388815;100;KI;OeNB;nullnull;Oesterreichische Nationalbank;Otto-Wagner-Platz 3;1090;Wien;;;;;Wien;01/40420;;;NABAATWWXXX;;;5299001Z8JOKGWV5O949',
            ';3404218;11000;;Aktienbanken;150714p;UniCredit Bank Austria AG;Rothschildplatz 1;1020;Wien;;1011;Wien;35;Wien;050505;;a@b.at;BKAUATWWXXX;www.bankaustria.at;18.09.1996;D1HEB8VEU6D9M8ZUXG17',
            ';3404218;11000;;Aktienbanken;150714p;UniCredit Duplicate;Elsewhere 1;1020;Wien;;;;;Wien;050505;;;BKAUATWWXXX;;;',
            'Filiale;99;19043;KI;Sparkassen;1p;Erste Bank der österreichischen Sparkassen AG;Am Belvedere 1;1100;Wien;;;;;Wien;050100;;;GIBAATWWXXX;;;',
            ';77;20111;;Sparkassen;2p;No BIC Bank;Hauptplatz 1;8010;Graz;;;;;Steiermark;1;;;;;;',
        ]), 'ISO-8859-1', 'UTF-8')."\r\n";
    }

    private function six(): string
    {
        $header = 'IID/QR-IID;Valid on;Concatenation;New IID/QR-IID;SIC IID;Headquarters;IID type;QR-IID allocation;Name of bank/institution;Street Name;Building Number;Post Code;Town Name;Country;BIC;SIC participation;RTGS customer payments, CHF;IP customer payments, CHF;euroSIC participation;LSV+/BDD, CHF;LSV+/BDD, EUR;202610241630000';

        return "\u{FEFF}".implode("\r\n", [
            $header,
            '100;2026-10-26;N;;001008;100;1;;Schweizerische Nationalbank;Börsenstrasse;15;8022;Zürich;CH;SNBZCHZZXXX;Y;Y;Y;Y;Y;N',
            '762;2026-10-26;N;;007620;762;1;;"Credit Suisse ""Bank"" (Schweiz) AG";Paradeplatz;8;8001;Zürich;CH;CRESCHZZ80A;Y;Y;Y;Y;Y;N',
            '8800;2026-10-26;N;;088000;8800;1;;Liechtensteinische Landesbank AG;Städtle;44;9490;Vaduz;LI;LLBLLI22XXX;Y;Y;Y;Y;Y;N',
            '30111;2026-10-26;N;;301115;30111;4;83047;Amnis Europe AG;Gewerbeweg;15;9490;Vaduz;LI;;Y;Y;Y;N;N;N',
        ])."\r\n";
    }

    private function czech(): string
    {
        return "\u{FEFF}".implode("\r\n", [
            'Kód platebního styku;Poskytovatel platebních služeb;BIC kód (SWIFT);Systém CERTIS',
            '0100;Komerční banka, a.s.;KOMBCZPP;A',
            '0300;Československá obchodní banka, a. s.;CEKOCZPP;A',
            '8610;Devizová burza a.s.;;A',
        ])."\r\n";
    }

    public function test_germany_keeps_main_entries_with_bic_town_and_postcode(): void
    {
        $banks = (new GermanyBanks)->parse($this->utf8($this->germany()));

        $this->assertCount(3, $banks);
        $this->assertSame(['name' => 'Bundesbank', 'bic' => 'MARKDEF1100', 'city' => 'Berlin', 'postcode' => '10591'], $banks['10000000']);
        $this->assertSame('Commerzbank <b>Köln</b>', $banks['37040044']['name']);
        $this->assertSame(['name' => 'Frankfurter Sparkasse', 'city' => 'Frankfurt am Main', 'postcode' => '60311'], $banks['50050201']);
    }

    public function test_germany_resolves_the_download_url_from_the_page(): void
    {
        $page = '<a href="/resource/blob/602632/abc/DEF/blz-aktuell-txt-data.txt">txt</a>';
        Http::fake([
            GermanyBanks::PAGE => Http::response($page),
            'https://www.bundesbank.de/resource/blob/602632/abc/DEF/blz-aktuell-txt-data.txt' => Http::response($this->germany(2100)),
        ]);

        $this->artisan('filament-iban:update-banks', ['country' => 'DE'])->assertSuccessful();

        $iban = $this->withCheckDigits('DE', '37040044'.'0532013000');
        $details = app(BankDirectories::class)->details($iban);

        $this->assertSame('COBADEFFXXX', $details?->bic);
        $this->assertSame('Köln', $details->city);
        $this->assertFileExists($this->dir.'/de.php');
    }

    public function test_germany_fails_loudly_when_the_page_has_no_link(): void
    {
        Http::fake([GermanyBanks::PAGE => Http::response('<html>moved</html>')]);

        $this->artisan('filament-iban:update-banks', ['country' => 'DE'])->assertFailed();

        $this->assertFileDoesNotExist($this->dir.'/de.php');
    }

    public function test_austria_reads_the_csv_below_the_notice_and_keeps_the_first_row_per_bank_code(): void
    {
        $banks = (new AustriaBanks)->parse($this->utf8($this->austria()));

        $this->assertCount(4, $banks);
        $this->assertSame(['name' => 'Oesterreichische Nationalbank', 'bic' => 'NABAATWWXXX', 'city' => 'Wien', 'address' => 'Otto-Wagner-Platz 3', 'postcode' => '1090'], $banks['00100']);
        $this->assertSame('UniCredit Bank Austria AG', $banks['11000']['name']);
        $this->assertSame('Erste Bank der österreichischen Sparkassen AG', $banks['19043']['name']);
        $this->assertArrayNotHasKey('bic', $banks['20111']);
    }

    public function test_switzerland_and_liechtenstein_share_one_file_keyed_by_the_five_digit_iid(): void
    {
        $ch = new SwitzerlandBanks;
        $banks = $ch->parse($this->utf8($this->six()));

        $this->assertSame('CH', $ch->country());
        $this->assertSame('LI', (new SwitzerlandBanks('LI'))->country());
        $this->assertCount(4, $banks);
        $this->assertSame(['name' => 'Schweizerische Nationalbank', 'bic' => 'SNBZCHZZXXX', 'city' => 'Zürich', 'address' => 'Börsenstrasse 15', 'postcode' => '8022'], $banks['00100']);
        $this->assertSame('Credit Suisse "Bank" (Schweiz) AG', $banks['00762']['name']);
        $this->assertSame('LLBLLI22XXX', $banks['08800']['bic']);
        $this->assertArrayNotHasKey('bic', $banks['30111']);
    }

    public function test_the_swiss_bank_is_found_by_iban_for_both_countries(): void
    {
        File::ensureDirectoryExists($this->dir);
        $entry = "<?php return ['banks' => ['00762' => ['name' => 'CS', 'bic' => 'CRESCHZZ80A'], '08800' => ['name' => 'LLB', 'city' => 'Vaduz']]];";
        File::put($this->dir.'/ch.php', $entry);
        File::put($this->dir.'/li.php', $entry);
        $banks = app(BankDirectories::class);

        $this->assertSame('CRESCHZZ80A', $banks->details($this->withCheckDigits('CH', '00762'.'011623852957'))?->bic);
        $this->assertSame('Vaduz', $banks->details($this->withCheckDigits('LI', '08800'.'000000000000'))?->city);
    }

    public function test_the_czech_register_has_a_bom_and_a_header_row(): void
    {
        $banks = (new CzechBanks)->parse($this->utf8($this->czech()));

        $this->assertSame(['0100' => ['name' => 'Komerční banka, a.s.', 'bic' => 'KOMBCZPP'], '0300' => ['name' => 'Československá obchodní banka, a. s.', 'bic' => 'CEKOCZPP'], '8610' => 'Devizová burza a.s.'], $banks);
    }

    public function test_a_czech_register_in_a_legacy_encoding_is_converted(): void
    {
        $banks = (new CzechBanks)->parse($this->utf8(mb_convert_encoding($this->czech(), 'ISO-8859-2', 'UTF-8')));

        $this->assertSame('Komerční banka, a.s.', $banks['0100']['name']);
    }

    public function test_a_small_download_is_refused_by_the_floor_and_a_good_one_is_written_for_cz(): void
    {
        $rows = ['Kód;Poskytovatel;BIC;CERTIS'];

        for ($i = 0; $i < 25; $i++) {
            $rows[] = str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT).';Bank '.$i.';FILLCZPP;A';
        }

        Http::fakeSequence(CzechBanks::URL)->push($this->czech())->push(implode("\r\n", $rows));

        $this->artisan('filament-iban:update-banks', ['country' => 'CZ'])->assertFailed();
        $this->assertFileDoesNotExist($this->dir.'/cz.php');

        $this->artisan('filament-iban:update-banks', ['country' => 'CZ'])->assertSuccessful();

        $details = app(BankDirectories::class)->details($this->withCheckDigits('CZ', '1003'.'0000192000145399'));

        $this->assertSame('Bank 3', $details?->name);
        $this->assertSame('FILLCZPP', $details->bic);
    }

    public function test_an_already_downloaded_country_is_refreshed_by_the_bare_command(): void
    {
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/cz.php', "<?php return ['banks' => ['0100' => 'Old']];");
        $rows = ['Kód;Poskytovatel;BIC;CERTIS'];

        for ($i = 0; $i < 25; $i++) {
            $rows[] = str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT).';Bank '.$i.';FILLCZPP;A';
        }

        Http::fake([
            CzechBanks::URL => Http::response(implode("\r\n", $rows)),
            '*' => Http::response('', 500),
        ]);

        $this->artisan('filament-iban:update-banks', ['country' => 'CZ'])->assertSuccessful();
        // UA/PL (shipped) fail with 500 here; CZ is picked up because its file exists, AT is not requested at all.
        $this->artisan('filament-iban:update-banks')->assertFailed();

        Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), 'oenb.at'));
    }

    private function utf8(string $body): string
    {
        return mb_check_encoding($body, 'UTF-8') ? $body : mb_convert_encoding($body, 'UTF-8', 'ISO-8859-2');
    }
}
