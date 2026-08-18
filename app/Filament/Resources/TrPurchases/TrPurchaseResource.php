<?php

namespace App\Filament\Resources\TrPurchases;

use App\Filament\Resources\TrPurchases\Pages\ListTrPurchases;
use App\Filament\Resources\TrPurchases\Schemas\TrPurchaseForm;
use App\Filament\Resources\TrPurchases\Tables\TrPurchasesTable;
use App\Models\TrHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TrPurchaseResource extends Resource
{
    protected static ?string $model = TrHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $recordTitleAttribute = 'trs_number';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Pembelian';

    protected static ?string $slug = 'pembelian';

    protected static ?string $navigationLabel = 'Pembelian';

    protected static string|UnitEnum|null $navigationGroup = 'Pembelian';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('trr_type', 'PURCHASE');
    }

    public static function form(Schema $schema): Schema
    {
        return TrPurchaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrPurchasesTable::configure($table);
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
            'index' => ListTrPurchases::route('/'),
        ];
    }
}
