<?php

namespace App\Filament\Resources\SupplierPayments;

use App\Filament\Resources\SupplierPayments\Pages\ListSupplierPayments;
use App\Filament\Resources\SupplierPayments\Schemas\SupplierPaymentForm;
use App\Filament\Resources\SupplierPayments\Tables\SupplierPaymentsTable;
use App\Models\SupplierPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SupplierPaymentResource extends Resource
{
    protected static ?string $model = SupplierPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'amount';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'Angsuran Supplier';

    protected static ?string $slug = 'angsuran-supplier';

    protected static ?string $navigationLabel = 'Angsuran Supplier';

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierPaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupplierPaymentsTable::configure($table);
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
            'index' => ListSupplierPayments::route('/'),
        ];
    }
}
