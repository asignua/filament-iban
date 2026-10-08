<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\SupplierResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Workbench\App\Filament\Resources\SupplierResource;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;
}
