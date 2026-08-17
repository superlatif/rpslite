<?php

namespace App\Filament\Resources\LaporanPenjualans;

use App\Filament\Resources\LaporanPenjualans\Pages\ListLaporanPenjualans;
use App\Filament\Resources\LaporanPenjualans\Schemas\LaporanPenjualanForm;
use App\Filament\Resources\LaporanPenjualans\Tables\LaporanPenjualansTable;
use App\Models\TrHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LaporanPenjualanResource extends Resource
{
    protected static ?string $model = TrHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $recordTitleAttribute = 'Laporan Penjualan';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Laporan Penjualan';

    protected static ?string $slug = 'lap-penjualan';

    protected static ?string $navigationLabel = 'Laporan Penjualan';

    public static function form(Schema $schema): Schema
    {
        return LaporanPenjualanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LaporanPenjualansTable::configure($table);
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
            'index' => ListLaporanPenjualans::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query) {
                $query->where('trr_type', 'SALE')
                    ->orWhere('trr_type', 'SALE_RET');
            })
            ->with('details');
    }
}
