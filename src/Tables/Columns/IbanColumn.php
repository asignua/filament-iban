<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tables\Columns;

use Asignua\FilamentIban\Support\BankDirectories;
use Asignua\FilamentIban\Support\Iban;
use Closure;
use Filament\Tables\Columns\TextColumn;

/**
 * A table column that shows an IBAN in groups of four. Copying puts the compact form on the clipboard.
 *
 *     IbanColumn::make('iban')->showBankName()
 */
class IbanColumn extends TextColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->formatStateUsing(static fn (mixed $state): ?string => is_string($state) && $state !== '' ? Iban::format($state) : null);
        $this->copyable();
        $this->copyableState(static fn (mixed $state): ?string => is_string($state) ? Iban::normalize($state) : null);
        $this->fontFamily('mono');
        $this->searchable(false);
        $this->showBankName((bool) config('filament-iban.show_bank_name', false));
    }

    public function showBankName(bool|Closure $condition = true): static
    {
        $this->description(function (mixed $state) use ($condition): ?string {
            if (!$this->evaluate($condition)) {
                return null;
            }

            return is_string($state) ? app(BankDirectories::class)->bankName($state) : null;
        });

        return $this;
    }
}
