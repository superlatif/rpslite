<?php

namespace App\Filament\Resources\TbStocks\Schemas;

use App\Filament\Resources\TbCates\Schemas\TbCateForm;
use App\Models\TbCate;
use App\Models\TbStock;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class TbStockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Grid::make(3)->schema([
                        FileUpload::make('gambar')
                            ->label('Gambar Item')
                            ->disk('public')
                            ->directory('items')
                            ->image()
                            ->imageEditor()
                            ->maxSize(2048)
                            ->automaticallyResizeImagesMode('cover')
                            ->automaticallyResizeImagesToWidth('400')
                            ->automaticallyResizeImagesToHeight('400'),
                        TextInput::make('code')
                            ->label('Kode Barang')
                            ->readOnly()
                            ->default(static function (): string {
                                $lastCode = TbStock::query()
                                    ->whereRaw('LENGTH(code) = 8')
                                    ->orderByDesc('code')
                                    ->value('code');

                                return str_pad(
                                    (string) ((int) ($lastCode ?? 0) + 1),
                                    8,
                                    '0',
                                    STR_PAD_LEFT
                                );
                            }),
                        Select::make('tb_cate_id')
                            ->label('Kategori')
                            ->relationship('cate', 'descr')
                            ->columnSpan(2)
                            ->searchable()
                            ->preload()
                            ->createOptionForm(fn (): array => TbCateForm::components())
                            ->createOptionUsing(fn (array $data) => TbCate::create($data)->getKey()),
                    ]),
                ])->columnSpanFull(),
                TextInput::make('descr')
                    ->columnSpanFull()
                    ->label('Nama Barang')
                    ->required(),
                Group::make([
                    Grid::make(3)->schema([
                        TextInput::make('satuan')
                            ->label('Satuan')
                            ->required()
                            ->default('PCS'),
                        TextInput::make('harga_beli')
                            ->label('Harga Beli')
                            ->default(0)
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->numeric(),
                        TextInput::make('harga_jual')
                            ->label('Harga Jual')
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->default(0)
                            ->numeric(),
                    ])])->columnSpanFull(),
            ]);
    }
}
