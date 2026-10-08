<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\Supplier;
use Workbench\App\Models\User;

/**
 * Screenshot data (`vendor/bin/testbench db:seed --class='Workbench\Database\Seeders\DemoSeeder'` after
 * `workbench:build`). Log in as emma@example.com / password. Every IBAN has valid check digits.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(['email' => 'emma@example.com'], ['name' => 'Emma Carter', 'password' => 'password']);

        $suppliers = [
            ['Kyiv Packaging Group', 'UA', 'UA543052990000026003012345678', 'Corrugated boxes, net 30'],
            ['Dnipro Pallets LLC', 'UA', 'UA033220010000026008765432101', 'EUR pallets, monthly invoice'],
            ['Warszawa Logistics Sp. z o.o.', 'PL', 'PL60102010260000123456789012', 'Road freight to Gdansk'],
            ['Poznan Labels S.A.', 'PL', 'PL47114020040000301234567890', 'Thermal labels, 60 days'],
            ['Rhein Maschinenbau GmbH', 'DE', 'DE89370400440532013000', 'Packing line spare parts'],
            ['Praha Plastics a.s.', 'CZ', 'CZ6508000000192000145399', 'Stretch film'],
            ['Iberia Fresh S.L.', 'ES', 'ES9121000418450200051332', 'Seasonal produce'],
            ['Thames Cargo Ltd', 'GB', 'GB29NWBK60161331926819', 'UK customs broker'],
        ];

        foreach ($suppliers as [$name, $country, $iban, $notes]) {
            Supplier::query()->firstOrCreate(['iban' => $iban], compact('name', 'country', 'notes'));
        }
    }
}
