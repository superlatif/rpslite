<?php

namespace App\Filament\Resources\TbCates\Tables;

use App\Filament\Actions\SafeDeleteAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class TbCatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('descr')
                    ->label('Kategori Barang'),
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
