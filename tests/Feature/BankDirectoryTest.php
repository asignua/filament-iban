<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Feature;

use Asignua\FilamentIban\Contracts\BankDirectory;
use Asignua\FilamentIban\Support\BankDirectories;
use Asignua\FilamentIban\Tests\TestCase;
use Illuminate\Support\Facades\File;

class BankDirectoryTest extends TestCase
{
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

    private function ukrainianIban(string $mfo): string
    {
        return $this->withCheckDigits('UA', $mfo.'0000026007233566001');
    }

    private function polishIban(string $code): string
    {
        return $this->withCheckDigits('PL', $code.'01014'.'0000071219812874');
    }

    private function withCheckDigits(string $country, string $bban): string
    {
        $digits = '';

        foreach (str_split($bban.$country.'00') as $char) {
            $digits .= ctype_digit($char) ? $char : (string) (ord($char) - 55);
        }

        $remainder = 0;

        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $country.str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT).$bban;
    }
}
