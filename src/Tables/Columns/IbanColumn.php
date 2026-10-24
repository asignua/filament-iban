<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tables\Columns;

use Asignua\FilamentIban\Support\BankDirectories;
use Asignua\FilamentIban\Support\Iban;
use Closure;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A table column that shows an IBAN in groups of four. Copying puts the compact form on the clipboard.
 *
 *     IbanColumn::make('iban')->showBankName()
 *     IbanColumn::make('iban')->showBankDetails()
 */
class IbanColumn extends TextColumn
{
    protected bool|Closure $showBankName = false;

    protected bool|Closure $showBankDetails = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatStateUsing(static fn (mixed $state): ?string => is_string($state) && $state !== '' ? Iban::format($state) : null);
        $this->copyable();
        $this->copyableState(static fn (mixed $state): ?string => is_string($state) ? Iban::normalize($state) : null);
        $this->fontFamily('mono');
        $this->searchable(false);
        $this->showBankName((bool) config('filament-iban.show_bank_name', false));
        $this->showBankDetails((bool) config('filament-iban.show_bank_details', false));
    }

    public function showBankName(bool|Closure $condition = true): static
    {
        $this->showBankName = $condition;

        return $this->applyBankLine();
    }

    /**
     * Name, BIC and city of the bank, with a warning when the bank is in liquidation. Replaces the plain bank name.
     */
    public function showBankDetails(bool|Closure $condition = true): static
    {
        $this->showBankDetails = $condition;

        return $this->applyBankLine();
    }

    private function applyBankLine(): static
    {
        $this->description(function (IbanColumn $component, mixed $state): string|Htmlable|null {
            if (!is_string($state)) {
                return null;
            }

            if ($component->evaluate($component->showBankDetails)) {
                return app(BankDirectories::class)->details($state)?->toHtml();
            }

            return $component->evaluate($component->showBankName) ? app(BankDirectories::class)->bankName($state) : null;
        });

        return $this;
    }
}
