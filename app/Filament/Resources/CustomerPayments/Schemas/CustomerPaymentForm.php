<?php

namespace App\Filament\Resources\CustomerPayments\Schemas;

use App\Models\Customer;
use App\Models\TrHeader;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class CustomerPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Grid::make(2)->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'descr')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText(
                                fn (callable $get): string => self::netBalanceHelperText($get('customer_id'))
                            )
                            ->afterStateUpdated(function (callable $set, ?string $state): void {
                                $set('tr_header_id', null);

                                $customer = $state ? Customer::find($state) : null;

                                $set(
                                    'amount',
                                    $customer ? (string) max(0, $customer->netReceivable()) : null
                                );
                            }),
                        Select::make('tr_header_id')
                            ->label('Invoice / Transaksi Kredit')
                            ->options(
                                fn (callable $get): array => self::invoiceOptions($get('customer_id'))
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (callable $set, callable $get, ?string $state): void {
                                $customer = Customer::find($get('customer_id'));

                                if (! $state) {
                                    $set(
                                        'amount',
                                        $customer ? (string) max(0, $customer->netReceivable()) : null
                                    );

                                    return;
                                }

                                $header = TrHeader::find($state);

                                $effective = $header && $customer
                                    ? min((float) $header->remaining_amount, max(0, $customer->netReceivable()))
                                    : 0;

                                $set('amount', (string) round($effective, 2));
                            }),
                    ]),
                ])->columnSpanFull(),
                Group::make([
                    Grid::make(2)->schema([
                        DatePicker::make('payment_date')
                            ->label('Tanggal Bayar')
                            ->required()
                            ->default(now()),
                        TextInput::make('amount')
                            ->label('Jumlah Bayar')
                            ->numeric()
                            ->required()
                            ->minValue(0.01),
                    ]),
                ])->columnSpanFull(),
            ]);
    }

    private static function netBalanceHelperText(mixed $customerId): string
    {
        if (blank($customerId)) {
            return 'Pilih customer untuk melihat sisa tagihan bersih.';
        }

        $customer = Customer::find($customerId);

        if (! $customer) {
            return '';
        }

        $net = $customer->netReceivable();
        $text = 'Sisa tagihan bersih: Rp '.number_format(max(0, $net), 2, ',', '.');

        $returnCredit = $customer->returnCredit();

        if ($returnCredit > 0) {
            $text .= ' (dikurangi retur kredit Rp '.number_format($returnCredit, 2, ',', '.').')';
        }

        return $text;
    }

    /**
     * @return array<int, string>
     */
    private static function invoiceOptions(mixed $customerId): array
    {
        if (blank($customerId)) {
            return [];
        }

        return TrHeader::query()
            ->where('customer_id', $customerId)
            ->where('trr_type', 'SALE')
            ->where('remaining_amount', '>', 0)
            ->orderBy('trs_date')
            ->orderBy('trs_number')
            ->get()
            ->mapWithKeys(fn (TrHeader $header): array => [
                $header->id => $header->trs_number
                    .' (Sisa: Rp '.number_format((float) $header->remaining_amount, 2, ',', '.').')',
            ])
            ->all();
    }
}
