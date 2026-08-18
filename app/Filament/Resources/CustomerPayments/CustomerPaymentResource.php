<?php

namespace App\Filament\Resources\CustomerPayments;

use App\Filament\Resources\CustomerPayments\Pages\ListCustomerPayments;
use App\Filament\Resources\CustomerPayments\Schemas\CustomerPaymentForm;
use App\Filament\Resources\CustomerPayments\Tables\CustomerPaymentsTable;
use App\Models\CustomerPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CustomerPaymentResource extends Resource
{
    protected static ?string $model = CustomerPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'amount';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Angsuran Piutang';

    protected static ?string $slug = 'angsuran-customer';

    protected static ?string $navigationLabel = 'Angsuran Piutang';

    protected static string|UnitEnum|null $navigationGroup = 'Penjualan';

    public static function form(Schema $schema): Schema
    {
        return CustomerPaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerPaymentsTable::configure($table);
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
            'index' => ListCustomerPayments::route('/'),
        ];
    }
}
