<?php

namespace App\Filament\Resources\SupplierPayments\Tables;

use App\Models\SupplierPayment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class SupplierPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier.descr')
                    ->label('Supplier')
                    ->searchable(),
                TextColumn::make('header.trs_number')
                    ->label('No. Invoice'),
                TextColumn::make('payment_date')
                    ->label('Tanggal Bayar')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('deletePayment')
                        ->label('Hapus')
                        ->color('danger')
                        ->icon(Heroicon::OutlinedTrash)
                        ->requiresConfirmation()
                        ->databaseTransaction()
                        ->action(function (SupplierPayment $record): void {
                            $record->supplier->reversePayment(
                                (float) $record->amount,
                                $record->tr_header_id,
                            );

                            $record->delete();
                        }),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                //
            ]);
    }
}
