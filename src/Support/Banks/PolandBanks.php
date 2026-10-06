<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support\Banks;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Poland: the 8-digit bank settlement number (IBAN positions 5..12) starts with the bank code, which is 3 digits for commercial banks, 4 for cooperative banks and 5 for a few institutions such as ZUS (longest match wins). The
 * register is the NBP "Plan Numeracji Rachunków Bankowych" (tab separated, CP852; column 1 = bank code, 2 = name).
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
     * for numbers the register does not list.
     *
     * @return array<string, string>
     */
    public function parse(string $text): array
    {
        $numbers = [];
        $codes = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $columns = explode("\t", $line);
            $code = trim($columns[0]);
            $name = trim($columns[1] ?? '');
            $number = trim($columns[4] ?? '');

            if ($name === '') {
                continue;
            }

            if (preg_match('/^\d{3,5}$/', $code) === 1 && !isset($codes[$code])) {
                $codes[$code] = $name;
            }

            if (preg_match('/^\d{8}$/', $number) === 1 && !isset($numbers[$number])) {
                $numbers[$number] = $name;
            }
        }

        $banks = $codes + $numbers;
        ksort($banks, SORT_STRING);

        return $banks;
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
