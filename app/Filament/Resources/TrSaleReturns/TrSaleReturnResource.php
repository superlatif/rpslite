<?php

namespace App\Filament\Resources\TrSaleReturns;

use App\Filament\Resources\TrSaleReturns\Pages\ListTrSaleReturns;
use App\Filament\Resources\TrSaleReturns\Schemas\TrSaleReturnForm;
use App\Filament\Resources\TrSaleReturns\Tables\TrSaleReturnsTable;
use App\Models\TrHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TrSaleReturnResource extends Resource
{
    protected static ?string $model = TrHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $recordTitleAttribute = 'trs_number';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Retur Penjualan';

    protected static ?string $slug = 'retur-penjualan';

    protected static ?string $navigationLabel = 'Retur Penjualan';

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('trr_type', 'SALE_RET');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::query()->where('trr_type', 'SALE_RET')->count();
    }

    public static function form(Schema $schema): Schema
    {
        return TrSaleReturnForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrSaleReturnsTable::configure($table);
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
            'index' => ListTrSaleReturns::route('/'),
        ];
    }
}
