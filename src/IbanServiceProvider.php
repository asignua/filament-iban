<?php

declare(strict_types=1);

namespace Asignua\FilamentIban;

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
            ->hasTranslations()
            ->hasViews();

        // Add a config file only when the plugin really has options: create config/filament-iban.php and
        // chain `->hasConfigFile()` here (publish tag `filament-iban-config`). Prefer fluent setters on the Plugin.
    }
}
