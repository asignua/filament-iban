<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Rules;

use Asignua\FilamentIban\Support\Iban as IbanValue;
use Asignua\FilamentIban\Support\IbanRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an IBAN: known country, length and BBAN structure of the SWIFT registry, mod 97 check digits.
 * Spaces and case are ignored. Empty values are left to `required`.
 *
 *     'iban' => ['nullable', new Iban]
 *     'iban' => ['required', Iban::make()->countries(['UA', 'PL'])]
 */
class Iban implements ValidationRule
{
    /** @var list<string> */
    protected array $countries = [];

    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Only these countries (ISO 3166-1 alpha-2) are accepted; an empty list accepts every IBAN country.
     *
     * @param array<int, string> $isoCodes
     */
    public function countries(array $isoCodes): static
    {
        $this->countries = array_values(array_unique(array_map(
            static fn (string $code): string => strtoupper($code),
            $isoCodes,
        )));

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCountries(): array
    {
        return $this->countries;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (!is_string($value) && !is_numeric($value)) {
            $fail('filament-iban::filament-iban.validation.invalid')->translate();

            return;
        }

        $value = (string) $value;

        if (IbanValue::normalize($value) === '') {
            return;
        }

        $problem = IbanValue::problem($value);

        if ($problem !== null) {
            $length = IbanRegistry::get((string) IbanValue::country($value))['length'] ?? '';

            $fail('filament-iban::filament-iban.validation.'.$problem)->translate(['length' => (string) $length]);

            return;
        }

        if ($this->countries !== [] && !in_array(IbanValue::country($value), $this->countries, true)) {
            $fail('filament-iban::filament-iban.validation.country_not_allowed')
                ->translate(['countries' => implode(', ', $this->countries)]);
        }
    }
}
