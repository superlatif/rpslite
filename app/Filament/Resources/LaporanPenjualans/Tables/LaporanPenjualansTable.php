<?php

namespace App\Filament\Resources\LaporanPenjualans\Tables;

use App\Models\TrHeader;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class LaporanPenjualansTable
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

                TextColumn::make('customer.descr')
                    ->label('Customer'),

                TextColumn::make('omzet')
                    ->label('Penjualan')
                    ->alignEnd()
                    ->numeric()
                    ->money('IDR')
                    ->getStateUsing(fn (TrHeader $record): float => $record->omzet)
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->using(fn (QueryBuilder $query): float => (float) $query->clone()
                                ->where('trr_type', 'SALE')
                                ->sum('total_amount'))
                            ->money('IDR')
                    ),

                TextColumn::make('retur')
                    ->label('Retur')
                    ->alignEnd()
                    ->numeric()
                    ->money('IDR')
                    ->getStateUsing(fn (TrHeader $record): float => $record->retur)
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->using(fn (QueryBuilder $query): float => (float) $query->clone()
                                ->where('trr_type', 'SALE_RET')
                                ->sum('total_amount'))
                            ->money('IDR')
                    ),

                TextColumn::make('hpp')
                    ->label('HPP')
                    ->alignEnd()
                    ->numeric()
                    ->money('IDR')
                    ->getStateUsing(fn (TrHeader $record): float => $record->hpp)
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->using(fn (QueryBuilder $query): float => self::summarizeHpp($query))
                            ->money('IDR')
                    ),

                TextColumn::make('laba')
                    ->label('Laba')
                    ->alignEnd()
                    ->numeric()
                    ->money('IDR')
                    ->getStateUsing(fn (TrHeader $record): float => $record->laba)
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->using(fn (QueryBuilder $query): float => self::summarizeLaba($query))
                            ->money('IDR')
                    ),
            ])
            ->filters([
                SelectFilter::make('trs_type')
                    ->label('Jenis')
                    ->options([
                        '0' => 'Tunai',
                        '1' => 'Kredit',
                    ])
                    ->query(function (EloquentBuilder $query, array $data): EloquentBuilder {
                        return $query->when(
                            $data['value'] !== null && $data['value'] !== '',
                            fn (EloquentBuilder $query) => $query->where('trs_type', (int) $data['value'])
                        );
                    }),
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'descr')
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
                    ->query(function (EloquentBuilder $query, array $data): EloquentBuilder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (EloquentBuilder $query, $date): EloquentBuilder => $query->whereDate('trs_date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (EloquentBuilder $query, $date): EloquentBuilder => $query->whereDate('trs_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
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

                        return implode(' - ', $indicators);
                    }),
            ])->persistFiltersInSession()
            ->headerActions([
                Action::make('cetak')
                    ->label('Cetak')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn ($livewire): string => self::reportUrl($livewire, 'cetak'))
                    ->openUrlInNewTab(),
                Action::make('exportExcel')
                    ->label('Export Excel')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn ($livewire): string => self::reportUrl($livewire, 'export')),
            ]);
    }

    protected static function reportUrl(object $livewire, string $type): string
    {
        $trsDate = $livewire->getTableFilterState('trs_date') ?? [];
        $customerState = $livewire->getTableFilterState('customer_id') ?? [];
        $trsTypeState = $livewire->getTableFilterState('trs_type') ?? [];

        $params = [
            'date_from' => (string) ($trsDate['date_from'] ?? ''),
            'date_until' => (string) ($trsDate['date_until'] ?? ''),
            'customer_id' => (string) ($customerState['value'] ?? ''),
            'trs_type' => (string) ($trsTypeState['value'] ?? ''),
        ];

        return $type === 'cetak'
            ? route('filament.admin.laporan-penjualan.cetak', $params)
            : route('filament.admin.laporan-penjualan.export', $params);
    }

    protected static function summarizeHpp(QueryBuilder $query): float
    {
        $saleHpp = (float) $query->clone()
            ->where('trr_type', 'SALE')
            ->join('tr_details', 'tr_details.tr_header_id', '=', 'tr_headers.id')
            ->sum(DB::raw('tr_details.qty * tr_details.hpp_at_transaction'));

        $returnHpp = (float) $query->clone()
            ->where('trr_type', 'SALE_RET')
            ->join('tr_details', 'tr_details.tr_header_id', '=', 'tr_headers.id')
            ->sum(DB::raw('tr_details.qty * tr_details.hpp_at_transaction'));

        return $saleHpp - $returnHpp;
    }

    protected static function summarizeLaba(QueryBuilder $query): float
    {
        $omzet = (float) $query->clone()->where('trr_type', 'SALE')->sum('total_amount');
        $retur = (float) $query->clone()->where('trr_type', 'SALE_RET')->sum('total_amount');

        return $omzet - $retur - self::summarizeHpp($query);
    }
}
