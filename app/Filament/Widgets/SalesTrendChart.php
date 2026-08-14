<?php

namespace App\Filament\Widgets;

use App\Models\TrHeader;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

class SalesTrendChart extends ChartWidget
{
    protected ?string $heading = 'Tren Penjualan & Pembelian 30 Hari Terakhir';

    protected ?string $description = 'Total nominal transaksi penjualan dan pembelian per hari';

    protected function getData(): array
    {
        $start = now()->subDays(29)->toDateString();
        $end = now()->toDateString();

        $sales = $this->totalsByDay('SALE', $start, $end);
        $purchases = $this->totalsByDay('PURCHASE', $start, $end);

        $dates = $this->generateDateRange($start, $end);

        return [
            'datasets' => [
                [
                    'label' => 'Penjualan',
                    'data' => $dates->map(fn (string $date): float => (float) ($sales[$date] ?? 0)),
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Pembelian',
                    'data' => $dates->map(fn (string $date): float => (float) ($purchases[$date] ?? 0)),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $dates->map(fn (string $date): string => now()->parse($date)->format('d M')),
        ];
    }

    /**
     * @return array<string, float>
     */
    protected function totalsByDay(string $trrType, string $start, string $end): array
    {
        return TrHeader::query()
            ->where('trr_type', $trrType)
            ->whereDate('trs_date', '>=', $start)
            ->whereDate('trs_date', '<=', $end)
            ->selectRaw('date(trs_date) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total): float => (float) $total)
            ->all();
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return Collection<int, string>
     */
    protected function generateDateRange(string $start, string $end): Collection
    {
        return collect(iterator_to_array(
            now()->parse($start)->range(now()->parse($end)),
            false,
        ))->map(fn ($date): string => $date->toDateString());
    }
}
