<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Penjualan</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
        }
        .report { margin: 0 auto; }
        .center { text-align: center; }
        .right { text-align: right; }
        .brand { font-weight: bold; font-size: 16px; }
        .title { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .separator { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
        th { background: #eee; }
        .total-row td { font-weight: bold; }
    </style>
</head>
<body>
    @php
        $money = fn (float|int|string $value): string => number_format((float) $value, 2, ',', '.');
        $totalOmzet = 0.0;
        $totalRetur = 0.0;
        $totalHpp = 0.0;
        $totalLaba = 0.0;
    @endphp

    <div class="report">
        <div class="center brand">{{ config('app.name') }}</div>
        <div class="center title">LAPORAN PENJUALAN</div>
        <div class="center">Periode: {{ filled($dateFrom) ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : '-' }} - {{ filled($dateUntil) ? \Carbon\Carbon::parse($dateUntil)->format('d M Y') : '-' }}</div>
        @if (filled($customerId))
            <div class="center">Customer: {{ $headers->firstWhere('customer_id', (int) $customerId)?->customer?->descr ?? '-' }}</div>
        @endif
        @if (filled($trsType))
            <div class="center">Jenis: {{ (int) $trsType === 1 ? 'Kredit' : 'Tunai' }}</div>
        @endif
        <hr class="separator">

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>No. Transaksi</th>
                    <th>Tanggal</th>
                    <th>Tipe</th>
                    <th>Customer</th>
                    <th>Jenis</th>
                    <th class="right">Penjualan</th>
                    <th class="right">Retur</th>
                    <th class="right">HPP</th>
                    <th class="right">Laba</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($headers as $index => $header)
                    @php
                        $omzet = $header->trr_type === 'SALE' ? (float) $header->total_amount : 0;
                        $retur = $header->trr_type === 'SALE_RET' ? (float) $header->total_amount : 0;
                        $hpp = $header->details->sum(fn ($detail): float => (float) $detail->qty * (float) $detail->hpp_at_transaction)
                            * ($header->trr_type === 'SALE_RET' ? -1 : 1);
                        $laba = $omzet - $retur - $hpp;
                        $totalOmzet += $omzet;
                        $totalRetur += $retur;
                        $totalHpp += $hpp;
                        $totalLaba += $laba;
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $header->trs_number }}</td>
                        <td>{{ $header->trs_date->format('d M Y') }}</td>
                        <td>{{ $header->trr_type === 'SALE' ? 'Penjualan' : 'Retur Penjualan' }}</td>
                        <td>{{ $header->customer?->descr ?? '-' }}</td>
                        <td>{{ (int) $header->trs_type === 1 ? 'Kredit' : 'Tunai' }}</td>
                        <td class="right">{{ $omzet > 0 ? $money($omzet) : '' }}</td>
                        <td class="right">{{ $retur > 0 ? $money($retur) : '' }}</td>
                        <td class="right">{{ $money($hpp) }}</td>
                        <td class="right">{{ $money($laba) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="6">TOTAL</td>
                    <td class="right">{{ $money($totalOmzet) }}</td>
                    <td class="right">{{ $money($totalRetur) }}</td>
                    <td class="right">{{ $money($totalHpp) }}</td>
                    <td class="right">{{ $money($totalLaba) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>