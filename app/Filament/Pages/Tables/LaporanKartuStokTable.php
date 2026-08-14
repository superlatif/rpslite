<?php

namespace App\Filament\Pages\Tables;

use App\Models\TrDetail;
use Carbon\Carbon;
use Closure;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

class LaporanKartuStokTable
{
    /**
     * @param  Closure(): array<int, array<string, mixed>>  $rows
     */
    public static function configure(Table $table, Closure $rows): Table
    {
        return $table
            ->records(function () use ($rows): LengthAwarePaginator {
                $records = $rows();

                $total = count($records);

                return new LengthAwarePaginator(
                    $records,
                    $total,
                    $total ?: 1,
                    1,
                );
            })
            ->columns([
                TextColumn::make('trs_date')
                    ->label('Tanggal')
                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? Carbon::parse($state)->format('d M Y') : ''),
                TextColumn::make('trs_number')
                    ->label('No. Transaksi'),
                TextColumn::make('jenis')
                    ->label('Keterangan'),
                TextColumn::make('masuk')
                    ->label('Masuk')
                    ->numeric(0)
                    ->alignEnd(),
                TextColumn::make('keluar')
                    ->label('Keluar')
                    ->numeric(0)
                    ->alignEnd(),
                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->numeric(0)
                    ->alignEnd()
                    ->weight(FontWeight::Bold),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada laporan')
            ->emptyStateDescription('Klik "Tampilkan Laporan" lalu pilih barang dan periode untuk melihat kartu stok.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildRows(string $stockId, string $dateFrom, string $dateUntil): array
    {
        $movements = TrDetail::query()
            ->with('header')
            ->where('stock_id', $stockId)
            ->whereHas(
                'header',
                fn ($query) => $query
                    ->whereDate('trs_date', '>=', $dateFrom)
                    ->whereDate('trs_date', '<=', $dateUntil),
            )
            ->get()
            ->sortBy(fn (TrDetail $detail): string => $detail->header->trs_date->format('Y-m-d').'|'.$detail->header->trs_number)
            ->values();

        $opening = self::openingStock($stockId, $dateFrom);

        $rows = [
            [
                'trs_date' => $dateFrom,
                'trs_number' => '-',
                'jenis' => 'Stok Awal',
                'masuk' => null,
                'keluar' => null,
                'saldo' => $opening,
            ],
        ];

        $saldo = $opening;
        $totalIn = 0.0;
        $totalOut = 0.0;

        foreach ($movements as $detail) {
            $qty = (float) $detail->qty;
            $direction = self::direction($detail->header->trr_type, $qty);
            $effectiveQty = abs($qty);

            $saldo += $direction * $effectiveQty;

            if ($direction > 0) {
                $totalIn += $effectiveQty;
            } else {
                $totalOut += $effectiveQty;
            }

            $rows[] = [
                'trs_date' => $detail->header->trs_date->format('Y-m-d'),
                'trs_number' => $detail->header->trs_number,
                'jenis' => self::jenis($detail->header->trr_type),
                'masuk' => $direction > 0 ? $effectiveQty : null,
                'keluar' => $direction < 0 ? $effectiveQty : null,
                'saldo' => $saldo,
            ];
        }

        $rows[] = [
            'trs_date' => null,
            'trs_number' => null,
            'jenis' => 'Total Mutasi Masuk',
            'masuk' => $totalIn,
            'keluar' => null,
            'saldo' => null,
        ];

        $rows[] = [
            'trs_date' => null,
            'trs_number' => null,
            'jenis' => 'Total Mutasi Keluar',
            'masuk' => null,
            'keluar' => $totalOut,
            'saldo' => null,
        ];

        $rows[] = [
            'trs_date' => null,
            'trs_number' => null,
            'jenis' => 'Stok Akhir',
            'masuk' => null,
            'keluar' => null,
            'saldo' => $saldo,
        ];

        return $rows;
    }

    public static function openingStock(string $stockId, string $dateFrom): float
    {
        return TrDetail::query()
            ->with('header')
            ->where('stock_id', $stockId)
            ->whereHas(
                'header',
                fn ($query) => $query->whereDate('trs_date', '<', $dateFrom),
            )
            ->get()
            ->sum(fn (TrDetail $detail): float => self::direction($detail->header->trr_type, (float) $detail->qty) * abs((float) $detail->qty));
    }

    public static function direction(string $trrType, float $qty = 0): int
    {
        return match ($trrType) {
            'PURCHASE', 'SALE_RET' => 1,
            'SALE', 'PURCHASE_RET' => -1,
            'OPNAME' => $qty >= 0 ? 1 : -1,
            default => 0,
        };
    }

    public static function jenis(string $trrType): string
    {
        return match ($trrType) {
            'PURCHASE' => 'Pembelian',
            'PURCHASE_RET' => 'Retur Pembelian',
            'SALE' => 'Penjualan',
            'SALE_RET' => 'Retur Penjualan',
            'OPNAME' => 'Stok Opname',
            default => $trrType,
        };
    }
}
