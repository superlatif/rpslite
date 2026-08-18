<?php

namespace App\Filament\Resources\TrPurchaseReturns;

use App\Filament\Resources\TrPurchaseReturns\Pages\ListTrPurchaseReturns;
use App\Filament\Resources\TrPurchaseReturns\Schemas\TrPurchaseReturnForm;
use App\Filament\Resources\TrPurchaseReturns\Tables\TrPurchaseReturnsTable;
use App\Models\TrHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TrPurchaseReturnResource extends Resource
{
    protected static ?string $model = TrHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static ?string $recordTitleAttribute = 'trs_number';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Retur Pembelian';

    protected static ?string $slug = 'retur-pembelian';

    protected static ?string $navigationLabel = 'Retur Pembelian';

    protected static string|UnitEnum|null $navigationGroup = 'Pembelian';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('trr_type', 'PURCHASE_RET');
    }

    public static function form(Schema $schema): Schema
    {
        return TrPurchaseReturnForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrPurchaseReturnsTable::configure($table);
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
            'index' => ListTrPurchaseReturns::route('/'),
        ];
    }
}
