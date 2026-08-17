<?php

namespace App\Filament\Resources\TrPurchaseReturns\Tables;

use App\Models\TrHeader;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TrPurchaseReturnsTable
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
                TextColumn::make('supplier.descr')
                    ->label('Supplier'),
                TextColumn::make('sourcePurchase.trs_number')
                    ->label('No. Faktur Beli')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
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
            ->defaultSort('trs_number', 'desc')
            ->filters([
                SelectFilter::make('trs_type')
                    ->label('Jenis')
                    ->options([
                        0 => 'Tunai',
                        1 => 'Kredit',
                    ]),
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'descr')
                    ->searchable()
                    ->preload(),
                Filter::make('trs_date')
                    // ->label('Tanggal Transaksi')
                    // ->indicator('test')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('Transaksi mulai dari')
                            ->default(now())
                            ->displayFormat('d-m-Y'),
                        DatePicker::make('date_until')
                            ->label('sampai dengan')
                            ->default(now())
                            ->displayFormat('d-m-Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('trs_date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('trs_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        // Jika tidak ada data yang dipilih, kembalikan null (tidak ada indikator)
                        if (! filled($data['date_from']) && ! filled($data['date_until'])) {
                            return null;
                        }

                        $indicators = [];

                        if (filled($data['date_from'])) {
                            $indicators[] = 'Mulai: '.Carbon::parse($data['date_from'])->format('d M Y');
                        }

                        if (filled($data['date_until'])) {
                            $indicators[] = 'Sampai: '.Carbon::parse($data['date_until'])->format('d M Y');
                        }

                        // Gabungkan indikator menjadi satu string
                        return implode(' - ', $indicators);
                    }),
            ])->persistFiltersInSession()
            ->recordActions([
                ActionGroup::make([
                    Action::make('laporanReturPembelian')
                        ->label('Detail Retur')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->url(fn (TrHeader $record): string => route('filament.admin.retur-pembelian.laporan', $record))
                        ->openUrlInNewTab(),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)

            ->toolbarActions([
                //
            ]);
    }
}
