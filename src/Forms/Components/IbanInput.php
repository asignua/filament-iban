<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Forms\Components;

use Asignua\FilamentIban\Rules\Iban as IbanRule;
use Asignua\FilamentIban\Support\BankDirectories;
use Asignua\FilamentIban\Support\Iban;
use Closure;
use Filament\Forms\Components\TextInput;

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

        $this->helperText(fn (): ?string => $this->shouldShowBankName() ? $this->getBankName() : null);

        $this->autocomplete(false);
        $this->extraInputAttributes([
            'autocapitalize' => 'characters',
            'spellcheck' => 'false',
            'inputmode' => 'text',
        ], merge: true);

        $this->extraAlpineAttributes([
            'x-on:input' => <<<'JS'
                (() => {
                    const el = $el;
                    const caret = el.selectionStart ?? el.value.length;
                    const significant = el.value.slice(0, caret).replace(/[^A-Za-z0-9]/g, '').length;
                    const formatted = el.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase().replace(/(.{4})(?=.)/g, '$1 ');
                    if (formatted === el.value) return;
                    el.value = formatted;
                    let position = 0;
                    let seen = 0;
                    while (position < formatted.length && seen < significant) {
                        if (formatted[position] !== ' ') seen++;
                        position++;
                    }
                    el.setSelectionRange(position, position);
                })()
                JS,
        ], merge: true);
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

        if ($condition !== false) {
            $this->live(onBlur: true);
        }

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
