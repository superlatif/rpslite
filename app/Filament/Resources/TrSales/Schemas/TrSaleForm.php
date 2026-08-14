<?php

namespace App\Filament\Resources\TrSales\Schemas;

use App\Filament\Resources\TbCustomers\Schemas\TbCustomerForm;
use App\Models\Customer;
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

class TrSaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Grid::make(3)->schema([
                        DatePicker::make('trs_date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'descr')
                            ->searchable()
                            ->preload()
                             ->createOptionForm(fn (): array => TbCustomerForm::components())
                            ->createOptionUsing(fn (array $data) => Customer::create($data)->getKey())
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                // Jika customer dipilih, biarkan trs_type bisa diubah (opsional)
                                // Jika customer dikosongkan, set trs_type ke 0
                                if (blank($state)) {
                                    $set('trs_type', 0);
                                }
                            }),
                        Select::make('trs_type')
                            ->label('Jenis Pembayaran')
                            ->options([
                                0 => 'Tunai',
                                1 => 'Kredit',
                            ])
                            ->default(0)
                            ->disabled(fn (callable $get) => blank($get('customer_id'))) // Disable jika customer kosong
                            ->dehydrated(fn (callable $get) => filled($get('customer_id'))) // Hanya kirim data ke DB jika customer ada
                            ->helperText(fn (callable $get) => blank($get('customer_id')) ? 'Pilih customer terlebih dahulu' : null)
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
                    ->label('Detail Penjualan')
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
                            ->live()

                            ->afterStateUpdated(
                                function ($state, callable $set, callable $get) {
                                    $stock = TbStock::find($state);

                                    if ($stock) {
                                        $set(
                                            'unit_price',
                                            $stock->harga_jual
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
