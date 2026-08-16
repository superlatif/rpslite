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
        .toolbar { text-align: center; margin: 12px 0; }
        .toolbar button {
            font-family: inherit;
            font-size: 14px;
            padding: 8px 24px;
            cursor: pointer;
        }
        .report { max-width: 210mm; margin: 0 auto; padding: 10mm; }
        .center { text-align: center; }
        .right { text-align: right; }
        .brand { font-weight: bold; font-size: 16px; }
        .title { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .separator { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
        th { background: #eee; }
        .total-row td { font-weight: bold; }
        @media print {
            .toolbar { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Cetak Laporan</button>
    </div>

    <div class="report">
        <div class="center brand">{{ config('app.name') }}</div>
        <div class="center title">LAPORAN PENJUALAN</div>
        <div class="center">Periode: {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} - {{ \Carbon\Carbon::parse($dateUntil)->format('d M Y') }}</div>
        <div class="center">Ringkasan Per: {{ $groupBy === 'customer' ? 'Customer' : 'Barang' }}</div>
        <hr class="separator">

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    @if ($groupBy === 'barang')
                        <th>Kode</th>
                    @endif
                    <th>Nama {{ $groupBy === 'customer' ? 'Customer' : 'Barang' }}</th>
                    <th class="right">Qty</th>
                    <th class="right">Omzet</th>
                    <th class="right">HPP</th>
                    <th class="right">Laba</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $index => $row)
                    <tr class="{{ ($row['nama'] ?? '') === 'Total' ? 'total-row' : '' }}">
                        <td>{{ $index + 1 }}</td>
                        @if ($groupBy === 'barang')
                            <td>{{ $row['kode'] ?? '' }}</td>
                        @endif
                        <td>{{ $row['nama'] ?? '' }}</td>
                        <td class="right">{{ $row['qty'] }}</td>
                        <td class="right">{{ number_format((float) $row['omzet'], 2, ',', '.') }}</td>
                        <td class="right">{{ number_format((float) $row['hpp'], 2, ',', '.') }}</td>
                        <td class="right">{{ number_format((float) $row['laba'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
