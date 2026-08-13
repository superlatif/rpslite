<?php

namespace App\Filament\Resources\CustomerPayments\Tables;

use App\Models\CustomerPayment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class CustomerPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.descr')
                    ->label('Customer')
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
                        ->action(function (CustomerPayment $record): void {
                            if ($header = $record->header) {
                                $header->decrement('paid_amount', (float) $record->amount);
                                $header->increment('remaining_amount', (float) $record->amount);
                            }

                            $record->delete();
                        }),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                //
            ]);
    }
}
