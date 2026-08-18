<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Penjualan {{ $header->trs_number }}</title>
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
        table.info { border-collapse: collapse; }
        table.info td { border: 0; padding: 1px 5px; }
        table.info td.label { width: 90px; }
        .total-row td { font-weight: bold; }
    </style>
</head>
<body>
    @php
        $customer = $header->customer;
        $money = fn (float|int|string|null $value): string => number_format((float) $value, 2, ',', '.');
        $details = $header->details->map(function ($item) {
            $hpp = (float) $item->qty * (float) $item->hpp_at_transaction;

            return (object) [
                'item' => $item,
                'hpp' => $hpp,
                'laba' => (float) $item->subtotal - $hpp,
            ];
        });
        $totalSubtotal = $details->sum(fn ($row): float => (float) $row->item->subtotal);
        $totalHpp = $details->sum(fn ($row): float => $row->hpp);
        $totalLaba = $details->sum(fn ($row): float => $row->laba);
    @endphp

    <div class="report">
        <div class="center brand">{{ config('app.name') }}</div>
        <div class="center title">LAPORAN PENJUALAN</div>
        <div class="center">No. : {{ $header->trs_number }}</div>
        <div class="center">Tgl : {{ $header->trs_date->format('d M Y') }}</div>
        <div class="center">{{ (int) $header->trs_type === 1 ? 'KREDIT' : 'TUNAI' }}</div>
        <hr class="separator">

        <table class="info">
            <tr>
                <td class="label">Customer</td>
                <td>: {{ $customer?->descr ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Alamat</td>
                <td>: {{ $customer?->alamat ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Phone</td>
                <td>: {{ filled($customer?->phone) ? $customer->phone : '---' }}</td>
            </tr>
        </table>
        <hr class="separator">

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Nama Barang</th>
                    <th>Satuan</th>
                    <th class="right">Qty</th>
                    <th class="right">Harga</th>
                    <th class="right">Subtotal</th>
                    <th class="right">HPP</th>
                    <th class="right">Laba</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($details as $index => $row)
                    @php $item = $row->item; @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->stock?->code ?? '' }}</td>
                        <td>{{ $item->stock?->descr ?? '' }}</td>
                        <td>{{ $item->stock?->satuan ?? '' }}</td>
                        <td class="right">{{ $money($item->qty) }}</td>
                        <td class="right">{{ $money($item->unit_price) }}</td>
                        <td class="right">{{ $money($item->subtotal) }}</td>
                        <td class="right">{{ $money($row->hpp) }}</td>
                        <td class="right">{{ $money($row->laba) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="6">TOTAL</td>
                    <td class="right">{{ $money($totalSubtotal) }}</td>
                    <td class="right">{{ $money($totalHpp) }}</td>
                    <td class="right">{{ $money($totalLaba) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
