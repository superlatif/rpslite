<?php

namespace App\Filament\Resources\CustomerPayments\Schemas;

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
                            ->required(),
                        Select::make('tr_header_id')
                            ->label('Invoice / Transaksi Kredit')
                            ->options(fn (): array => TrHeader::query()
                                ->where('trr_type', 'SALE')
                                ->where('remaining_amount', '>', 0)
                                ->orderByDesc('trs_number')
                                ->get()
                                ->mapWithKeys(fn (TrHeader $header): array => [
                                    $header->id => $header->trs_number.' - '.($header->customer?->descr ?? '-')
                                        .' (Sisa: '.number_format((float) $header->remaining_amount, 2, '.', ',').')',
                                ])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(
                                fn (mixed $set, ?string $state): mixed => $set(
                                    'amount',
                                    (string) (TrHeader::find($state)?->remaining_amount ?? 0)
                                )
                            ),
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
}
