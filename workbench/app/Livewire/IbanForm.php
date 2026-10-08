<?php

declare(strict_types=1);

namespace Workbench\App\Livewire;

use Asignua\FilamentIban\Forms\Components\IbanInput;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class IbanForm extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $saved = [];

    public function mount(): void
    {
        $this->form->fill(['stored' => 'ua213223130000026007233566001']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                IbanInput::make('iban'),
                IbanInput::make('stored'),
                IbanInput::make('eu')->countries(['de', 'PL'])->showBankName(),
                IbanInput::make('helped')->countries(['UA'])->showBankName()->helperText('Your own hint'),
                IbanInput::make('ua')->countries(['UA'])->showBankName(),
                Section::make()->live()->schema([IbanInput::make('inherits')]),
                IbanInput::make('limited')->maxLength(34),
                Repeater::make('accounts')->schema([
                    IbanInput::make('iban')->countries(['UA'])->showBankName(),
                ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->saved = $this->form->getState();
    }

    public function render(): View
    {
        return view('iban-form');
    }
}
