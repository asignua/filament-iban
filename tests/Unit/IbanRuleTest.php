<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Unit;

use Asignua\FilamentIban\Rules\Iban;
use Asignua\FilamentIban\Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class IbanRuleTest extends TestCase
{
    /**
     * @return array<string, list<string>>
     */
    private function errors(mixed $value, ?Iban $rule = null): array
    {
        return Validator::make(['iban' => $value], ['iban' => [$rule ?? new Iban]])->errors()->toArray();
    }

    public function test_valid_and_grouped_values_pass(): void
    {
        $this->assertSame([], $this->errors('DE89370400440532013000'));
        $this->assertSame([], $this->errors('de89 3704 0044 0532 0130 00'));
    }

    public function test_empty_is_left_to_required(): void
    {
        $this->assertSame([], $this->errors(''));
        $this->assertSame([], $this->errors(null));
    }

    public function test_invalid_utf8_and_separator_only_values_are_not_treated_as_empty(): void
    {
        $this->assertArrayHasKey('iban', $this->errors("GB82WEST\xFF2345698765432"));
        $this->assertArrayHasKey('iban', $this->errors('- - -'));
        $this->assertSame([], $this->errors('   '));
    }

    public function test_a_wrong_checksum_is_reported_with_a_translated_message(): void
    {
        $errors = $this->errors('DE89370400440532013001');

        $this->assertSame(
            [__('filament-iban::filament-iban.validation.checksum', ['attribute' => 'iban'])],
            $errors['iban'],
        );
    }

    public function test_wrong_length_reports_the_expected_one(): void
    {
        $errors = $this->errors('DE8937040044053201300');

        $this->assertStringContainsString('22', $errors['iban'][0]);
    }

    public function test_unknown_country_and_garbage_fail(): void
    {
        $this->assertArrayHasKey('iban', $this->errors('ZZ89370400440532013000'));
        $this->assertArrayHasKey('iban', $this->errors('hello world'));
        $this->assertArrayHasKey('iban', $this->errors(['x']));
    }

    public function test_country_restriction(): void
    {
        $rule = Iban::make()->countries(['ua', 'PL']);

        $this->assertSame([], $this->errors('UA213223130000026007233566001', $rule));
        $this->assertSame([], $this->errors('PL61109010140000071219812874', Iban::make()->countries(['UA', 'PL'])));

        $errors = $this->errors('DE89370400440532013000', $rule);

        $this->assertStringContainsString('UA, PL', $errors['iban'][0]);
    }

    public function test_messages_follow_the_locale(): void
    {
        app()->setLocale('uk');

        $this->assertStringContainsString('контрольні', $this->errors('GB83WEST12345698765432')['iban'][0]);
    }
}
