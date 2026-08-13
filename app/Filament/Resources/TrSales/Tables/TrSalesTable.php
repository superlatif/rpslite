<?php

namespace App\Filament\Resources\TrSales\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrSalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('trs_number')
                    ->label('No. Transaksi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('trs_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('customer.descr')
                    ->label('Customer'),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('trs_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => (int) $state === 1 ? 'Kredit' : 'Tunai')
                    ->color(fn (mixed $state): string => (int) $state === 1 ? 'warning' : 'success'),
                TextColumn::make('paid_amount')
                    ->label('Dibayar')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('remaining_amount')
                    ->label('Sisa')
                    ->numeric(2, '.', ',')
                    ->alignEnd()
                    ->color(fn (mixed $state): string => (float) $state > 0 ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('trs_type')
                    ->label('Jenis')
                    ->options([
                        0 => 'Tunai',
                        1 => 'Kredit',
                    ]),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
