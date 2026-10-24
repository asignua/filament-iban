<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Unit;

use Asignua\FilamentIban\Support\BankDetails;
use Asignua\FilamentIban\Tests\TestCase;

class BankDetailsTest extends TestCase
{
    public function test_a_plain_string_entry_is_just_the_name(): void
    {
        $details = BankDetails::fromEntry('  Old Bank ');

        $this->assertInstanceOf(BankDetails::class, $details);
        $this->assertSame('Old Bank', $details->name);
        $this->assertNull($details->bic);
        $this->assertNull($details->city);
        $this->assertTrue($details->isActive());
    }

    public function test_an_array_entry_carries_every_field(): void
    {
        $details = BankDetails::fromEntry([
            'name' => 'Bank', 'bic' => 'ABCDUA2X', 'city' => 'Kyiv', 'address' => 'Main 1', 'postcode' => '01001', 'status' => 'liquidation',
        ]);

        $this->assertInstanceOf(BankDetails::class, $details);
        $this->assertSame('ABCDUA2X', $details->bic);
        $this->assertSame('Kyiv', $details->city);
        $this->assertSame('Main 1', $details->address);
        $this->assertSame('01001', $details->postcode);
        $this->assertFalse($details->isActive());
    }

    public function test_junk_entries_give_null(): void
    {
        $this->assertNull(BankDetails::fromEntry(''));
        $this->assertNull(BankDetails::fromEntry(['bic' => 'X']));
        $this->assertNull(BankDetails::fromEntry(['name' => '  ']));
        $this->assertNull(BankDetails::fromEntry(42));
        $this->assertNull(BankDetails::fromEntry(null));
        $this->assertSame('Bank', BankDetails::fromEntry(['name' => 'Bank', 'bic' => 12, 'city' => ''])?->name);
        $this->assertNull(BankDetails::fromEntry(['name' => 'Bank', 'bic' => 12])?->bic);
    }

    public function test_to_entry_round_trips_and_stays_a_string_when_only_the_name_is_known(): void
    {
        $this->assertSame('Bank', (new BankDetails('Bank'))->toEntry());
        $this->assertSame(['name' => 'Bank', 'city' => 'Lviv'], (new BankDetails('Bank', null, 'Lviv'))->toEntry());
    }

    public function test_the_summary_joins_the_known_parts(): void
    {
        $this->assertSame('Bank · ABCDDEFF · Berlin', (new BankDetails('Bank', 'ABCDDEFF', 'Berlin'))->summary());
        $this->assertSame('Bank · Berlin', (new BankDetails('Bank', null, 'Berlin'))->summary());
        $this->assertSame('Bank', (new BankDetails('Bank'))->summary());
    }

    public function test_the_status_is_translated_and_an_unknown_one_is_quoted(): void
    {
        $this->assertNull((new BankDetails('Bank'))->statusLabel());
        $this->assertSame(__('filament-iban::filament-iban.bank.status.liquidation'), (new BankDetails('Bank', status: 'liquidation'))->statusLabel());
        $this->assertSame('Status: Odd', (new BankDetails('Bank', status: 'Odd'))->statusLabel());
    }

    public function test_html_is_escaped_and_marks_an_inactive_bank(): void
    {
        $html = (new BankDetails('<script>alert(1)</script> & Co', '"><img src=x>', "O'Brien <b>", status: '<i>gone</i>'))->toHtml()->toHtml();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<i>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; Co', $html);
        $this->assertStringContainsString('fi-color-danger', $html);
        $this->assertStringContainsString('Status: &lt;i&gt;gone&lt;/i&gt;', $html);
    }

    public function test_an_active_bank_has_no_warning(): void
    {
        $this->assertStringNotContainsString('fi-color-danger', (new BankDetails('Bank', 'ABCDDEFF'))->toHtml()->toHtml());
    }
}
