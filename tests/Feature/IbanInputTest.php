<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Feature;

use Asignua\FilamentIban\Contracts\BankDirectory;
use Asignua\FilamentIban\Contracts\ResolvesBankDetails;
use Asignua\FilamentIban\Forms\Components\IbanInput;
use Asignua\FilamentIban\Infolists\Components\IbanEntry;
use Asignua\FilamentIban\Support\BankDetails;
use Asignua\FilamentIban\Support\BankDirectories;
use Asignua\FilamentIban\Tables\Columns\IbanColumn;
use Asignua\FilamentIban\Tests\Concerns\BuildsIbans;
use Asignua\FilamentIban\Tests\TestCase;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use Workbench\App\Filament\Resources\SupplierResource\Pages\ViewSupplier;
use Workbench\App\Livewire\IbanForm;
use Workbench\App\Models\Supplier;

class IbanInputTest extends TestCase
{
    use BuildsIbans;

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

    public function test_the_bank_name_is_shown_inside_a_repeater_item(): void
    {
        Livewire::test(IbanForm::class)
            ->fillForm(['accounts' => [['iban' => 'UA21 3223 1300 0002 6007 2335 6600 1']]])
            ->assertSee('Укрексімбанк');
    }

    public function test_the_html_maxlength_covers_the_grouped_text(): void
    {
        $html = Livewire::test(IbanForm::class)->html();

        $this->assertStringContainsString('maxlength="42"', $html);
    }

    public function test_bank_names_off_leaves_live_to_the_container(): void
    {
        $html = html_entity_decode(Livewire::test(IbanForm::class)->html());

        $this->assertStringContainsString('wire:model.live="data.inherits"', $html);
        $this->assertStringContainsString('wire:model.live.blur="data.ua"', $html);
        $this->assertStringNotContainsString('wire:model.live="data.iban"', $html);
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
        config()->set('filament-iban.show_bank_name', true);
        $input = IbanInput::make('b');

        $this->assertTrue($input->isLive());
        $this->assertTrue($input->isLiveOnBlur());
        $this->assertTrue($input->shouldShowBankName());
        $this->assertSame(['live', 'blur'], $input->getStateBindingModifiers());
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

    public function test_the_default_label_and_message_keep_the_acronym(): void
    {
        $this->assertSame('IBAN', IbanInput::make('iban')->getLabel());
        $this->assertSame('IBAN', IbanInput::make('data.iban')->getValidationAttribute());

        Livewire::test(IbanForm::class)
            ->fillForm(['iban' => 'GB83 WEST 1234 5698 7654 32'])
            ->call('save')
            ->assertHasFormErrors(['iban'])
            ->assertSee('The IBAN ')
            ->assertDontSee('The iBAN');
    }

    public function test_a_lowercase_label_keeps_the_filament_behaviour(): void
    {
        $this->assertSame('bank account', IbanInput::make('x')->label('Bank account')->getValidationAttribute());
        $this->assertSame('iBAN', IbanInput::make('x')->label('iBAN')->getValidationAttribute());
        $this->assertSame('IBAN account', IbanInput::make('x')->label('IBAN account')->getValidationAttribute());
    }

    public function test_an_explicit_validation_attribute_wins(): void
    {
        $this->assertSame('x', IbanInput::make('iban')->validationAttribute('x')->getValidationAttribute());
        $this->assertSame('x', IbanInput::make('iban')->label('IBAN')->validationAttribute(fn () => 'x')->getValidationAttribute());
    }

    public function test_bank_details_show_name_bic_and_city_under_the_field(): void
    {
        Livewire::test(IbanForm::class)
            ->fillForm(['details' => 'UA21 3223 1300 0002 6007 2335 6600 1'])
            ->assertSee('Укрексімбанк')
            ->assertSee('Київ')
            ->assertDontSee(__('filament-iban::filament-iban.bank.status.liquidation'));
    }

    public function test_bank_details_warn_about_a_bank_in_liquidation(): void
    {
        $dir = $this->banksDirectory("<?php return ['banks' => ['322313' => ['name' => 'Old Bank', 'bic' => 'OLDBUA2X', 'city' => 'Lviv', 'status' => 'liquidation']]];");

        Livewire::test(IbanForm::class)
            ->fillForm(['details' => 'UA21 3223 1300 0002 6007 2335 6600 1'])
            ->assertSeeHtml('Old Bank · OLDBUA2X · Lviv')
            ->assertSeeHtml('fi-color-danger')
            ->assertSee(__('filament-iban::filament-iban.bank.status.liquidation'));

        File::deleteDirectory($dir);
    }

    public function test_bank_details_are_escaped_and_composed_with_the_own_helper_text(): void
    {
        $dir = $this->banksDirectory("<?php return ['banks' => ['322313' => ['name' => '<script>alert(1)</script>', 'city' => '<b>x</b>']]];");

        $html = Livewire::test(IbanForm::class)
            ->fillForm(['detailed_help' => 'UA21 3223 1300 0002 6007 2335 6600 1'])
            ->html();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; · &lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringContainsString('Own &lt;hint&gt; — ', $html);

        File::deleteDirectory($dir);
    }

    public function test_bank_details_make_the_field_live_on_blur_and_replace_the_plain_name(): void
    {
        $input = IbanInput::make('b')->showBankDetails();

        $this->assertTrue($input->isLiveOnBlur());
        $this->assertSame(['live', 'blur'], $input->getStateBindingModifiers());
        config()->set('filament-iban.show_bank_details', true);
        $this->assertTrue(IbanInput::make('c')->shouldShowBankDetails());
        $this->assertFalse(IbanInput::make('c')->showBankDetails(false)->shouldShowBankDetails());
    }

    public function test_entry_and_column_render_the_details_escaped_with_a_warning(): void
    {
        $dir = $this->banksDirectory("<?php return ['banks' => ['322313' => ['name' => 'A <i>Bank</i>', 'bic' => 'ABCDUA2X', 'city' => 'Kyiv', 'status' => 'closed']]];");
        $iban = 'UA213223130000026007233566001';

        $supplier = Supplier::create(['name' => 'Acme', 'country' => 'UA', 'iban' => $iban]);

        $column = IbanColumn::make('iban')->showBankDetails();
        $description = (string) $this->description($column, $iban);

        $this->assertStringContainsString('A &lt;i&gt;Bank&lt;/i&gt; · ABCDUA2X · Kyiv', $description);
        $this->assertStringContainsString('fi-color-danger', $description);
        $this->assertStringContainsString(__('filament-iban::filament-iban.bank.status.closed'), $description);

        $name = IbanColumn::make('iban')->showBankName();
        $this->assertSame('A <i>Bank</i>', $this->description($name, $iban));

        $off = IbanColumn::make('iban');
        $this->assertNull($this->description($off, $iban));

        $html = Livewire::test(ViewSupplier::class, ['record' => $supplier->getKey()])->html();

        $this->assertStringContainsString('A &lt;i&gt;Bank&lt;/i&gt; · ABCDUA2X · Kyiv', $html);
        $this->assertStringNotContainsString('<i>Bank</i>', $html);
        $this->assertStringContainsString('fi-color-danger', $html);

        $list = Livewire::test(ListSuppliers::class)->html();

        $this->assertStringContainsString('A &lt;i&gt;Bank&lt;/i&gt; · ABCDUA2X · Kyiv', $list);
        $this->assertStringNotContainsString('<i>Bank</i>', $list);

        File::deleteDirectory($dir);
    }

    public function test_the_config_default_turns_details_on_for_entry_and_column(): void
    {
        config()->set('filament-iban.show_bank_details', true);
        $iban = 'UA213223130000026007233566001';

        $supplier = Supplier::create(['name' => 'Acme', 'country' => 'UA', 'iban' => $iban]);

        $this->assertStringContainsString('Укрексімбанк', (string) $this->description(IbanColumn::make('iban'), $iban));

        $this->assertStringContainsString('Київ', Livewire::test(ViewSupplier::class, ['record' => $supplier->getKey()])->html());
    }

    public function test_a_directory_with_a_hostile_name_cannot_inject_markup(): void
    {
        app(BankDirectories::class)->register('DE', new class implements BankDirectory, ResolvesBankDetails
        {
            public function bankName(string $iban): ?string
            {
                return '<script>1</script>';
            }

            public function bankDetails(string $iban): ?BankDetails
            {
                return new BankDetails('<script>1</script>', '<img src=x onerror=1>', '"><svg/onload=1>');
            }
        });

        $html = Livewire::test(IbanForm::class)
            ->fillForm(['details' => 'DE89 3704 0044 0532 0130 00'])
            ->html();

        $this->assertStringNotContainsString('<script>1</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<svg/onload', $html);
        $this->assertStringContainsString('&lt;script&gt;1&lt;/script&gt;', $html);
    }

    /**
     * The column's description closure needs a mounted table; call it with the state directly.
     */
    private function description(IbanColumn $column, string $state): string|Htmlable|null
    {
        $closure = (fn (): Closure => $this->descriptionBelow)->call($column);

        return $closure($column, $state);
    }

    private function banksDirectory(string $uaFile): string
    {
        $dir = sys_get_temp_dir().'/filament-iban-'.uniqid();
        File::ensureDirectoryExists($dir);
        File::put($dir.'/ua.php', $uaFile);
        config()->set('filament-iban.banks_path', $dir);

        return $dir;
    }
}
