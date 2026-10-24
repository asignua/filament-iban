<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support\Banks;

use Asignua\FilamentIban\Support\BankDetails;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Poland: the 8-digit bank settlement number (IBAN positions 5..12) starts with the bank code, which is 3 digits for commercial banks, 4 for cooperative banks and 5 for a few institutions such as ZUS (longest match wins). The
 * register is the NBP "Plan Numeracji Rachunków Bankowych" (tab separated, CP852; column 1 = bank code, 2 = name, 5 = settlement number, 8 = branch town, 20 = BIC).
 */
class PolandBanks extends FileBankDirectory
{
    /** Characters of CP852 for the bytes 0x80..0xFF. */
    private const string CP852_HIGH = 'ÇüéâäůćçłëŐőîŹÄĆÉĹĺôöĽľŚśÖÜŤťŁ×čáíóúĄąŽžĘę¬źČş«»░▒▓│┤ÁÂĚŞ╣║╗╝Żż┐└┴┬├─┼Ăă╚╔╩╦╠═╬¤đĐĎËďŇÍÎě┘┌█▄ŢŮ▀ÓßÔŃńňŠšŔÚŕŰýÝţ´­˝˛ˇ˘§÷¸°¨˙űŘř■ ';

    public const string URL = 'https://ewib.nbp.pl/plewibnra?dokNazwa=plewibnra.txt';

    public function minimumBanks(): int
    {
        return 200;
    }

    public function country(): string
    {
        return 'PL';
    }

    public function source(): string
    {
        return 'Narodowy Bank Polski, plan numeracji rachunków bankowych, '.self::URL;
    }

    protected function keys(string $iban): array
    {
        return [substr($iban, 4, 8), substr($iban, 4, 5), substr($iban, 4, 4), substr($iban, 4, 3)];
    }

    public function download(): array
    {
        $body = Http::timeout(60)->retry(2, 500)->get(self::URL)->throw()->body();

        $banks = $this->parse($this->toUtf8($body));

        if ($banks === []) {
            throw new RuntimeException('The NBP register contained no banks.');
        }

        return $banks;
    }

    /**
     * Keys: the full 8-digit settlement number of every row (the owner of a number can differ from the bank code it
     * starts with after a merger: 144xxxxx is PKO BP, formerly Nordea) and the 3-5 digit bank codes as the fallback
     * for numbers the register does not list. A number carries the town of its branch and the branch's BIC (the
     * bank's BIC when the branch has none); a bank code carries the first BIC found for it and no town.
     *
     * @return array<string, array<string, string>|string>
     */
    public function parse(string $text): array
    {
        $numbers = [];
        $codes = [];
        /** @var array<string, string> $bics bank code => first BIC */
        $bics = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $columns = array_map('trim', explode("\t", $line));
            $code = $columns[0];
            $name = $columns[1] ?? '';
            $bic = $this->bic($columns[19] ?? '');

            if ($name === '') {
                continue;
            }

            if (preg_match('/^\d{3,5}$/', $code) === 1) {
                $codes[$code] ??= $name;

                if ($bic !== null) {
                    $bics[$code] ??= $bic;
                }
            }

            $number = $columns[4] ?? '';

            if (preg_match('/^\d{8}$/', $number) === 1 && !isset($numbers[$number])) {
                $numbers[$number] = [$code, $name, $bic ?? '', $columns[7] ?? ''];
            }
        }

        $banks = [];

        foreach ($codes as $code => $name) {
            $banks[(string) $code] = (new BankDetails($name, $bics[$code] ?? null))->toEntry();
        }

        foreach ($numbers as $number => [$code, $name, $bic, $city]) {
            $banks[(string) $number] = (new BankDetails($name, $bic !== '' ? $bic : ($bics[$code] ?? null), $city !== '' ? $city : null))->toEntry();
        }

        ksort($banks, SORT_STRING);

        return $banks;
    }

    private function bic(string $value): ?string
    {
        return preg_match('/^[A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?$/', $value) === 1 ? $value : null;
    }

    /**
     * The register is CP852 (Latin-2 of DOS). Decoded by hand: iconv builds
     * differ in how they treat these bytes.
     */
    private function toUtf8(string $body): string
    {
        if (mb_check_encoding($body, 'UTF-8')) {
            return $body;
        }

        $high = preg_split('//u', self::CP852_HIGH, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';

        foreach (str_split($body) as $byte) {
            $code = ord($byte);
            $out .= $code < 0x80 ? $byte : ($high[$code - 0x80] ?? '?');
        }

        return $out;
    }
}
