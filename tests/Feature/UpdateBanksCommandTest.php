<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Feature;

use Asignua\FilamentIban\Support\BankDirectories;
use Asignua\FilamentIban\Support\Banks\PolandBanks;
use Asignua\FilamentIban\Support\Banks\UkraineBanks;
use Asignua\FilamentIban\Tests\Concerns\BuildsIbans;
use Asignua\FilamentIban\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class UpdateBanksCommandTest extends TestCase
{
    use BuildsIbans;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/filament-iban-'.uniqid();
        config()->set('filament-iban.banks_path', $this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function nbu(): string
    {
        $filler = [];

        for ($i = 0; $i < 60; $i++) {
            $mfo = 400000 + $i;
            $filler[] = ['GLMFO' => $mfo, 'MFO' => $mfo, 'TYP' => 0, 'SHORTNAME' => 'Filler '.$i];
        }

        return (string) json_encode([
            ...$filler,
            ['GLMFO' => 305299, 'MFO' => 305299, 'TYP' => 0, 'SHORTNAME' => 'АТ КБ "ПриватБанк"', 'NP' => 'Київ', 'ADRESS' => 'вул. Грушевського, 1Д', 'P_IND' => '01001', 'KSTAN' => 1, 'N_STAN' => 'Нормальний'],
            ['GLMFO' => 305299, 'MFO' => 305299, 'TYP' => 2, 'SHORTNAME' => 'Відділення №1 АТ КБ "ПриватБанк"'],
            ['GLMFO' => 300335, 'MFO' => 300335, 'TYP' => 0, 'SHORTNAME' => 'АТ "Райффайзен Банк"', 'NP' => 'Київ', 'KSTAN' => 4, 'N_STAN' => 'Ліквідація'],
            ['GLMFO' => 300335, 'MFO' => 305653, 'TYP' => 1, 'SHORTNAME' => 'Дніпропетровська дирекція'],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function nbp(): string
    {
        $row = static fn (string $code, string $name): string => str_pad($code, 5)."\t".str_pad($name, 40)."\t\t".$code."\t".$code."00000\tCentrala";

        $rows = [];

        for ($i = 0; $i < 210; $i++) {
            $rows[] = $row((string) (300 + $i), 'Filler '.$i);
        }

        return iconv('UTF-8', 'CP852', implode("\r\n", [
            ...$rows,
            $row('102', 'Powszechna Kasa Oszczędności Bank Polski SA'),
            $row('102', 'Powszechna Kasa Oszczędności Bank Polski SA'),
            $row('249', 'Alior Bank Spółka Akcyjna'),
            "124  \t".str_pad('Bank Pekao SA', 40)."\t\t124\t12400001\tOddział\tO\tŁódź\tul. Piotrkowska 1\t90-001\tŁódź\t\t\t\t\t\t\t\t\tPKOPPLPW\tPKOPPLPW",
            'garbage line',
        ]))."\r\n";
    }

    public function test_it_downloads_and_writes_both_registers(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            UkraineBanks::URL => Http::response($this->nbu()),
            PolandBanks::URL => Http::response($this->nbp()),
        ]);

        // The download-only countries (DE, AT, CH, LI, CZ) are not touched without an argument or an existing file.
        $this->artisan('filament-iban:update-banks')->assertSuccessful();

        $ua = require $this->dir.'/ua.php';
        $pl = require $this->dir.'/pl.php';

        $this->assertSame(['name' => 'АТ "Райффайзен Банк"', 'city' => 'Київ', 'status' => 'liquidation'], $ua['banks']['300335']);
        $this->assertSame([
            'name' => 'АТ КБ "ПриватБанк"', 'city' => 'Київ', 'address' => 'вул. Грушевського, 1Д', 'postcode' => '01001',
        ], $ua['banks']['305299']);
        // a branch MFO carries the head office's details, status included
        $this->assertSame(['name' => 'АТ "Райффайзен Банк"', 'city' => 'Київ', 'status' => 'liquidation'], $ua['banks']['305653']);
        // a bank the register knows only by name stays a plain string
        $this->assertSame('Filler 0', $ua['banks']['400000']);
        $this->assertCount(63, $ua['banks']);
        $this->assertSame('Powszechna Kasa Oszczędności Bank Polski SA', $pl['banks']['102']);
        $this->assertSame('Powszechna Kasa Oszczędności Bank Polski SA', $pl['banks']['10200000']);
        $this->assertSame('Alior Bank Spółka Akcyjna', $pl['banks']['249']);
        $this->assertSame(['name' => 'Bank Pekao SA', 'bic' => 'PKOPPLPW', 'city' => 'Łódź'], $pl['banks']['12400001']);
        $this->assertSame(['name' => 'Bank Pekao SA', 'bic' => 'PKOPPLPW'], $pl['banks']['124']);
        $this->assertSame([], glob($this->dir.'/*.tmp'));
        $this->assertNotEmpty($ua['source']);
        $this->assertNotEmpty($ua['fetched_at']);
    }

    public function test_the_written_file_is_read_first(): void
    {
        Http::fake([UkraineBanks::URL => Http::response($this->nbu())]);

        $this->artisan('filament-iban:update-banks', ['country' => 'ua'])->assertSuccessful();

        $this->assertFileDoesNotExist($this->dir.'/pl.php');

        $iban = 'UA'.'00'.'305653'.'0000026007233566001';
        $directory = app(BankDirectories::class)->for('UA');

        $this->assertSame('АТ "Райффайзен Банк"', $directory?->bankName($iban));
    }

    public function test_a_failed_download_fails_and_keeps_the_old_file(): void
    {
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/ua.php', '<?php return [];');
        Http::fake([UkraineBanks::URL => Http::response('boom', 500)]);

        $this->artisan('filament-iban:update-banks', ['country' => 'UA'])->assertFailed();

        $this->assertSame('<?php return [];', File::get($this->dir.'/ua.php'));
    }

    public function test_a_suspiciously_small_download_is_refused(): void
    {
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/ua.php', "<?php return ['banks' => ['111111' => 'Old']];");
        Http::fake([UkraineBanks::URL => Http::response((string) json_encode([
            ['GLMFO' => 1, 'MFO' => 305299, 'TYP' => 0, 'SHORTNAME' => 'X'],
        ]))]);

        $this->artisan('filament-iban:update-banks', ['country' => 'UA'])->assertFailed();

        $this->assertStringContainsString('Old', File::get($this->dir.'/ua.php'));
    }

    public function test_a_broken_override_falls_back_to_the_shipped_data(): void
    {
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/ua.php', '<?php this is not php');

        $this->assertStringContainsString('ПриватБанк', (string) app(BankDirectories::class)->for('UA')?->bankName('UA21305299'.'0000026007233566001'));

        File::put($this->dir.'/ua.php', '<?php return "nope";');
        app()->forgetInstance(BankDirectories::class);

        $this->assertStringContainsString('ПриватБанк', (string) app(BankDirectories::class)->for('UA')?->bankName('UA21305299'.'0000026007233566001'));
    }

    public function test_an_unknown_country_is_rejected(): void
    {
        Http::fake();

        $this->artisan('filament-iban:update-banks', ['country' => 'FR'])->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_the_ukrainian_status_reaches_the_directory(): void
    {
        Http::fake([UkraineBanks::URL => Http::response($this->nbu())]);

        $this->artisan('filament-iban:update-banks', ['country' => 'UA'])->assertSuccessful();

        $details = app(BankDirectories::class)->details($this->withCheckDigits('UA', '300335'.'0000026007233566001'));

        $this->assertFalse($details?->isActive());
        $this->assertSame('liquidation', $details->status);
    }

    public function test_the_path_option_overrides_the_config(): void
    {
        Http::fake([PolandBanks::URL => Http::response($this->nbp())]);
        $other = $this->dir.'/other';

        $this->artisan('filament-iban:update-banks', ['country' => 'PL', '--path' => $other])->assertSuccessful();

        $this->assertFileExists($other.'/pl.php');
    }
}
