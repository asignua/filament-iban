<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Feature;

use Asignua\FilamentIban\Contracts\BankDirectory;
use Asignua\FilamentIban\Contracts\ResolvesBankDetails;
use Asignua\FilamentIban\Support\BankDetails;
use Asignua\FilamentIban\Support\BankDirectories;
use Asignua\FilamentIban\Tests\Concerns\BuildsIbans;
use Asignua\FilamentIban\Tests\TestCase;
use Illuminate\Support\Facades\File;

class BankDirectoryTest extends TestCase
{
    use BuildsIbans;

    public function test_ukrainian_bank_names_come_from_the_mfo(): void
    {
        $banks = app(BankDirectories::class);

        $this->assertStringContainsString('ПриватБанк', (string) $banks->bankName($this->ukrainianIban('305299')));
    }

    public function test_the_shipped_ukrainian_data_knows_the_big_banks(): void
    {
        $directory = app(BankDirectories::class)->for('UA');
        $names = [
            '305299' => 'ПриватБанк',
            '300465' => 'Ощадбанк',
            '322001' => 'УНІВЕРСАЛ',
            '320478' => 'УКРГАЗБАНК',
            '334851' => 'ПУМБ',
            '300335' => 'Райффайзен',
        ];

        foreach ($names as $mfo => $needle) {
            $iban = $this->ukrainianIban((string) $mfo);

            $this->assertNotNull($directory);
            $this->assertStringContainsString($needle, (string) $directory->bankName($iban), (string) $mfo);
        }
    }

    public function test_polish_bank_names_come_from_the_bank_code(): void
    {
        $banks = app(BankDirectories::class);

        $this->assertStringContainsString('Powszechna Kasa Oszczędności', (string) $banks->bankName($this->polishIban('102')));
        $this->assertStringContainsString('Bank Polska Kasa Opieki', (string) $banks->bankName($this->polishIban('124')));
        $this->assertStringContainsString('mBank', (string) $banks->bankName($this->polishIban('114')));
        $this->assertStringContainsString('ING Bank', (string) $banks->bankName($this->polishIban('105')));
    }

    public function test_polish_numbers_inherited_through_mergers_resolve_to_the_current_owner(): void
    {
        $banks = app(BankDirectories::class);
        $name = fn (string $number): string => (string) $banks->bankName($this->withCheckDigits('PL', $number.'0000071219812874'));

        $this->assertStringContainsString('Powszechna Kasa Oszczędności', $name('14400003'));
        $this->assertStringContainsString('Erste Bank', $name('15000002'));
        $this->assertStringContainsString('Erste Bank', $name('19100009'));
        $this->assertStringContainsString('Alior', $name('10600018'));
        $this->assertStringContainsString('VeloBank', $name('10300019'));
        $this->assertStringContainsString('VeloBank', $name('24800002'));
        // not listed: falls back to the bank code prefix
        $this->assertStringContainsString('mBank', $name('11499999'));
        // cooperative bank, 4-digit code
        $this->assertStringContainsString('Bank Spółdzielczy w Otwocku', $name('80010005'));
    }

    public function test_unknown_banks_countries_and_invalid_ibans_give_null(): void
    {
        $banks = app(BankDirectories::class);

        $this->assertNull($banks->bankName('DE89370400440532013000'));
        $this->assertNull($banks->bankName('UA213223130000026007233566002'));
        $this->assertNull($banks->bankName($this->ukrainianIban('999999')));
        $this->assertNull($banks->bankName(null));
    }

    public function test_a_file_in_banks_path_wins_over_the_shipped_data(): void
    {
        $dir = sys_get_temp_dir().'/filament-iban-'.uniqid();
        File::ensureDirectoryExists($dir);
        File::put($dir.'/ua.php', "<?php return ['banks' => ['305299' => 'Override Bank']];");
        config()->set('filament-iban.banks_path', $dir);

        $this->assertSame('Override Bank', app(BankDirectories::class)->bankName($this->ukrainianIban('305299')));

        File::deleteDirectory($dir);
    }

    public function test_a_custom_directory_can_be_registered(): void
    {
        app(BankDirectories::class)->register('DE', new class implements BankDirectory
        {
            public function bankName(string $iban): ?string
            {
                return substr($iban, 4, 8) === '37040044' ? 'Commerzbank' : null;
            }
        });

        $this->assertSame('Commerzbank', app(BankDirectories::class)->bankName('DE89370400440532013000'));
        $this->assertContains('DE', app(BankDirectories::class)->countries());
    }

    public function test_details_come_from_an_array_entry_and_a_plain_string_entry(): void
    {
        $dir = sys_get_temp_dir().'/filament-iban-'.uniqid();
        File::ensureDirectoryExists($dir);
        File::put($dir.'/ua.php', "<?php return ['banks' => [
            '305299' => ['name' => 'Rich Bank', 'bic' => 'RICHUA2X', 'city' => 'Kyiv', 'status' => 'liquidation'],
            '300465' => 'Plain Bank',
        ]];");
        config()->set('filament-iban.banks_path', $dir);
        $banks = app(BankDirectories::class);

        $rich = $banks->details($this->ukrainianIban('305299'));
        $plain = $banks->details($this->ukrainianIban('300465'));

        $this->assertSame('Rich Bank', $rich?->name);
        $this->assertSame('RICHUA2X', $rich?->bic);
        $this->assertSame('Kyiv', $rich?->city);
        $this->assertFalse($rich?->isActive());
        $this->assertSame('Plain Bank', $plain?->name);
        $this->assertNull($plain?->bic);
        $this->assertTrue($plain?->isActive());
        $this->assertSame('Rich Bank', $banks->bankName($this->ukrainianIban('305299')));
        $this->assertSame('Plain Bank', $banks->bankName($this->ukrainianIban('300465')));
        $this->assertNull($banks->details($this->ukrainianIban('999999')));
        $this->assertNull($banks->details('UA213223130000026007233566002'));
        $this->assertNull($banks->details(null));

        File::deleteDirectory($dir);
    }

    public function test_the_shipped_data_has_a_town_and_a_bic(): void
    {
        $banks = app(BankDirectories::class);

        $this->assertSame('Київ', $banks->details($this->ukrainianIban('305299'))?->city);
        $this->assertSame('BPKOPLPWXXX', $banks->details($this->polishIban('102'))?->bic);
    }

    public function test_a_directory_without_details_support_still_gives_the_name(): void
    {
        app(BankDirectories::class)->register('DE', new class implements BankDirectory
        {
            public function bankName(string $iban): ?string
            {
                return 'Commerzbank';
            }
        });

        $details = app(BankDirectories::class)->details('DE89370400440532013000');

        $this->assertSame('Commerzbank', $details?->name);
        $this->assertNull($details->bic);
    }

    public function test_a_directory_can_resolve_details(): void
    {
        app(BankDirectories::class)->register('DE', new class implements BankDirectory, ResolvesBankDetails
        {
            public function bankName(string $iban): ?string
            {
                return 'Commerzbank';
            }

            public function bankDetails(string $iban): ?BankDetails
            {
                return new BankDetails('Commerzbank', 'COBADEFFXXX', 'Frankfurt');
            }
        });

        $this->assertSame('Frankfurt', app(BankDirectories::class)->details('DE89370400440532013000')?->city);
    }

    public function test_the_download_only_countries_know_nothing_until_downloaded(): void
    {
        $banks = app(BankDirectories::class);

        $this->assertSame(['UA', 'PL', 'DE', 'AT', 'CH', 'LI', 'CZ'], $banks->countries());
        $this->assertNull($banks->details('DE89370400440532013000'));
        $this->assertNull($banks->details('AT611904300234573201'));
        $this->assertNull($banks->details('CH9300762011623852957'));
        $this->assertNull($banks->details('CZ6508000000192000145399'));
    }

    private function ukrainianIban(string $mfo): string
    {
        return $this->withCheckDigits('UA', $mfo.'0000026007233566001');
    }

    private function polishIban(string $code): string
    {
        return $this->withCheckDigits('PL', $code.'01014'.'0000071219812874');
    }
}
