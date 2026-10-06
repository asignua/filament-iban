<?php

declare(strict_types=1);

namespace Asignua\FilamentIban\Tests\Feature;

use Asignua\FilamentIban\IbanPlugin;
use Asignua\FilamentIban\Tests\TestCase;
use Filament\Facades\Filament;

class SmokeTest extends TestCase
{
    public function test_the_plugin_is_registered_on_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasPlugin('asignua-filament-iban'));
        $this->assertInstanceOf(IbanPlugin::class, $panel->getPlugin('asignua-filament-iban'));
    }

    public function test_the_translations_are_loaded(): void
    {
        $this->assertNotSame('filament-iban::filament-iban.validation.checksum', __('filament-iban::filament-iban.validation.checksum'));
    }

    public function test_the_config_and_command_are_registered(): void
    {
        $this->assertFalse(config('filament-iban.show_bank_name'));
        $this->assertArrayHasKey('filament-iban:update-banks', \Illuminate\Support\Facades\Artisan::all());
    }
}
