<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Feature;

use Asignua\FilamentIban\Forms\Components\IbanInput;
use Asignua\FilamentIban\Infolists\Components\IbanEntry;
use Asignua\FilamentIban\Tables\Columns\IbanColumn;
use Asignua\FilamentIban\Tests\TestCase;
use Livewire\Livewire;
use Workbench\App\Livewire\IbanForm;

class IbanInputTest extends TestCase
{
    public function test_state_is_dehydrated_compact(): void
    {
        Livewire::test(IbanForm::class)
            ->fillForm(['iban' => 'gb82 west 1234 5698 7654 32'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved.iban', 'GB82WEST12345698765432');
    }

    public function test_a_stored_compact_value_is_shown_in_groups(): void
    {
        Livewire::test(IbanForm::class)
            ->assertSet('data.stored', 'UA21 3223 1300 0002 6007 2335 6600 1')
            ->call('save')
            ->assertSet('saved.stored', 'UA213223130000026007233566001');
    }

    public function test_an_invalid_iban_is_rejected(): void
    {
        Livewire::test(IbanForm::class)
            ->fillForm(['iban' => 'GB83 WEST 1234 5698 7654 32'])
            ->call('save')
            ->assertHasFormErrors(['iban']);
    }

    public function test_blank_is_stored_as_null(): void
    {
        Livewire::test(IbanForm::class)
            ->fillForm(['iban' => '   '])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved.iban', null);
    }

    public function test_country_restriction(): void
    {
        Livewire::test(IbanForm::class)
            ->fillForm(['eu' => 'GB82WEST12345698765432'])
            ->call('save')
            ->assertHasFormErrors(['eu']);

        Livewire::test(IbanForm::class)
            ->fillForm(['eu' => 'PL61 1090 1014 0000 0712 1981 2874'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved.eu', 'PL61109010140000071219812874');
    }

    public function test_the_bank_name_is_shown_after_input(): void
    {
        Livewire::test(IbanForm::class)
            ->fillForm(['ua' => 'UA21 3223 1300 0002 6007 2335 6600 1'])
            ->assertSee('Укрексімбанк')->assertDontSee('zzz');
    }

    public function test_the_markup_carries_the_formatter_and_a_single_country_placeholder(): void
    {
        $html = Livewire::test(IbanForm::class)->html();

        $this->assertStringContainsString('x-on:input', $html);
        $this->assertStringContainsString('UA', $html);
        $this->assertStringContainsString('autocapitalize', $html);
    }

    public function test_placeholder_and_countries_resolution(): void
    {
        $one = IbanInput::make('a')->countries(['ua']);
        $many = IbanInput::make('b')->countries(['UA', 'PL']);

        $this->assertSame(['UA'], $one->getCountries());
        $this->assertStringStartsWith('UA', (string) $one->getPlaceholder());
        $this->assertNull($many->getPlaceholder());

        config()->set('filament-iban.countries', ['de']);
        $this->assertSame(['DE'], IbanInput::make('c')->getCountries());
        $this->assertSame(['UA'], IbanInput::make('c')->countries(['UA'])->getCountries());
    }

    public function test_validation_sees_the_compact_value(): void
    {
        $input = IbanInput::make('iban')->maxLength(22);

        $this->assertSame('GB82WEST12345698765432', $input->mutateStateForValidation('gb82 west 1234 5698 7654 32'));
    }

    public function test_the_formatter_writes_back_to_the_model_and_handles_separators(): void
    {
        $html = Livewire::test(IbanForm::class)->html();

        $this->assertStringContainsString('_x_model.set(formatted)', html_entity_decode($html));
        $this->assertStringContainsString('isComposing', $html);
        $this->assertStringContainsString('x-on:beforeinput', $html);
        $this->assertStringContainsString('deleteContentForward', $html);
    }

    public function test_the_config_default_makes_the_field_live_on_blur(): void
    {
        $this->assertFalse(IbanInput::make('a')->isLive());

        config()->set('filament-iban.show_bank_name', true);
        $input = IbanInput::make('b');

        $this->assertTrue($input->isLive());
        $this->assertTrue($input->isLiveOnBlur());
        $this->assertTrue($input->shouldShowBankName());
        $this->assertFalse(IbanInput::make('c')->showBankName(false)->isLive());
    }

    public function test_user_helper_text_is_shown_together_with_the_bank_name(): void
    {
        Livewire::test(IbanForm::class)
            ->fillForm(['helped' => 'UA213223130000026007233566001'])
            ->assertSee('Your own hint')
            ->assertSee('Укрексімбанк');
    }

    public function test_entry_and_column_format_and_copy_the_compact_form(): void
    {
        $this->assertInstanceOf(IbanEntry::class, IbanEntry::make('iban')->showBankName());
        $this->assertInstanceOf(IbanColumn::class, IbanColumn::make('iban')->showBankName());

        $column = IbanColumn::make('iban');

        $this->assertSame('UA21 3223 1300 0002 6007 2335 6600 1', $column->formatState('UA213223130000026007233566001'));
    }
}
