<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kartu Stok {{ $stock->code }} - {{ $stock->descr }}</title>
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
    <div class="report">
        <div class="center brand">{{ config('app.name') }}</div>
        <div class="center title">LAPORAN KARTU STOK</div>
        <div class="center">Kode: {{ $stock->code }}</div>
        <div class="center">{{ $stock->descr }}</div>
        <div class="center">Periode: {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} - {{ \Carbon\Carbon::parse($dateUntil)->format('d M Y') }}</div>
        <hr class="separator">

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>No. Transaksi</th>
                    <th>Keterangan</th>
                    <th class="right">Masuk</th>
                    <th class="right">Keluar</th>
                    <th class="right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $index => $row)
                    <tr class="{{ in_array($row['jenis'] ?? '', ['Stok Awal', 'Total Mutasi Masuk', 'Total Mutasi Keluar', 'Stok Akhir'], true) ? 'total-row' : '' }}">
                        <td>{{ $index + 1 }}</td>
                        <td>{{ filled($row['trs_date'] ?? null) ? \Carbon\Carbon::parse($row['trs_date'])->format('d M Y') : '' }}</td>
                        <td>{{ $row['trs_number'] ?? '' }}</td>
                        <td>{{ $row['jenis'] ?? '' }}</td>
                        <td class="right">{{ filled($row['masuk'] ?? null) ? $row['masuk'] : '' }}</td>
                        <td class="right">{{ filled($row['keluar'] ?? null) ? $row['keluar'] : '' }}</td>
                        <td class="right">{{ filled($row['saldo'] ?? null) ? $row['saldo'] : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
