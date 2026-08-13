<?php

namespace App\Filament\Resources\TbCustomers;

use App\Filament\Resources\TbCustomers\Pages\ListTbCustomers;
use App\Filament\Resources\TbCustomers\Schemas\TbCustomerForm;
use App\Filament\Resources\TbCustomers\Tables\TbCustomersTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TbCustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'Customer';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Data Customer';

    protected static ?string $slug = 'tb-customer';

    protected static ?string $navigationLabel = 'Data Customer';

    // protected static ?string $navigationParentItem = MasterInventory::class;

    protected static string|UnitEnum|null $navigationGroup = 'Tabel';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return TbCustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TbCustomersTable::configure($table);
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
            'index' => ListTbCustomers::route('/'),
        ];
    }
}
