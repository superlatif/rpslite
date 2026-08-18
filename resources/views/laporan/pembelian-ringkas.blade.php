<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Pembelian</title>
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
        $totalPembelian = 0.0;
        $totalRetur = 0.0;
    @endphp

    <div class="report">
        <div class="center brand">{{ config('app.name') }}</div>
        <div class="center title">LAPORAN PEMBELIAN</div>
        <div class="center">Periode: {{ filled($dateFrom) ? \Carbon\Carbon::parse($dateFrom)->format('d M Y') : '-' }} - {{ filled($dateUntil) ? \Carbon\Carbon::parse($dateUntil)->format('d M Y') : '-' }}</div>
        @if (filled($supplierId))
            <div class="center">Supplier: {{ $headers->firstWhere('supplier_id', (int) $supplierId)?->supplier?->descr ?? '-' }}</div>
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
                    <th>Supplier</th>
                    <th>Jenis</th>
                    <th class="right">Pembelian</th>
                    <th class="right">Retur</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($headers as $index => $header)
                    @php
                        $debet = str_starts_with($header->trs_number, 'PB') ? (float) $header->total_amount : 0;
                        $kredit = str_starts_with($header->trs_number, 'RPB') ? (float) $header->total_amount : 0;
                        $totalPembelian += $debet;
                        $totalRetur += $kredit;
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $header->trs_number }}</td>
                        <td>{{ $header->trs_date->format('d M Y') }}</td>
                        <td>{{ $header->trr_type === 'PURCHASE' ? 'Pembelian' : 'Retur Pembelian' }}</td>
                        <td>{{ $header->supplier?->descr ?? '-' }}</td>
                        <td>{{ (int) $header->trs_type === 1 ? 'Kredit' : 'Tunai' }}</td>
                        <td class="right">{{ $debet > 0 ? $money($debet) : '' }}</td>
                        <td class="right">{{ $kredit > 0 ? $money($kredit) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="6">TOTAL</td>
                    <td class="right">{{ $money($totalPembelian) }}</td>
                    <td class="right">{{ $money($totalRetur) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>