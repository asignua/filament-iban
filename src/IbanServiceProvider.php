<?php

declare(strict_types=1);

namespace Asignua\FilamentIban;

use Asignua\FilamentIban\Console\UpdateBanksCommand;
use Asignua\FilamentIban\Support\BankDirectories;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IbanServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-iban';

    public function configurePackage(Package $package): void
    {
        // Translations live in resources/lang/<locale>/filament-iban.php and are read as
        // `__('filament-iban::filament-iban.<key>')`. Publish tag: `filament-iban-translations`.
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasCommand(UpdateBanksCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(BankDirectories::class);
    }
}
