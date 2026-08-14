<?php

namespace App\Filament\Pages\Tables;

use App\Models\CustomerPayment;
use App\Models\TrHeader;
use Carbon\Carbon;
use Closure;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;

class LaporanPiutangTable
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
                TextColumn::make('customer')
                    ->label('Customer')
                    ->weight(FontWeight::Bold),
                TextColumn::make('age30')
                    ->label('0-30 Hari')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('age60')
                    ->label('31-60 Hari')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('age90')
                    ->label('61-90 Hari')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('age90p')
                    ->label('> 90 Hari')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('retur')
                    ->label('Retur Kredit')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('total')
                    ->label('Total Piutang')
                    ->numeric(2, '.', ',')
                    ->alignEnd()
                    ->weight(FontWeight::Bold),
                TextColumn::make('dibayar')
                    ->label('Total Dibayar')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('angsuran')
                    ->label('Riwayat Angsuran')
                    ->listWithLineBreaks()
                    ->wrap(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada piutang')
            ->emptyStateDescription('Klik "Tampilkan Laporan" untuk melihat sisa tagihan per customer.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildRows(string $asof, string $customerId): array
    {
        $asofDate = Carbon::parse($asof);
        $asofDateString = $asofDate->toDateString();

        $sales = TrHeader::query()
            ->where('trr_type', 'SALE')
            ->where('trs_type', 1)
            ->where('remaining_amount', '>', 0)
            ->whereDate('trs_date', '<=', $asofDateString)
            ->when(filled($customerId), fn ($query) => $query->where('customer_id', $customerId))
            ->with('customer')
            ->get();

        $returns = TrHeader::query()
            ->where('trr_type', 'SALE_RET')
            ->where('trs_type', 1)
            ->where('remaining_amount', '>', 0)
            ->whereDate('trs_date', '<=', $asofDateString)
            ->when(filled($customerId), fn ($query) => $query->where('customer_id', $customerId))
            ->with('customer')
            ->get();

        $payments = CustomerPayment::query()
            ->when(filled($customerId), fn ($query) => $query->where('customer_id', $customerId))
            ->with('customer')
            ->get();

        $byCustomer = [];

        foreach ($sales as $sale) {
            $cid = (string) $sale->customer_id;

            $outstanding = (float) $sale->remaining_amount;

            // Pembayaran yang terjadi setelah tanggal laporan belum dihitung
            // sehingga sisa tagihan dikembalikan ke kondisi per tanggal laporan.
            $outstanding += (float) $payments
                ->where('tr_header_id', $sale->id)
                ->filter(fn (CustomerPayment $payment): bool => $payment->payment_date->gt($asofDate))
                ->sum('amount');

            if ($outstanding <= 0) {
                continue;
            }

            $age = (int) abs($asofDate->diffInDays(Carbon::parse($sale->trs_date)));
            $bucket = match (true) {
                $age <= 30 => 'age30',
                $age <= 60 => 'age60',
                $age <= 90 => 'age90',
                default => 'age90p',
            };

            $byCustomer[$cid]['name'] = $sale->customer?->descr ?? 'Tanpa Customer';
            $byCustomer[$cid][$bucket] = round(($byCustomer[$cid][$bucket] ?? 0.0) + $outstanding, 2);
        }

        foreach ($returns as $return) {
            $cid = (string) $return->customer_id;

            $byCustomer[$cid]['name'] = $return->customer?->descr ?? 'Tanpa Customer';
            $byCustomer[$cid]['retur'] = round(($byCustomer[$cid]['retur'] ?? 0.0) + (float) $return->remaining_amount, 2);
        }

        foreach ($payments as $payment) {
            if ($payment->payment_date->gt($asofDate)) {
                continue;
            }

            $cid = (string) $payment->customer_id;

            $byCustomer[$cid]['name'] = $payment->customer?->descr ?? 'Tanpa Customer';
            $byCustomer[$cid]['dibayar'] = round(($byCustomer[$cid]['dibayar'] ?? 0.0) + (float) $payment->amount, 2);
            $byCustomer[$cid]['angsuran'][] = $payment->payment_date->format('d M Y').' - Rp '.number_format((float) $payment->amount, 0, ',', '.');
        }

        $rows = collect($byCustomer)
            ->map(function (array $row): array {
                $row['age30'] ??= 0.0;
                $row['age60'] ??= 0.0;
                $row['age90'] ??= 0.0;
                $row['age90p'] ??= 0.0;
                $row['retur'] ??= 0.0;
                $row['dibayar'] ??= 0.0;
                $row['angsuran'] ??= [];

                return [
                    'customer' => $row['name'],
                    'age30' => $row['age30'],
                    'age60' => $row['age60'],
                    'age90' => $row['age90'],
                    'age90p' => $row['age90p'],
                    'retur' => $row['retur'],
                    'total' => round($row['age30'] + $row['age60'] + $row['age90'] + $row['age90p'] - $row['retur'], 2),
                    'dibayar' => $row['dibayar'],
                    'angsuran' => $row['angsuran'],
                ];
            })
            ->sortBy('customer')
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        return $rows
            ->push([
                'customer' => 'Total',
                'age30' => $rows->sum('age30'),
                'age60' => $rows->sum('age60'),
                'age90' => $rows->sum('age90'),
                'age90p' => $rows->sum('age90p'),
                'retur' => $rows->sum('retur'),
                'total' => $rows->sum('total'),
                'dibayar' => $rows->sum('dibayar'),
                'angsuran' => [],
            ])
            ->all();
    }
}
