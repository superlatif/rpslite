<?php

namespace App\Filament\Resources\TrSales;

use App\Filament\Resources\TrSales\Pages\ListTrSales;
use App\Filament\Resources\TrSales\Schemas\TrSaleForm;
use App\Filament\Resources\TrSales\Tables\TrSalesTable;
use App\Models\TrHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TrSaleResource extends Resource
{
    protected static ?string $model = TrHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $recordTitleAttribute = 'trs_number';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Penjualan';

    protected static ?string $slug = 'penjualan';

    protected static ?string $navigationLabel = 'Penjualan';

    protected static string|UnitEnum|null $navigationGroup = 'Penjualan';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('trr_type', 'SALE');
    }

    public static function form(Schema $schema): Schema
    {
        return TrSaleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrSalesTable::configure($table);
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
            'index' => ListTrSales::route('/'),
        ];
    }
}
