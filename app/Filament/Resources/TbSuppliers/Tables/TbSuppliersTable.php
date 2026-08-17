<?php

namespace App\Filament\Resources\TbSuppliers\Tables;

use App\Filament\Actions\SafeDeleteAction;
use App\Filament\Actions\SafeDeleteBulkAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class TbSuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('descr')
                    ->label('Nama Supplier'),
                TextColumn::make('alamat')
                    ->label('Alamat'),
                TextColumn::make('phone')
                    ->label('No. HP / WA'),
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
                    SafeDeleteBulkAction::make(),
                ]),
            ]);
    }
}
