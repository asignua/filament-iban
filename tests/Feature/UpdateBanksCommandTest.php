<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Feature;

use Asignua\FilamentIban\Support\BankDirectories;
use Asignua\FilamentIban\Support\Banks\PolandBanks;
use Asignua\FilamentIban\Support\Banks\UkraineBanks;
use Asignua\FilamentIban\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class UpdateBanksCommandTest extends TestCase
{
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
        return (string) json_encode([
            ['GLMFO' => 305299, 'MFO' => 305299, 'TYP' => 0, 'SHORTNAME' => 'АТ КБ "ПриватБанк"'],
            ['GLMFO' => 305299, 'MFO' => 305299, 'TYP' => 2, 'SHORTNAME' => 'Відділення №1 АТ КБ "ПриватБанк"'],
            ['GLMFO' => 300335, 'MFO' => 300335, 'TYP' => 0, 'SHORTNAME' => 'АТ "Райффайзен Банк"'],
            ['GLMFO' => 300335, 'MFO' => 305653, 'TYP' => 1, 'SHORTNAME' => 'Дніпропетровська дирекція'],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function nbp(): string
    {
        $row = static fn (string $code, string $name): string => str_pad($code, 5)."\t".str_pad($name, 40)."\t\t".$code."\t".$code."00000\tCentrala";

        return iconv('UTF-8', 'CP852', implode("\r\n", [
            $row('102', 'Powszechna Kasa Oszczędności Bank Polski SA'),
            $row('102', 'Powszechna Kasa Oszczędności Bank Polski SA'),
            $row('249', 'Alior Bank Spółka Akcyjna'),
            'garbage line',
        ]))."\r\n";
    }

    public function test_it_downloads_and_writes_both_registers(): void
    {
        Http::fake([
            UkraineBanks::URL => Http::response($this->nbu()),
            PolandBanks::URL => Http::response($this->nbp()),
        ]);

        $this->artisan('filament-iban:update-banks')->assertSuccessful();

        $ua = require $this->dir.'/ua.php';
        $pl = require $this->dir.'/pl.php';

        $this->assertSame([
            '300335' => 'АТ "Райффайзен Банк"',
            '305299' => 'АТ КБ "ПриватБанк"',
            '305653' => 'АТ "Райффайзен Банк"',
        ], $ua['banks']);
        $this->assertSame(['102' => 'Powszechna Kasa Oszczędności Bank Polski SA', '249' => 'Alior Bank Spółka Akcyjna'], $pl['banks']);
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

    public function test_an_unknown_country_is_rejected(): void
    {
        Http::fake();

        $this->artisan('filament-iban:update-banks', ['country' => 'DE'])->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_the_path_option_overrides_the_config(): void
    {
        Http::fake([PolandBanks::URL => Http::response($this->nbp())]);
        $other = $this->dir.'/other';

        $this->artisan('filament-iban:update-banks', ['country' => 'PL', '--path' => $other])->assertSuccessful();

        $this->assertFileExists($other.'/pl.php');
    }
}
