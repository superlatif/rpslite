<?php

namespace App\Filament\Pages\Tables;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\TrHeader;
use Carbon\Carbon;
use Closure;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LaporanKartuPiutangTable
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
                    ->weight(FontWeight::Bold)
                    ->visible(fn ($livewire): bool => blank($livewire->customer_id)),
                TextColumn::make('trs_date')
                    ->label('Tanggal')
                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? Carbon::parse($state)->format('d M Y') : ''),
                TextColumn::make('trs_number')
                    ->label('No. Transaksi'),
                TextColumn::make('jenis')
                    ->label('Keterangan'),
                TextColumn::make('masuk')
                    ->label('Masuk')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('keluar')
                    ->label('Keluar')
                    ->numeric(2, '.', ',')
                    ->alignEnd(),
                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->numeric(2, '.', ',')
                    ->alignEnd()
                    ->weight(FontWeight::Bold),
            ])
            ->paginated(false)
            ->emptyStateHeading('Belum ada laporan')
            ->emptyStateDescription('Klik "Tampilkan Laporan" lalu pilih customer dan periode untuk melihat kartu piutang.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function buildRows(?string $customerId, string $dateFrom, string $dateUntil): array
    {
        $customers = self::customersWithActivity($customerId, $dateFrom, $dateUntil);

        $rows = [];

        foreach ($customers as $customer) {
            $opening = self::openingBalance($customer->id, $dateFrom);
            $events = self::events($customer->id, $dateFrom, $dateUntil);
            $customerLabel = $customer->descr;

            $rows[] = [
                'customer' => $customerLabel,
                'trs_date' => null,
                'trs_number' => '-',
                'jenis' => 'Saldo Awal',
                'masuk' => null,
                'keluar' => null,
                'saldo' => $opening,
            ];

            $saldo = $opening;
            $totalIn = 0.0;
            $totalOut = 0.0;

            foreach ($events as $event) {
                $delta = (float) $event['delta'];
                $saldo += $delta;

                if ($delta > 0) {
                    $totalIn += $delta;
                } else {
                    $totalOut += abs($delta);
                }

                $rows[] = [
                    'customer' => $customerLabel,
                    'trs_date' => $event['date'],
                    'trs_number' => $event['no'],
                    'jenis' => $event['jenis'],
                    'masuk' => $delta > 0 ? $delta : null,
                    'keluar' => $delta < 0 ? abs($delta) : null,
                    'saldo' => $saldo,
                ];
            }

            $rows[] = [
                'customer' => $customerLabel,
                'trs_date' => null,
                'trs_number' => null,
                'jenis' => 'Total Masuk',
                'masuk' => $totalIn,
                'keluar' => null,
                'saldo' => null,
            ];

            $rows[] = [
                'customer' => $customerLabel,
                'trs_date' => null,
                'trs_number' => null,
                'jenis' => 'Total Keluar',
                'masuk' => null,
                'keluar' => $totalOut,
                'saldo' => null,
            ];

            $rows[] = [
                'customer' => $customerLabel,
                'trs_date' => null,
                'trs_number' => null,
                'jenis' => 'Saldo Akhir',
                'masuk' => null,
                'keluar' => null,
                'saldo' => $saldo,
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, Customer>
     */
    protected static function customersWithActivity(?string $customerId, string $dateFrom, string $dateUntil): Collection
    {
        if ($customerId) {
            return collect([Customer::findOrFail($customerId)]);
        }

        $customerIds = TrHeader::query()
            ->whereIn('trr_type', ['SALE', 'SALE_RET'])
            ->where('trs_type', 1)
            ->whereDate('trs_date', '<=', $dateUntil)
            ->pluck('customer_id')
            ->merge(
                CustomerPayment::query()
                    ->whereDate('payment_date', '<=', $dateUntil)
                    ->pluck('customer_id'),
            )
            ->unique()
            ->filter();

        return Customer::query()
            ->whereIn('id', $customerIds)
            ->orderBy('descr')
            ->get();
    }

    /**
     * @return Collection<int, array{date: string, no: string, jenis: string, delta: float, sort: string}>
     */
    protected static function events(string $customerId, string $dateFrom, string $dateUntil): Collection
    {
        $sales = TrHeader::query()
            ->where('customer_id', $customerId)
            ->where('trr_type', 'SALE')
            ->where('trs_type', 1)
            ->whereDate('trs_date', '>=', $dateFrom)
            ->whereDate('trs_date', '<=', $dateUntil)
            ->get()
            ->map(fn (TrHeader $header): array => [
                'date' => $header->trs_date->format('Y-m-d'),
                'no' => $header->trs_number,
                'jenis' => 'Penjualan Kredit',
                'delta' => round((float) $header->total_amount, 2),
                'sort' => $header->trs_date->format('Y-m-d').'|0|'.$header->trs_number,
            ]);

        $returns = TrHeader::query()
            ->where('customer_id', $customerId)
            ->where('trr_type', 'SALE_RET')
            ->where('trs_type', 1)
            ->whereDate('trs_date', '>=', $dateFrom)
            ->whereDate('trs_date', '<=', $dateUntil)
            ->get()
            ->map(fn (TrHeader $header): array => [
                'date' => $header->trs_date->format('Y-m-d'),
                'no' => $header->trs_number,
                'jenis' => 'Retur Penjualan',
                'delta' => -round((float) $header->total_amount, 2),
                'sort' => $header->trs_date->format('Y-m-d').'|1|'.$header->trs_number,
            ]);

        $payments = CustomerPayment::query()
            ->where('customer_id', $customerId)
            ->whereDate('payment_date', '>=', $dateFrom)
            ->whereDate('payment_date', '<=', $dateUntil)
            ->with('header')
            ->get()
            ->map(function (CustomerPayment $payment): array {
                $number = 'PEM-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);

                return [
                    'date' => $payment->payment_date->format('Y-m-d'),
                    'no' => $number,
                    'jenis' => $payment->header ? 'Pembayaran '.$payment->header->trs_number : 'Pembayaran',
                    'delta' => -round((float) $payment->amount, 2),
                    'sort' => $payment->payment_date->format('Y-m-d').'|2|'.$number,
                ];
            });

        return $sales
            ->concat($returns)
            ->concat($payments)
            ->sortBy('sort')
            ->values();
    }

    protected static function openingBalance(string $customerId, string $dateFrom): float
    {
        $sales = (float) TrHeader::query()
            ->where('customer_id', $customerId)
            ->where('trr_type', 'SALE')
            ->where('trs_type', 1)
            ->whereDate('trs_date', '<', $dateFrom)
            ->sum('total_amount');

        $returns = (float) TrHeader::query()
            ->where('customer_id', $customerId)
            ->where('trr_type', 'SALE_RET')
            ->where('trs_type', 1)
            ->whereDate('trs_date', '<', $dateFrom)
            ->sum('total_amount');

        $payments = (float) CustomerPayment::query()
            ->where('customer_id', $customerId)
            ->whereDate('payment_date', '<', $dateFrom)
            ->sum('amount');

        return round($sales - $returns - $payments, 2);
    }
}
