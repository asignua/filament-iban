<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support;

/**
 * Country data of the SWIFT IBAN registry, read once from `resources/data/countries.php`.
 *
 * @phpstan-type Country array{name: string, length: int, structure: string, bank: array{int, int}, example: string}
 */
final class IbanRegistry
{
    /** @var array<string, Country>|null */
    private static ?array $countries = null;

    /**
     * @return array<string, Country>
     */
    public static function all(): array
    {
        /** @var array<string, Country> $countries */
        $countries = self::$countries ??= require __DIR__.'/../../resources/data/countries.php';

        return $countries;
    }

    /**
     * @return Country|null
     */
    public static function get(string $country): ?array
    {
        return self::all()[strtoupper($country)] ?? null;
    }

    public static function has(string $country): bool
    {
        return self::get($country) !== null;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /**
     * The regular expression a BBAN (the IBAN without country code and check digits) has to match.
     */
    public static function bbanPattern(string $country): ?string
    {
        $data = self::get($country);

        if ($data === null) {
            return null;
        }

        $pattern = '';

        preg_match_all('/(\d+)([anc])/', $data['structure'], $parts, PREG_SET_ORDER);

        foreach ($parts as [, $length, $type]) {
            $class = match ($type) {
                'n' => '[0-9]',
                'a' => '[A-Z]',
                default => '[A-Z0-9]',
            };

            $pattern .= $class.'{'.$length.'}';
        }

        return '/^'.$pattern.'$/';
    }
}
