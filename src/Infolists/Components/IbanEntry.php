<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Infolists\Components;

use Asignua\FilamentIban\Support\BankDirectories;
use Asignua\FilamentIban\Support\Iban;
use Closure;
use Filament\Infolists\Components\TextEntry;

/**
 * Shows an IBAN in groups of four. Copying puts the compact form on the clipboard.
 *
 *     IbanEntry::make('iban')->showBankName()
 */
class IbanEntry extends TextEntry
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->formatStateUsing(static fn (mixed $state): ?string => is_string($state) && $state !== '' ? Iban::format($state) : null);
        $this->copyable();
        $this->copyableState(static fn (mixed $state): ?string => is_string($state) ? Iban::normalize($state) : null);
        $this->fontFamily('mono');
        $this->showBankName((bool) config('filament-iban.show_bank_name', false));
    }

    public function showBankName(bool|Closure $condition = true): static
    {
        $this->belowContent(function (IbanEntry $component) use ($condition): ?string {
            if (!$component->evaluate($condition)) {
                return null;
            }

            $state = $component->getState();

            return is_string($state) ? app(BankDirectories::class)->bankName($state) : null;
        });

        return $this;
    }
}
