<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support;

use Asignua\FilamentIban\Rules\Iban as IbanRule;

/**
 * Value helper for IBANs. Stateless: every method takes the raw string the user typed.
 */
final class Iban
{
    /**
     * The compact form: upper case, no whitespace. This is what should be stored.
     */
    public static function normalize(?string $iban): string
    {
        $iban = (string) $iban;

        // Whitespace, hyphens, dots, slashes and invisible characters (soft hyphen, zero-width, BOM) are separators.
        $clean = preg_replace('/[\s\x{00A0}\x{00AD}\x{200B}-\x{200D}\x{FEFF}.\/-]+/u', '', $iban);

        // Invalid UTF-8: keep the bytes so that validation reports a format error instead of an empty value.
        return strtoupper($clean ?? $iban);
    }

    /**
     * The display form: groups of four separated by a space.
     */
    public static function format(?string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }

    public static function isValid(?string $iban): bool
    {
        return self::problem($iban) === null;
    }

    public static function country(?string $iban): ?string
    {
        $country = substr(self::normalize($iban), 0, 2);

        return preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;
    }

    /**
     * The bank identifier, in the position the registry defines for the country.
     */
    public static function bankCode(?string $iban): ?string
    {
        return self::span($iban, 'bank');
    }

    /**
     * The branch identifier, for the countries whose registry entry defines one (null otherwise).
     */
    public static function branchCode(?string $iban): ?string
    {
        return self::span($iban, 'branch');
    }

    private static function span(?string $iban, string $part): ?string
    {
        $iban = self::normalize($iban);
        $data = IbanRegistry::get(substr($iban, 0, 2));
        $span = $data === null ? null : ($part === 'bank' ? $data['bank'] : ($data['branch'] ?? null));

        if ($span === null || strlen($iban) < $span[0] + $span[1]) {
            return null;
        }

        return substr($iban, $span[0], $span[1]);
    }

    /**
     * A valid example IBAN of the country (compact), when the country is known.
     */
    public static function example(string $country): ?string
    {
        return IbanRegistry::get($country)['example'] ?? null;
    }

    public static function rule(): IbanRule
    {
        return new IbanRule;
    }

    /**
     * Why the IBAN is not valid: one of `empty`, `format`, `unknown_country`, `length`, `structure`, `checksum`;
     * null when it is valid.
     */
    public static function problem(?string $iban): ?string
    {
        $iban = self::normalize($iban);

        if ($iban === '') {
            return 'empty';
        }

        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban) !== 1) {
            return 'format';
        }

        $data = IbanRegistry::get(substr($iban, 0, 2));

        if ($data === null) {
            return 'unknown_country';
        }

        if (in_array(substr($iban, 2, 2), ['00', '01', '99'], true)) {
            return 'checksum';
        }

        if (strlen($iban) !== $data['length']) {
            return 'length';
        }

        if (preg_match((string) IbanRegistry::bbanPattern(substr($iban, 0, 2)), substr($iban, 4)) !== 1) {
            return 'structure';
        }

        return self::mod97($iban) === 1 ? null : 'checksum';
    }

    /**
     * The ISO 7064 mod 97-10 remainder; 1 for a correct IBAN. Chunked, so it needs neither bcmath nor GMP.
     * Expects a normalized IBAN (upper case, no separators); it is normalized here defensively.
     */
    public static function mod97(string $iban): int
    {
        $iban = self::normalize($iban);
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $remainder = 0;

        foreach (str_split($rearranged) as $char) {
            $value = ctype_digit($char) ? $char : (string) (ord($char) - 55);
            $remainder = ((int) ($remainder.$value)) % 97;
        }

        return $remainder;
    }
}
