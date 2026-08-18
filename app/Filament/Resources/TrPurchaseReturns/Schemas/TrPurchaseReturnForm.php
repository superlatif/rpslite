<?php

namespace App\Filament\Resources\TrPurchaseReturns\Schemas;

use App\Models\TbStock;
use App\Models\TrHeader;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class TrPurchaseReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Grid::make(2)->schema([
                        DatePicker::make('trs_date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'descr')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Supplier diganti -> faktur beli sebelumnya tidak relevan lagi
                                $set('source_purchase_id', null);

                                if (blank($state)) {
                                    $set('trs_type', 0);
                                }
                            }),
                        Select::make('source_purchase_id')
                            ->label('No. Faktur Beli')
                            ->options(fn (callable $get): array => TrHeader::query()
                                ->where('trr_type', 'PURCHASE')
                                ->where('trs_type', 1)
                                ->where('remaining_amount', '>', 0)
                                ->when(
                                    filled($get('supplier_id')),
                                    fn ($query) => $query->where('supplier_id', $get('supplier_id'))
                                )
                                ->orderBy('trs_date')
                                ->orderBy('trs_number')
                                ->get()
                                ->mapWithKeys(
                                    fn (TrHeader $header): array => [
                                        $header->id => $header->trs_number.' — sisa '.number_format((float) $header->remaining_amount, 0, ',', '.'),
                                    ]
                                )
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText(fn (callable $get): ?string => filled($get('supplier_id'))
                                ? 'Pilih faktur beli kredit yang masih memiliki sisa tagihan.'
                                : 'Pilih supplier terlebih dahulu.'),
                        Select::make('trs_type')
                            ->label('Jenis Pembayaran')
                            ->options([
                                0 => 'Tunai',
                                1 => 'Kredit',
                            ])
                            ->default(0)
                            ->disabled(fn (callable $get) => blank($get('supplier_id')))
                            ->dehydrated(fn (callable $get) => filled($get('supplier_id')))
                            ->helperText(fn (callable $get): ?string => blank($get('supplier_id'))
                                ? 'Pilih supplier terlebih dahulu'
                                : ((int) ($get('trs_type') ?? 0) === 1
                                    ? 'Retur kredit mengurangi utang pada faktur beli.'
                                    : 'Retur tunai dianggap supplier mengembalikan uang tunai kepada toko.'))
                            ->required(),
                    ]),
                ])->columnSpanFull(),

                TextInput::make('total_amount')
                    ->prefix('Grand Total')
                    ->hiddenLabel()
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(',')
                    ->readOnly()
                    ->dehydrated()
                    ->default(0)
                    ->extraInputAttributes(['style' => 'text-align: right'])
                    ->columnSpanFull(),

                Repeater::make('details')
                    ->label('Detail Retur Pembelian')
                    ->columnSpanFull()
                    ->reorderable(false)
                    ->live()
                    ->afterStateUpdated(
                        fn (callable $get, callable $set) => self::calculateGrandTotal($get, $set)
                    )
                    ->table([
                        TableColumn::make('Nama Barang')
                            ->width('40%'),
                        TableColumn::make('Qty')
                            ->width('20%'),
                        TableColumn::make('Harga')
                            ->width('20%'),
                        TableColumn::make('Jumlah')
                            ->width('20%'),
                    ])
                    ->schema([

                        Select::make('stock_id')
                            ->label('Barang')
                            ->options(fn (): array => TbStock::query()
                                ->orderBy('descr')
                                ->get()
                                ->mapWithKeys(
                                    fn (TbStock $stock): array => [
                                        $stock->id => $stock->code.' - '.$stock->descr.($stock->is_jasa ? ' (Jasa)' : ''),
                                    ]
                                )
                                ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()

                            ->afterStateUpdated(
                                function ($state, callable $set, callable $get) {
                                    $stock = TbStock::find($state);

                                    if ($stock) {
                                        $set(
                                            'unit_price',
                                            $stock->harga_beli
                                        );

                                        self::calculateSubtotal($set, $get);
                                    }
                                }
                            ),

                        TextInput::make('qty')
                            ->label('Qty')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(0.01)
                            ->extraInputAttributes(['style' => 'text-align: right'])
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn (callable $set, callable $get) => self::calculateSubtotal($set, $get)
                            ),

                        TextInput::make('unit_price')
                            ->label('Harga')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->extraInputAttributes(['style' => 'text-align: right'])
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn (callable $set, callable $get) => self::calculateSubtotal($set, $get)
                            ),

                        TextInput::make('subtotal')
                            ->label('Jumlah')
                            ->numeric()
                            ->disabled()
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->extraInputAttributes(['style' => 'text-align: right'])
                            ->dehydrated()
                            ->default(0),
                    ])
                    ->columns(4)
                    ->defaultItems(1)
                    ->required()
                    ->addActionLabel('Tambah Barang'),
            ]);
    }

    protected static function calculateSubtotal(callable $set, callable $get): void
    {
        $qty = (float) ($get('qty') ?? 0);
        $price = (float) ($get('unit_price') ?? 0);

        $set(
            'subtotal',
            number_format($qty * $price, 2, '.', '')
        );
    }

    protected static function calculateGrandTotal(callable $get, callable $set): void
    {
        $rows = $get('details') ?? [];
        $total = collect($rows)->sum(
            fn ($row): float => (float) str_replace(',', '', (string) ($row['subtotal'] ?? 0))
        );
        $set('total_amount', number_format($total, 2, '.', ''));
    }
}
