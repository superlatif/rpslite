<?php

namespace App\Filament\Resources\TbStocks;

use App\Filament\Resources\TbStocks\Pages\ListTbStocks;
use App\Filament\Resources\TbStocks\Schemas\TbStockForm;
use App\Filament\Resources\TbStocks\Tables\TbStocksTable;
use App\Models\TbStock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TbStockResource extends Resource
{
    protected static ?string $model = TbStock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $recordTitleAttribute = 'Data Barang';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Data Barang';

    protected static ?string $slug = 'tb-barang';

    protected static ?string $navigationLabel = 'Data Barang';

    // protected static ?string $navigationParentItem = MasterInventory::class;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return TbStockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TbStocksTable::configure($table);
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
            'index' => ListTbStocks::route('/'),
        ];
    }
}
