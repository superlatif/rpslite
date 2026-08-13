<?php

namespace App\Filament\Resources\TrPurchases\Schemas;

use App\Models\TbStock;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
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
                    Grid::make(3)->schema([
                        DatePicker::make('trs_date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'descr')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('trs_type')
                            ->label('Jenis Pembayaran')
                            ->options([
                                0 => 'Tunai',
                                1 => 'Kredit',
                            ])
                            ->default(0)
                            ->required(),
                    ]),
                ])->columnSpanFull(),
                Repeater::make('details')
                    ->label('Detail Pembelian')
                    ->schema([
                        Select::make('stock_id')
                            ->label('Barang')
                            ->options(fn (): array => TbStock::query()
                                ->orderBy('descr')
                                ->get()
                                ->mapWithKeys(
                                    fn (TbStock $stock): array => [$stock->id => $stock->code.' - '.$stock->descr]
                                )
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(2)
                            ->live()
                            ->afterStateUpdated(
                                fn (mixed $set, ?string $state): mixed => $set(
                                    'unit_price',
                                    (string) (TbStock::find($state)?->harga_beli ?? 0)
                                )
                            ),
                        TextInput::make('qty')
                            ->label('Qty')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(0.01)
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(','),
                        TextInput::make('unit_price')
                            ->label('Harga')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(','),
                    ])
                    ->columns(4)
                    ->reorderableWithButtons()
                    ->collapsible()
                    ->defaultItems(1)
                    ->required()
                    ->addActionLabel('Tambah Barang'),
            ]);
    }
}
