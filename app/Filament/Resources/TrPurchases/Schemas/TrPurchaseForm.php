<?php

namespace App\Filament\Resources\TrPurchases\Schemas;

use App\Filament\Resources\TbStocks\Schemas\TbStockForm;
use App\Filament\Resources\TbSuppliers\Schemas\TbSupplierForm;
use App\Models\Supplier;
use App\Models\TbStock;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class TrPurchaseForm
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
                            ->createOptionForm(fn (): array => TbSupplierForm::components())
                            ->createOptionUsing(fn (array $data) => Supplier::create($data)->getKey())
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Jika supplier dipilih, biarkan trs_type bisa diubah (opsional)
                                // Jika supplier dikosongkan, set trs_type ke 0
                                if (blank($state)) {
                                    $set('trs_type', 0);
                                }
                            }),
                        // Select::make('trs_type')
                        //     ->label('Jenis Pembayaran')
                        //     ->options([
                        //         0 => 'Tunai',
                        //         1 => 'Kredit',
                        //     ])
                        //     ->default(0)
                        //     ->disabled(fn (callable $get) => blank($get('supplier_id'))) // Disable jika supplier kosong
                        //     ->dehydrated(fn (callable $get) => filled($get('supplier_id'))) // Hanya kirim data ke DB jika supplier ada
                        //     ->helperText(fn (callable $get) => blank($get('supplier_id')) ? 'Pilih supplier terlebih dahulu' : null)
                        //     ->required(),
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
                    ->extraInputAttributes(['style' => 'text-align: center'])
                    ->columnSpanFull(),

                Repeater::make('details')
                    ->label('Detail Pembelian')
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
                                        $stock->id => $stock->code.' - '.$stock->descr,
                                    ]
                                )
                                ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm(fn (Schema $schema): Schema => $schema
                                ->model(TbStock::class)
                                ->components(TbStockForm::components()))
                            ->createOptionUsing(fn (array $data) => TbStock::create($data)->getKey())
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
                            ->minValue(1)
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

                // TextInput::make('total_amount')
                //     ->prefix('Grand Total')
                //     ->hiddenLabel()
                //     ->mask(RawJs::make('$money($input)'))
                //     ->stripCharacters(',')
                //     ->readOnly()
                //     ->dehydrated()
                //     ->default(0)
                //     ->columnSpanFull(),
            ]);
    }

    protected static function updateSubtotal(callable $get, callable $set): void
    {
        $qty = floatval($get('qty') ?? 0);
        $price = floatval($get('unit_price') ?? 0);
        $set('subtotal', number_format($qty * $price, 2, '.', ''));
    }

    protected static function calculateSubtotal(
        callable $set,
        callable $get
    ): void {
        $qty = (float) ($get('qty') ?? 0);
        $price = (float) ($get('unit_price') ?? 0);

        $subtotal = $qty * $price;

        $set(
            'subtotal',
            number_format($subtotal, 2, '.', '')
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
