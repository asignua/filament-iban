<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Unit;

use Asignua\FilamentIban\Support\Iban;
use Asignua\FilamentIban\Support\IbanRegistry;
use Asignua\FilamentIban\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class IbanTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validIbans(): array
    {
        return [
            'GB' => ['GB82WEST12345698765432'],
            'DE' => ['DE89370400440532013000'],
            'PL' => ['PL61109010140000071219812874'],
            'UA' => ['UA213223130000026007233566001'],
            'FR' => ['FR1420041010050500013M02606'],
            'ES' => ['ES9121000418450200051332'],
            'IT' => ['IT60X0542811101000000123456'],
            'NL' => ['NL91ABNA0417164300'],
            'CH' => ['CH9300762011623852957'],
            'AT' => ['AT611904300234573201'],
            'BE' => ['BE68539007547034'],
            'NO' => ['NO9386011117947'],
            'LT' => ['LT121000011101001000'],
            'TR' => ['TR330006100519786457841326'],
            'spaced' => ['GB82 WEST 1234 5698 7654 32'],
            'lower case' => ['gb82west12345698765432'],
        ];
    }

    #[DataProvider('validIbans')]
    public function test_valid_ibans(string $iban): void
    {
        $this->assertNull(Iban::problem($iban));
        $this->assertTrue(Iban::isValid($iban));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidIbans(): array
    {
        return [
            'wrong check digits' => ['GB83WEST12345698765432', 'checksum'],
            'one digit changed' => ['DE89370400440532013001', 'checksum'],
            'too short' => ['DE8937040044053201300', 'length'],
            'too long' => ['PL611090101400000712198128740', 'length'],
            'unknown country' => ['ZZ89370400440532013000', 'unknown_country'],
            'garbage' => ['not an iban', 'format'],
            'bad bban structure' => ['GB82W3ST12345698765432', 'structure'],
            'empty' => ['', 'empty'],
        ];
    }

    #[DataProvider('invalidIbans')]
    public function test_invalid_ibans(string $iban, string $problem): void
    {
        $this->assertSame($problem, Iban::problem($iban));
        $this->assertFalse(Iban::isValid($iban));
    }

    public function test_every_registry_example_is_valid_and_has_the_registry_length(): void
    {
        foreach (IbanRegistry::all() as $code => $data) {
            $this->assertStringStartsWith($code, $data['example']);
            $this->assertSame($data['length'], strlen($data['example']), $code);
            $this->assertNull(Iban::problem($data['example']), $code);
        }

        $this->assertGreaterThanOrEqual(80, count(IbanRegistry::all()));
    }

    public function test_normalize_and_format(): void
    {
        $this->assertSame('GB82WEST12345698765432', Iban::normalize(" gb82 west\u{00A0}1234 5698\t7654 32 "));
        $this->assertSame('GB82 WEST 1234 5698 7654 32', Iban::format('gb82west12345698765432'));
        $this->assertSame('GB82 WEST 1234 5698 7654 32', Iban::format('GB82 WEST 1234 5698 7654 32'));
        $this->assertSame('', Iban::format(null));
        $this->assertSame('AB12', Iban::format('ab 12'));
    }

    public function test_country_and_bank_code(): void
    {
        $this->assertSame('UA', Iban::country('ua213223130000026007233566001'));
        $this->assertNull(Iban::country('12'));
        $this->assertSame('322313', Iban::bankCode('UA213223130000026007233566001'));
        $this->assertSame('10901014', Iban::bankCode('PL61109010140000071219812874'));
        $this->assertSame('37040044', Iban::bankCode('DE89370400440532013000'));
        $this->assertSame('WEST', Iban::bankCode('GB82WEST12345698765432'));
        $this->assertSame('05428', Iban::bankCode('IT60X0542811101000000123456'));
        $this->assertNull(Iban::bankCode('XX00'));
    }

    public function test_example(): void
    {
        $this->assertSame('GB82WEST12345698765432', Iban::example('gb'));
        $this->assertNull(Iban::example('ZZ'));
    }
}
