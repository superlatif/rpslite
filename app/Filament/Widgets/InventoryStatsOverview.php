<?php

namespace App\Filament\Widgets;

use App\Models\TbStock;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class InventoryStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalItems = TbStock::query()->count();
        $totalQty = (float) TbStock::query()->sum('stock');
        $stockValue = (float) TbStock::query()->sum(DB::raw('stock * harga_pokok'));

        return [
            Stat::make('Total Item', number_format($totalItems, 0, ',', '.'))
                ->description(number_format($totalQty, 0, ',', '.').' total qty')
                ->descriptionIcon(Heroicon::OutlinedArchiveBox)
                ->descriptionColor('primary')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->color('primary'),
            Stat::make('Nilai Stok', $this->formatRupiah($stockValue))
                ->description('SUM(stock × harga_pokok)')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->descriptionColor('success')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->color('success'),
        ];
    }

    protected function formatRupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
