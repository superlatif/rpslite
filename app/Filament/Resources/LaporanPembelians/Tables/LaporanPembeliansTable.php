<?php

namespace App\Filament\Resources\LaporanPembelians\Tables;

use App\Models\TrHeader;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LaporanPembeliansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('trs_number')
                ->label('No. Transaksi')
                ->searchable(),

            TextColumn::make('trs_date')
                ->label('Tanggal')
                ->date(),

            TextColumn::make('jenis_bayar')
                ->label('Jenis Bayar'),

            TextColumn::make('debet')
                ->label('Pembelian')
                ->alignEnd()
                ->numeric()
                ->money('IDR'), // opsional: format rupiah

            TextColumn::make('kredit')
                ->label('Retur')
                ->alignEnd()
                ->numeric()
                ->money('IDR'),

            TextColumn::make('supplier.descr')
                ->label('Supplier'),
            ])
            ->filters([
               SelectFilter::make('trs_type')
                ->label('Jenis')
                ->options([
                    '0' => 'Tunai',
                    '1' => 'Kredit',
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['value'] !== null && $data['value'] !== '',
                        fn (Builder $query) => $query->where('trs_type', (int) $data['value'])
                    );
                }),
                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'descr')
                    ->searchable()
                    ->preload(),
                Filter::make('trs_date')
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
                    Action::make('laporanPembelian')
                        ->label('Detail Pembelian')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->url(fn (TrHeader $record): string => route('filament.admin.pembelian.laporan', $record))
                        ->openUrlInNewTab(),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
