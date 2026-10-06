<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support\Banks;

use Asignua\FilamentIban\Contracts\BankDirectory;
use Asignua\FilamentIban\Contracts\DownloadsBanks;
use Asignua\FilamentIban\Support\Iban;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * A bank directory backed by a PHP data file `<country>.php` that returns
 * `['source' => …, 'fetched_at' => …, 'banks' => [code => name]]`. The file in `filament-iban.banks_path`
 * (written by `filament-iban:update-banks`, default `storage/app/filament-iban/banks`) wins over the one shipped with the package.
 */
abstract class FileBankDirectory implements BankDirectory, DownloadsBanks
{
    /** @var array<string, string>|null */
    private ?array $banks = null;

    /**
     * The register keys to try for this IBAN (slices of the compact IBAN), most specific first.
     *
     * @return list<string>
     */
    abstract protected function keys(string $iban): array;

    public function bankName(string $iban): ?string
    {
        $banks = $this->banks();

        foreach ($this->keys(Iban::normalize($iban)) as $key) {
            if (isset($banks[$key])) {
                return $banks[$key];
            }
        }

        return null;
    }

    public function forget(): void
    {
        $this->banks = null;
    }

    /**
     * @return array<string, string>
     */
    public function banks(): array
    {
        return $this->banks ??= $this->load()['banks'];
    }

    /**
     * @return array{source: string, fetched_at: ?string, banks: array<string, string>}
     */
    public function load(): array
    {
        foreach ([self::overridePath($this->country()), self::shippedPath($this->country())] as $path) {
            if (!File::isFile($path)) {
                continue;
            }

            try {
                /** @var mixed $data */
                $data = require $path;
            } catch (Throwable) {
                // A broken or half-written override must not take the shipped data down with it.
                continue;
            }

            if (is_array($data) && is_array($data['banks'] ?? null)) {
                /** @var array{source?: string, fetched_at?: ?string, banks: array<string, string>} $data */

                return [
                    'source' => $data['source'] ?? '',
                    'fetched_at' => $data['fetched_at'] ?? null,
                    'banks' => $data['banks'],
                ];
            }
        }

        return ['source' => '', 'fetched_at' => null, 'banks' => []];
    }

    public static function shippedPath(string $country): string
    {
        return __DIR__.'/../../../resources/data/banks/'.strtolower($country).'.php';
    }

    public static function overridePath(string $country): string
    {
        $dir = config('filament-iban.banks_path');
        $dir = is_string($dir) && $dir !== '' ? $dir : storage_path('app/filament-iban/banks');

        return rtrim($dir, '/').'/'.strtolower($country).'.php';
    }
}
