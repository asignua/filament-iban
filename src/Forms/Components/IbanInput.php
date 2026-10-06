<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Forms\Components;

use Asignua\FilamentIban\Rules\Iban as IbanRule;
use Asignua\FilamentIban\Support\BankDirectories;
use Asignua\FilamentIban\Support\Iban;
use Closure;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * A text input for IBANs. The browser shows the number in groups of four while the user types (visual only); the
 * form state is validated and saved in the compact form (upper case, no spaces).
 *
 *     IbanInput::make('iban')->countries(['UA', 'PL'])->showBankName()
 */
class IbanInput extends TextInput
{
    /** @var array<int, string>|Closure|null */
    protected array|Closure|null $countries = null;

    protected bool|Closure|null $showBankName = null;

    protected string|Htmlable|Closure|null $userHelperText = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(static function (IbanInput $component, mixed $state): void {
            if (is_string($state) && $state !== '') {
                $component->state(Iban::format($state));
            }
        });

        $this->dehydrateStateUsing(static fn (mixed $state): ?string => is_string($state) && Iban::normalize($state) !== ''
            ? Iban::normalize($state)
            : null);

        $this->rule(fn (): IbanRule => IbanRule::make()->countries($this->getCountries()));

        $this->placeholder(function (): ?string {
            $countries = $this->getCountries();

            return count($countries) === 1 && ($example = Iban::example($countries[0])) !== null
                ? Iban::format($example)
                : null;
        });

        $this->mutateStateForValidationUsing(static fn (mixed $state): mixed => is_string($state) ? Iban::normalize($state) : $state);

        $this->live(onBlur: true, condition: fn (): bool => $this->shouldShowBankName());

        parent::helperText(fn (): string|Htmlable|null => $this->composeHelperText());

        $this->autocomplete(false);
        $this->extraInputAttributes([
            'autocapitalize' => 'characters',
            'spellcheck' => 'false',
            'inputmode' => 'text',
        ], merge: true);

        $this->extraAlpineAttributes([
            // Backspace / Delete next to a group separator would only delete the space, which the formatter puts
            // back: remove the neighbouring character as well.
            'x-on:beforeinput' => <<<'JS'
                (() => {
                    const el = $el;
                    if (el.selectionStart !== el.selectionEnd) return;
                    const p = el.selectionStart;
                    let from, to;
                    if ($event.inputType === 'deleteContentForward' && el.value[p] === ' ') { from = p; to = p + 2; }
                    else if ($event.inputType === 'deleteContentBackward' && el.value[p - 1] === ' ') { from = p - 2; to = p; }
                    else return;
                    $event.preventDefault();
                    el.setRangeText('', Math.max(from, 0), Math.min(to, el.value.length), 'end');
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                })()
                JS,
            // Visual mask. x-model (wire:model) has already read the raw text by the time this runs, so the
            // formatted value is written back to it, like Alpine's x-mask does.
            'x-on:input' => <<<'JS'
                (() => {
                    if ($event.isComposing) return;
                    const el = $el;
                    const strip = /[\s ­​-‍﻿.\/-]/g;
                    const caret = el.selectionStart ?? el.value.length;
                    const significant = el.value.slice(0, caret).replace(strip, '').length;
                    const formatted = el.value.replace(strip, '').toUpperCase().replace(/(.{4})(?=.)/g, '$1 ');
                    if (formatted !== el.value) {
                        el.value = formatted;
                        let position = 0;
                        let seen = 0;
                        while (position < formatted.length && seen < significant) {
                            if (formatted[position] !== ' ') seen++;
                            position++;
                        }
                        el.setSelectionRange(position, position);
                    }
                    if (el._x_model && el._x_model.get() !== formatted) el._x_model.set(formatted);
                })()
                JS,
        ], merge: true);
    }

    /**
     * Your own helper text is shown together with the bank name (separated by an em dash) instead of replacing it.
     */
    public function helperText(string|Htmlable|Closure|null $text): static
    {
        $this->userHelperText = $text;

        return $this;
    }

    private function composeHelperText(): string|Htmlable|null
    {
        $own = $this->evaluate($this->userHelperText);
        $bank = $this->shouldShowBankName() ? $this->getBankName() : null;

        if (blank($bank)) {
            return blank($own) ? null : $own;
        }

        if (blank($own)) {
            return $bank;
        }

        $own = $own instanceof Htmlable ? $own->toHtml() : e($own);

        return new HtmlString($own.' — '.e($bank));
    }

    /**
     * Only these countries (ISO 3166-1 alpha-2) are accepted. Overrides `filament-iban.countries`.
     *
     * @param array<int, string>|Closure|null $isoCodes
     */
    public function countries(array|Closure|null $isoCodes): static
    {
        $this->countries = $isoCodes;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCountries(): array
    {
        $countries = $this->evaluate($this->countries) ?? config('filament-iban.countries');

        return is_array($countries)
            ? array_values(array_unique(array_map(static fn (mixed $code): string => strtoupper((string) $code), $countries)))
            : [];
    }

    /**
     * Show the bank name under the field. The name is resolved on the server once the field loses focus.
     */
    public function showBankName(bool|Closure $condition = true): static
    {
        $this->showBankName = $condition;

        return $this;
    }

    public function shouldShowBankName(): bool
    {
        return (bool) ($this->evaluate($this->showBankName) ?? config('filament-iban.show_bank_name', false));
    }

    public function getBankName(): ?string
    {
        $state = $this->getState();

        return is_string($state) ? app(BankDirectories::class)->bankName($state) : null;
    }
}
