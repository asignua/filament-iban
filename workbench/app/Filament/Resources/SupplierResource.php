<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources;

use Asignua\FilamentIban\Forms\Components\IbanInput;
use Asignua\FilamentIban\Infolists\Components\IbanEntry;
use Asignua\FilamentIban\Tables\Columns\IbanColumn;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use Workbench\App\Filament\Resources\SupplierResource\Pages\EditSupplier;
use Workbench\App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use Workbench\App\Filament\Resources\SupplierResource\Pages\ViewSupplier;
use Workbench\App\Models\Supplier;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    public const COUNTRIES = [
        'UA' => 'Ukraine',
        'PL' => 'Poland',
        'DE' => 'Germany',
        'CZ' => 'Czechia',
        'ES' => 'Spain',
        'GB' => 'United Kingdom',
        'FR' => 'France',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            Select::make('country')->options(self::COUNTRIES)->required(),
            IbanInput::make('iban')->label('IBAN')->required()->showBankName(),
            Textarea::make('notes')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('country')->formatStateUsing(fn (string $state): string => self::COUNTRIES[$state] ?? $state),
            IbanEntry::make('iban')->label('IBAN')->showBankName(),
            TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('country')->badge()->sortable(),
                IbanColumn::make('iban')->label('IBAN')->showBankName(),
                TextColumn::make('notes')->limit(40)->toggleable(),
            ])
            ->recordActions([ActionGroup::make([ViewAction::make(), EditAction::make()])])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'view' => ViewSupplier::route('/{record}'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
