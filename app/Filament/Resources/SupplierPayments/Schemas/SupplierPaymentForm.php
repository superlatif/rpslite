<?php

namespace App\Filament\Resources\SupplierPayments\Schemas;

use App\Models\Supplier;
use App\Models\TrHeader;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class SupplierPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Grid::make(2)->schema([
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'descr')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText(
                                fn (callable $get): string => self::netBalanceHelperText($get('supplier_id'))
                            )
                            ->afterStateUpdated(function (callable $set, ?string $state): void {
                                $set('tr_header_id', null);

                                $supplier = $state ? Supplier::find($state) : null;

                                $set(
                                    'amount',
                                    $supplier ? (string) max(0, $supplier->netPayable()) : null
                                );
                            }),
                        Select::make('tr_header_id')
                            ->label('Invoice / Transaksi Kredit')
                            ->options(
                                fn (callable $get): array => self::invoiceOptions($get('supplier_id'))
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (callable $set, callable $get, ?string $state): void {
                                $supplier = Supplier::find($get('supplier_id'));

                                if (! $state) {
                                    $set(
                                        'amount',
                                        $supplier ? (string) max(0, $supplier->netPayable()) : null
                                    );

                                    return;
                                }

                                $header = TrHeader::find($state);

                                $effective = $header && $supplier
                                    ? min((float) $header->remaining_amount, max(0, $supplier->netPayable()))
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

    private static function netBalanceHelperText(mixed $supplierId): string
    {
        if (blank($supplierId)) {
            return 'Pilih supplier untuk melihat sisa hutang bersih.';
        }

        $supplier = Supplier::find($supplierId);

        if (! $supplier) {
            return '';
        }

        $net = $supplier->netPayable();
        $text = 'Sisa hutang bersih: Rp '.number_format(max(0, $net), 2, ',', '.');

        $returnCredit = $supplier->returnCredit();

        if ($returnCredit > 0) {
            $text .= ' (dikurangi retur kredit Rp '.number_format($returnCredit, 2, ',', '.').')';
        }

        return $text;
    }

    /**
     * @return array<int, string>
     */
    private static function invoiceOptions(mixed $supplierId): array
    {
        if (blank($supplierId)) {
            return [];
        }

        return TrHeader::query()
            ->where('supplier_id', $supplierId)
            ->where('trr_type', 'PURCHASE')
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
