<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support;

use Asignua\FilamentIban\Contracts\BankDirectory;
use Asignua\FilamentIban\Contracts\ResolvesBankDetails;
use Asignua\FilamentIban\Support\Banks\AustriaBanks;
use Asignua\FilamentIban\Support\Banks\CzechBanks;
use Asignua\FilamentIban\Support\Banks\GermanyBanks;
use Asignua\FilamentIban\Support\Banks\PolandBanks;
use Asignua\FilamentIban\Support\Banks\SwitzerlandBanks;
use Asignua\FilamentIban\Support\Banks\UkraineBanks;
use Closure;

/**
 * Registry of the bank directories by country. Ukraine and Poland ship with data; Germany, Austria, Switzerland,
 * Liechtenstein and Czechia are download-only (no data until `filament-iban:update-banks <country>` has run). Add
 * more with `app(BankDirectories::class)->register('FR', MyFrenchBanks::class)`.
 */
class BankDirectories
{
    /** @var array<string, BankDirectory|class-string<BankDirectory>|Closure(): BankDirectory> */
    private array $directories = [];

    public function __construct()
    {
        $this->register('UA', UkraineBanks::class);
        $this->register('PL', PolandBanks::class);
        $this->register('DE', GermanyBanks::class);
        $this->register('AT', AustriaBanks::class);
        $this->register('CH', SwitzerlandBanks::class);
        $this->register('LI', static fn (): BankDirectory => new SwitzerlandBanks('LI'));
        $this->register('CZ', CzechBanks::class);
    }

    /**
     * @param BankDirectory|class-string<BankDirectory>|Closure(): BankDirectory $directory
     */
    public function register(string $country, BankDirectory|string|Closure $directory): static
    {
        $this->directories[strtoupper($country)] = $directory;

        return $this;
    }

    public function for(string $country): ?BankDirectory
    {
        $country = strtoupper($country);
        $directory = $this->directories[$country] ?? null;

        if ($directory === null) {
            return null;
        }

        if (!$directory instanceof BankDirectory) {
            $directory = $directory instanceof Closure ? $directory() : app($directory);
            $this->directories[$country] = $directory;
        }

        return $directory;
    }

    /**
     * @return list<string>
     */
    public function countries(): array
    {
        return array_keys($this->directories);
    }

    /**
     * The bank name for a valid IBAN, or null when the country has no directory or the bank is unknown.
     */
    public function bankName(?string $iban): ?string
    {
        $iban = Iban::normalize($iban);

        if (!Iban::isValid($iban)) {
            return null;
        }

        return $this->for(substr($iban, 0, 2))?->bankName($iban);
    }

    /**
     * Everything the register knows about the bank of a valid IBAN (name, BIC, city, status). A directory that only
     * implements {@see BankDirectory} gives details with just the name.
     */
    public function details(?string $iban): ?BankDetails
    {
        $iban = Iban::normalize($iban);

        if (!Iban::isValid($iban)) {
            return null;
        }

        $directory = $this->for(substr($iban, 0, 2));

        if ($directory instanceof ResolvesBankDetails) {
            return $directory->bankDetails($iban);
        }

        $name = $directory?->bankName($iban);

        return $name === null || trim($name) === '' ? null : new BankDetails($name);
    }
}
