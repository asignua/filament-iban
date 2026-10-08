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

        // Static closures taking the component: inside a Repeater/Builder Filament clones the field per item, and a
        // closure bound to `$this` would keep reading the original template (no item state path, wrong `$get`).
        $this->rule(static fn (IbanInput $component): IbanRule => IbanRule::make()->countries($component->getCountries()));

        $this->placeholder(static function (IbanInput $component): ?string {
            $countries = $component->getCountries();

            return count($countries) === 1 && ($example = Iban::example($countries[0])) !== null
                ? Iban::format($example)
                : null;
        });

        $this->mutateStateForValidationUsing(static fn (mixed $state): mixed => is_string($state) ? Iban::normalize($state) : $state);

        parent::helperText(static fn (IbanInput $component): string|Htmlable|null => $component->composeHelperText());

        $this->autocomplete(false);
        // The browser limit applies to the grouped text the mask writes, while maxLength()/length() describe the compact
        // value: widen it by the separators (one per full group of four) and leave the length check to the rules.
        $this->extraInputAttributes(static function (IbanInput $component): array {
            $max = $component->getMaxLength();

            return [
                'maxlength' => $max === null ? null : $max + intdiv(max($max - 1, 0), 4),
                'minlength' => null,
            ];
        }, merge: true);

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
     * The bank name is resolved on the server on blur, so the field turns live only when it is shown. Done here and
     * not with `live()` in setUp: an explicit `isLive === false` would stop the field from inheriting a parent
     * container's `live()`. A `->live()` of your own sets `isLive` and wins.
     *
     * @return array<string>
     */
    public function getStateBindingModifiers(bool $withBlur = true, bool $withDebounce = true, bool $isOptimisticallyLive = true): array
    {
        if ($this->stateBindingModifiers === null && $this->isLive === null && $this->shouldShowBankName()) {
            return $withBlur ? ['live', 'blur'] : ($isOptimisticallyLive ? ['live'] : []);
        }

        return parent::getStateBindingModifiers($withBlur, $withDebounce, $isOptimisticallyLive);
    }

    public function isLive(): bool
    {
        return ($this->isLive === null && $this->shouldShowBankName()) || parent::isLive();
    }

    public function isLiveOnBlur(): bool
    {
        return ($this->isLive === null && $this->shouldShowBankName()) || parent::isLiveOnBlur();
    }

    /**
     * Your own helper text is shown together with the bank name (separated by an em dash) instead of replacing it.
     */
    public function helperText(string|Htmlable|Closure|null $text): static
    {
        $this->userHelperText = $text;

        return $this;
    }

    protected function composeHelperText(): string|Htmlable|null
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

    /**
     * Filament lower-cases the first letter of the label in validation messages ("The iBAN must..."). A label that
     * opens with an acronym ("IBAN", "VAT / Tax ID") is kept as written; your own `->validationAttribute()` wins.
     */
    public function getValidationAttribute(): string
    {
        if (filled($this->evaluate($this->validationAttribute))) {
            return parent::getValidationAttribute();
        }

        $label = $this->getLabel();

        return is_string($label) && preg_match('/^\p{Lu}{2}/u', $label) === 1
            ? $label
            : parent::getValidationAttribute();
    }

    public function getDefaultLabel(): string
    {
        return strtolower(str($this->getName())->afterLast('.')->toString()) === 'iban'
            ? 'IBAN'
            : parent::getDefaultLabel();
    }
}
