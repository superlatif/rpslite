<?php

namespace App\Filament\Widgets;

use App\Models\TrDetail;
use App\Models\TrHeader;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SalesStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        [$todayCount, $todayAmount] = $this->salesSummary($today, $today);
        [$monthCount, $monthAmount] = $this->salesSummary($monthStart, $monthEnd);
        [$cashAmount] = $this->salesSummaryByType($monthStart, $monthEnd, 0);
        [$creditAmount] = $this->salesSummaryByType($monthStart, $monthEnd, 1);
        [$purchaseCount, $purchaseAmount] = $this->purchaseSummary($monthStart, $monthEnd);

        return [
            Stat::make('Penjualan Hari Ini', $this->formatRupiah($todayAmount))
                ->description("{$todayCount} transaksi")
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->descriptionColor('success')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Penjualan Bulan Ini', $this->formatRupiah($monthAmount))
                ->description("{$monthCount} transaksi")
                ->descriptionIcon(Heroicon::OutlinedShoppingBag)
                ->descriptionColor('primary')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('primary'),
            Stat::make('Tunai vs Kredit', $this->formatRupiah($cashAmount))
                ->description('Kredit: '.$this->formatRupiah($creditAmount))
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->descriptionColor('warning')
                ->icon(Heroicon::OutlinedArrowTrendingUp)
                ->chart([$cashAmount, $creditAmount])
                ->color('success'),
            Stat::make('Total Pembelian Bulan Ini', $this->formatRupiah($purchaseAmount))
                ->description("{$purchaseCount} transaksi")
                ->descriptionIcon(Heroicon::OutlinedShoppingBag)
                ->descriptionColor('primary')
                ->icon(Heroicon::OutlinedTruck)
                ->color('primary'),
            Stat::make('Laba Kotor Bulan Ini', $this->formatRupiah($this->grossProfit($monthStart, $monthEnd)))
                ->descriptionIcon(Heroicon::OutlinedReceiptPercent)
                ->descriptionColor('success')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success'),
        ];
    }

    /**
     * @return array{0: int, 1: float}
     */
    protected function salesSummary(string $from, string $until): array
    {
        $query = TrHeader::query()
            ->where('trr_type', 'SALE')
            ->whereDate('trs_date', '>=', $from)
            ->whereDate('trs_date', '<=', $until);

        return [$query->count(), (float) $query->sum('total_amount')];
    }

    /**
     * @return array{0: int, 1: float}
     */
    protected function purchaseSummary(string $from, string $until): array
    {
        $query = TrHeader::query()
            ->where('trr_type', 'PURCHASE')
            ->whereDate('trs_date', '>=', $from)
            ->whereDate('trs_date', '<=', $until);

        return [$query->count(), (float) $query->sum('total_amount')];
    }

    /**
     * @return array{0: float}
     */
    protected function salesSummaryByType(string $from, string $until, int $type): array
    {
        $amount = TrHeader::query()
            ->where('trr_type', 'SALE')
            ->where('trs_type', $type)
            ->whereDate('trs_date', '>=', $from)
            ->whereDate('trs_date', '<=', $until)
            ->sum('total_amount');

        return [(float) $amount];
    }

    protected function grossProfit(string $from, string $until): float
    {
        return (float) TrDetail::query()
            ->join('tr_headers', 'tr_details.tr_header_id', '=', 'tr_headers.id')
            ->where('tr_headers.trr_type', 'SALE')
            ->whereDate('tr_headers.trs_date', '>=', $from)
            ->whereDate('tr_headers.trs_date', '<=', $until)
            ->sum(DB::raw('(unit_price - hpp_at_transaction) * qty'));
    }

    protected function formatRupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
