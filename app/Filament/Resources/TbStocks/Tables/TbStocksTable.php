<?php

namespace App\Filament\Resources\TbStocks\Tables;

use App\Filament\Actions\SafeDeleteAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class TbStocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('gambar')
                    ->label('Gambar')
                    ->disk('public')
                    ->imageHeight(40)
                    ->square()
                    ->grow(false)
                    ->defaultImageUrl(fn (): string => 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="100%" height="100%" fill="#f3f4f6"/><g fill="#9ca3af" transform="translate(14 14)"><circle cx="6" cy="4" r="3"/><path d="M0 11 Q3 7 6 9 T12 8 L12 11 Z"/></g></svg>')),
                TextColumn::make('code')
                    ->label('Kode'),
                TextColumn::make('descr')
                    ->label('Nama Barang'),
                TextColumn::make('satuan')
                    ->label('Satuan'),
                TextColumn::make('harga_beli')
                    ->numeric()
                    ->label('Harga Beli')
                    ->alignEnd(),
                TextColumn::make('harga_jual')
                    ->numeric()
                    ->alignEnd()
                    ->label('Harga Jual'),
                TextColumn::make('cate.descr')
                    ->label('Kategori'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    SafeDeleteAction::make()
                        ->label('Hapus')
                        ->databaseTransaction()
                        ->tooltip('Hapus Data'),
                    EditAction::make()
                        ->label('Ubah')
                        ->tooltip('Ubah Data'),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
