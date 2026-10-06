<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Support;

use Asignua\FilamentIban\Contracts\BankDirectory;
use Asignua\FilamentIban\Support\Banks\PolandBanks;
use Asignua\FilamentIban\Support\Banks\UkraineBanks;
use Closure;

/**
 * Registry of the bank directories by country. Ukraine and Poland are built in; add more with
 * `app(BankDirectories::class)->register('DE', MyGermanBanks::class)`.
 */
class BankDirectories
{
    /** @var array<string, BankDirectory|class-string<BankDirectory>|Closure(): BankDirectory> */
    private array $directories = [];

    public function __construct()
    {
        $this->register('UA', UkraineBanks::class);
        $this->register('PL', PolandBanks::class);
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
}
