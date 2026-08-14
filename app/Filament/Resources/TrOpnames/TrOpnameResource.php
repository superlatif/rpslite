<?php

namespace App\Filament\Resources\TrOpnames;

use App\Filament\Resources\TrOpnames\Pages\ListTrOpnames;
use App\Filament\Resources\TrOpnames\Schemas\TrOpnameForm;
use App\Filament\Resources\TrOpnames\Tables\TrOpnamesTable;
use App\Models\TrHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TrOpnameResource extends Resource
{
    protected static ?string $model = TrHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $recordTitleAttribute = 'trs_number';

    protected static ?int $navigationSort = 9;

    protected static ?string $modelLabel = 'Stok Opname';

    protected static ?string $slug = 'opname';

    protected static ?string $navigationLabel = 'Stok Opname';

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('trr_type', 'OPNAME')
            ->withCount('details');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::query()->where('trr_type', 'OPNAME')->count();
    }

    public static function form(Schema $schema): Schema
    {
        return TrOpnameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrOpnamesTable::configure($table);
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
            'index' => ListTrOpnames::route('/'),
        ];
    }
}
