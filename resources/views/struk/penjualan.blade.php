<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $header->trs_number }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            color: #000;
        }

        .receipt {
            width: 80mm;
            margin: 0 auto;
            padding: 4mm;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .brand {
            font-weight: bold;
            font-size: 14px;
        }

        .separator {
            border: 0;
            border-top: 1px dashed #000;
            margin: 3px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 0;
            vertical-align: top;
        }

        .dim {
            font-size: 11px;
        }

        .toolbar {
            text-align: center;
            margin: 12px 0;
        }

        .toolbar button {
            font-family: inherit;
            font-size: 10px;
            padding: 8px 24px;
            cursor: pointer;
        }

        @media print {
            .toolbar {
                display: none;
            }

            @page {
                margin: 0;
            }

            body {
                margin: 0;
            }
        }
    </style>
</head>

<body>
    @php
        $customer = $header->customer;
        $details = $header->details;
        $isKredit = $header->trr_type === 'SALE' && (float) $header->paid_amount < (float) $header->total_amount;
        $money = fn(float|int|string|null $value): string => 'Rp ' . number_format((float) $value, 0, ',', '.');
    @endphp

    <div class="toolbar">
        <button onclick="window.print()">Cetak Struk</button>
    </div>

    <div class="receipt">
        <div class="center brand">{{ config('app.name') }}</div>
        <div class="center">STRUK PENJUALAN</div>
        <div class="center">No. : {{ $header->trs_number }}</div>
        <div class="center">Tgl : {{ $header->trs_date->format('d/m/Y') }}</div>
        <hr class="separator">

        <div>Yth,</div>
        <div>{{ $customer?->descr ?? 'Umum' }}</div>
        @if ($customer?->alamat)
            <div>{{ $customer->alamat }}</div>
        @endif
        @if ($customer?->phone)
            <div>Telp: {{ $customer->phone }}</div>
        @endif
        <hr class="separator">

        <table>
            <tr>
                <td>Jenis Bayar</td>
                <td class="right">: {{ $isKredit ? 'KREDIT' : 'TUNAI' }}</td>
            </tr>
            @if ($isKredit && (float) $header->remaining_amount > 0)
                <tr>
                    <td>Sisa Piutang</td>
                    <td class="right">: {{ $money($header->remaining_amount) }}</td>
                </tr>
            @endif
        </table>
        <hr class="separator">

        <table>
            <tr>
                <td style="width:50%"><strong>Barang</strong></td>
                <td class="right" style="width:15%"><strong>Qty</strong></td>
                <td class="right" style="width:35%"><strong>Jumlah</strong></td>
            </tr>
            <tr>
                <td colspan="3">
                    <hr class="separator">
                </td>
            </tr>
            @foreach ($details as $item)
                <tr>
                    <td>{{ $item->stock?->descr }}</td>
                    <td class="right">{{ (float) $item->qty }}</td>
                    <td class="right">{{ $money($item->subtotal) }}</td>
                </tr>
                <tr>
                    <td class="dim">@ {{ $money($item->unit_price) }}</td>
                    <td></td>
                    <td></td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3">
                    <hr class="separator">
                </td>
            </tr>
            <tr>
                <td><strong>TOTAL</strong></td>
                <td></td>
                <td class="right"><strong>{{ $money($header->total_amount) }}</strong></td>
            </tr>
        </table>
        <hr class="separator">

        <div class="center">*** TERIMA KASIH ***</div>
    </div>

    <script>
        window.addEventListener('load', function() {
            window.print();
        });
    </script>
</body>

</html>
