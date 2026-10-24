<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Concerns;

trait BuildsIbans
{
    protected function withCheckDigits(string $country, string $bban): string
    {
        $digits = '';

        foreach (str_split($bban.$country.'00') as $char) {
            $digits .= ctype_digit($char) ? $char : (string) (ord($char) - 55);
        }

        $remainder = 0;

        foreach (str_split($digits) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $country.str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT).$bban;
    }
}
