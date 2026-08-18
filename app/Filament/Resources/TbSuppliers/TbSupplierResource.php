<?php

namespace App\Filament\Resources\TbSuppliers;

use App\Filament\Resources\TbSuppliers\Pages\ListTbSuppliers;
use App\Filament\Resources\TbSuppliers\Schemas\TbSupplierForm;
use App\Filament\Resources\TbSuppliers\Tables\TbSuppliersTable;
use App\Models\Supplier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TbSupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'Supplier';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Data Supplier';

    protected static ?string $slug = 'tb-supplier';

    protected static ?string $navigationLabel = 'Data Supplier';

    // protected static ?string $navigationParentItem = MasterInventory::class;

    protected static string|UnitEnum|null $navigationGroup = 'Pembelian';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return TbSupplierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TbSuppliersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTbSuppliers::route('/'),
        ];
    }
}
