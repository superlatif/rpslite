<?php

namespace App\Filament\Resources\TbCates;

use App\Filament\Resources\TbCates\Pages\ListTbCates;
use App\Filament\Resources\TbCates\Schemas\TbCateForm;
use App\Filament\Resources\TbCates\Tables\TbCatesTable;
use App\Models\TbCate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TbCateResource extends Resource
{
    protected static ?string $model = TbCate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?string $recordTitleAttribute = 'Kategori';

    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'Kategori Barang';

    protected static ?string $modelLabel = 'Kategori Barang';

    protected static ?string $slug = 'tb-cate';

    protected static ?string $navigationLabel = 'Kategori Barang';

    // protected static ?string $navigationParentItem = MasterInventory::class;

    protected static string|UnitEnum|null $navigationGroup = 'Tabel';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return TbCateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TbCatesTable::configure($table);
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
            'index' => ListTbCates::route('/'),
        ];
    }
}
