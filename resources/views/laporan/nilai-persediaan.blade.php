<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Nilai Persediaan</title>
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
        <div class="center title">LAPORAN NILAI PERSEDIAAN</div>
        <div class="center">Per Tanggal: {{ now()->format('d M Y') }}</div>
        <hr class="separator">

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Satuan</th>
                    <th class="right">Stok</th>
                    <th class="right">Harga Pokok</th>
                    <th class="right">Nilai Persediaan</th>
                    <th>Kategori</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalStok = 0.0;
                    $totalNilai = 0.0;
                @endphp
                @foreach ($rows as $index => $row)
                    @php
                        $totalStok += (float) $row['stock'];
                        $totalNilai += (float) $row['nilai_persediaan'];
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $row['code'] }}</td>
                        <td>{{ $row['descr'] }}</td>
                        <td>{{ $row['satuan'] }}</td>
                        <td class="right">{{ $row['stock'] }}</td>
                        <td class="right">{{ number_format((float) $row['harga_pokok'], 2, ',', '.') }}</td>
                        <td class="right">{{ number_format((float) $row['nilai_persediaan'], 2, ',', '.') }}</td>
                        <td>{{ $row['kategori'] }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4">TOTAL</td>
                    <td class="right">{{ number_format($totalStok, 0, ',', '.') }}</td>
                    <td></td>
                    <td class="right">{{ number_format($totalNilai, 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
