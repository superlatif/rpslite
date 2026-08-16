<?php

namespace App\Filament\Pages\Tables;

use App\Models\TrDetail;
use Closure;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LaporanPenjualanTable
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
                TextColumn::make('kode')
                    ->label('Kode')
                    ->visible(fn ($livewire): bool => $livewire->group_by === 'barang'),
                TextColumn::make('nama')
                    ->label(fn ($livewire): string => $livewire->group_by === 'customer' ? 'Customer' : 'Nama Barang'),
                TextColumn::make('qty')
                    ->label('Qty')
                    ->numeric(0)
                    ->alignEnd(),
                TextColumn::make('omzet')
                    ->label('Omzet')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('hpp')
                    ->label('HPP')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('laba')
                    ->label('Laba')
                    ->numeric(2, '.', ',')
                    ->alignEnd()
                    ->weight(FontWeight::Bold),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada laporan')
            ->emptyStateDescription('Klik "Tampilkan Laporan" lalu pilih periode untuk melihat ringkasan penjualan.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildRows(string $dateFrom, string $dateUntil, string $customerId, string $groupBy): array
    {
        $details = TrDetail::query()
            ->with(['header.customer', 'stock'])
            ->whereHas(
                'header',
                function ($query) use ($dateFrom, $dateUntil, $customerId) {
                    $query->whereIn('trr_type', ['SALE', 'SALE_RET'])
                        ->whereDate('trs_date', '>=', $dateFrom)
                        ->whereDate('trs_date', '<=', $dateUntil);

                    if (filled($customerId)) {
                        $query->where('customer_id', $customerId);
                    }
                },
            )
            ->get();

        if ($details->isEmpty()) {
            return [];
        }

        if ($groupBy === 'customer') {
            $rows = $details
                ->groupBy(fn (TrDetail $detail): string => (string) $detail->header->customer_id)
                ->map(function ($items): array {
                    [$qty, $omzet, $hpp] = self::summarize($items);
                    $customer = $items->first()->header->customer;

                    return [
                        'kode' => '',
                        'nama' => $customer?->descr ?? 'Tanpa Customer',
                        'qty' => $qty,
                        'omzet' => $omzet,
                        'hpp' => $hpp,
                        'laba' => round($omzet - $hpp, 2),
                    ];
                })
                ->sortBy('nama')
                ->values();
        } else {
            $rows = $details
                ->groupBy('stock_id')
                ->map(function ($items): array {
                    [$qty, $omzet, $hpp] = self::summarize($items);
                    $stock = $items->first()->stock;

                    return [
                        'kode' => $stock?->code ?? '',
                        'nama' => $stock?->descr ?? 'Barang Dihapus',
                        'qty' => $qty,
                        'omzet' => $omzet,
                        'hpp' => $hpp,
                        'laba' => round($omzet - $hpp, 2),
                    ];
                })
                ->sortBy('nama')
                ->values();
        }

        $totalQty = $rows->sum('qty');
        $totalOmzet = $rows->sum('omzet');
        $totalHpp = $rows->sum('hpp');
        $totalLaba = $rows->sum('laba');

        return $rows
            ->push([
                'kode' => '',
                'nama' => 'Total',
                'qty' => $totalQty,
                'omzet' => $totalOmzet,
                'hpp' => $totalHpp,
                'laba' => $totalLaba,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, TrDetail>  $items
     * @return array{0: float, 1: float, 2: float}
     */
    protected static function summarize($items): array
    {
        $qty = 0.0;
        $omzet = 0.0;
        $hpp = 0.0;

        foreach ($items as $detail) {
            $direction = $detail->header->trr_type === 'SALE' ? 1 : -1;

            $qty += $direction * (float) $detail->qty;
            $omzet += $direction * (float) $detail->subtotal;
            $hpp += $direction * (float) $detail->qty * (float) $detail->hpp_at_transaction;
        }

        return [round($qty, 2), round($omzet, 2), round($hpp, 2)];
    }
}
