<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\SupplierResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Workbench\App\Filament\Resources\SupplierResource;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;
}
