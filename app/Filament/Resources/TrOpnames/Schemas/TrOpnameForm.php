<?php

namespace App\Filament\Resources\TrOpnames\Schemas;

use App\Models\TbStock;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class TrOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    DatePicker::make('trs_date')
                        ->label('Tanggal')
                        ->required()
                        ->default(now()),
                ])->columnSpanFull(),

                Repeater::make('details')
                    ->label('Detail Opname')
                    ->columnSpanFull()
                    ->reorderable(false)
                    ->live()
                    ->table([
                        TableColumn::make('Nama Barang')
                            ->width('35%'),
                        TableColumn::make('Stok Sistem')
                            ->width('20%'),
                        TableColumn::make('Stok Fisik')
                            ->width('20%'),
                        TableColumn::make('Selisih')
                            ->width('25%'),
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
                                        $set('stok_sistem', (float) $stock->stock);
                                        self::calculateSelisih($get, $set);
                                    }
                                }
                            ),

                        TextInput::make('stok_sistem')
                            ->label('Stok Sistem')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->extraInputAttributes(['style' => 'text-align: right']),

                        TextInput::make('stok_fisik')
                            ->label('Stok Fisik')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->extraInputAttributes(['style' => 'text-align: right'])
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn (callable $set, callable $get) => self::calculateSelisih($get, $set)
                            ),

                        TextInput::make('selisih')
                            ->label('Selisih')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->extraInputAttributes(['style' => 'text-align: right'])
                            ->default(0),
                    ])
                    ->columns(4)
                    ->defaultItems(1)
                    ->required()
                    ->addActionLabel('Tambah Barang'),
            ]);
    }

    protected static function calculateSelisih(callable $get, callable $set): void
    {
        $sistem = (float) str_replace(',', '', (string) ($get('stok_sistem') ?? 0));
        $fisik = (float) str_replace(',', '', (string) ($get('stok_fisik') ?? 0));

        $set('selisih', number_format($fisik - $sistem, 2, '.', ''));
    }
}
