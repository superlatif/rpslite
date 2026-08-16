<?php

namespace App\Filament\Resources\LaporanPembelians;

use App\Filament\Resources\LaporanPembelians\Pages\CreateLaporanPembelian;
use App\Filament\Resources\LaporanPembelians\Pages\EditLaporanPembelian;
use App\Filament\Resources\LaporanPembelians\Pages\ListLaporanPembelians;
use App\Filament\Resources\LaporanPembelians\Schemas\LaporanPembelianForm;
use App\Filament\Resources\LaporanPembelians\Tables\LaporanPembeliansTable;
use App\Models\LaporanPembelian;
use App\Models\TrHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LaporanPembelianResource extends Resource
{
    protected static ?string $model = TrHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Laporan Pembelian';
     protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 17;

    protected static ?string $modelLabel = 'Laporan Pembelian';

    protected static ?string $slug = 'lap-pembelian';

    protected static ?string $navigationLabel = 'Laporan Pembelian';

    public static function form(Schema $schema): Schema
    {
        return LaporanPembelianForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LaporanPembeliansTable::configure($table);
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
            'index' => ListLaporanPembelians::route('/'),
         
        ];
    }

     public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function (Builder $query) {
                $query->where('trr_type', 'PURCHASE')
                    ->orWhere('trr_type', 'PURCHASE_RET');
            });
    }
}
