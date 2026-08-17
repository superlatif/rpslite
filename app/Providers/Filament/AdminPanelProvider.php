<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Tables\LaporanKartuStokTable;
use App\Filament\Pages\Tables\LaporanNilaiPersediaanTable;
use App\Models\TbStock;
use App\Models\TrHeader;
use App\Services\ThermalPrinterService;
use Carbon\Carbon;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->sidebarCollapsibleOnDesktop()
            ->widgets([
                AccountWidget::class,
                //  FilamentInfoWidget::class,
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('/penjualan/{trHeader}/struk', function (TrHeader $trHeader, ThermalPrinterService $printer) {
                    abort_unless($trHeader->trr_type === 'SALE', 404);

                    $trHeader->load('customer', 'details.stock');

                    $data = [
                        'shop_name' => config('app.name', 'RPS Lite'),
                        'number' => $trHeader->trs_number,
                        'date' => $trHeader->trs_date->format('d/m/Y'),
                        'customer_name' => $trHeader->customer?->descr ?? 'Umum',
                        'customer_address' => $trHeader->customer?->alamat ?? '',
                        'customer_phone' => $trHeader->customer?->phone ?? '',
                        'payment_type' => (float) $trHeader->paid_amount < (float) $trHeader->total_amount ? 'KREDIT' : 'TUNAI',
                        'is_credit' => (float) $trHeader->paid_amount < (float) $trHeader->total_amount,
                        'remaining' => (float) $trHeader->remaining_amount,
                        'total' => (float) $trHeader->total_amount,
                        'items' => $trHeader->details->map(function ($item) {
                            return [
                                'name' => $item->stock?->descr ?? '-',
                                'qty' => (float) $item->qty,
                                'unit_price' => (float) $item->unit_price,
                                'subtotal' => (float) $item->subtotal,
                            ];
                        })->toArray(),
                    ];

                    $printer->printReceipt($data);

                    return response('Struk dikirim ke printer', 200)
                        ->header('Content-Type', 'text/plain');
                })->name('penjualan.struk');

                Route::get('/laporan-kartu-stok/cetak', function (Request $request) {
                    $stockId = (string) $request->query('stock_id', '');
                    $dateFrom = (string) $request->query('date_from', '');
                    $dateUntil = (string) $request->query('date_until', '');

                    abort_if(blank($stockId) || blank($dateFrom) || blank($dateUntil), 404);

                    $stock = TbStock::find($stockId);

                    abort_if(is_null($stock), 404);

                    $rows = LaporanKartuStokTable::buildRows($stockId, $dateFrom, $dateUntil);

                    return view('laporan.kartu-stok', [
                        'stock' => $stock,
                        'dateFrom' => $dateFrom,
                        'dateUntil' => $dateUntil,
                        'rows' => $rows,
                    ]);
                })->name('laporan-kartu-stok.cetak');

                Route::get('/laporan-kartu-stok/export', function (Request $request) {
                    $stockId = (string) $request->query('stock_id', '');
                    $dateFrom = (string) $request->query('date_from', '');
                    $dateUntil = (string) $request->query('date_until', '');

                    abort_if(blank($stockId) || blank($dateFrom) || blank($dateUntil), 404);

                    $stock = TbStock::find($stockId);

                    abort_if(is_null($stock), 404);

                    $rows = LaporanKartuStokTable::buildRows($stockId, $dateFrom, $dateUntil);

                    $filename = 'kartu-stok-'.($stock->code ?? $stock->id).'-'.$dateFrom.'-'.$dateUntil.'.csv';

                    $callback = function () use ($rows): void {
                        $handle = fopen('php://output', 'w');

                        fwrite($handle, "\xEF\xBB\xBF");

                        fputcsv($handle, ['Tanggal', 'No. Transaksi', 'Keterangan', 'Masuk', 'Keluar', 'Saldo']);

                        foreach ($rows as $row) {
                            fputcsv($handle, [
                                filled($row['trs_date'] ?? null) ? Carbon::parse($row['trs_date'])->format('d M Y') : '',
                                (string) ($row['trs_number'] ?? ''),
                                (string) ($row['jenis'] ?? ''),
                                filled($row['masuk'] ?? null) ? (string) $row['masuk'] : '',
                                filled($row['keluar'] ?? null) ? (string) $row['keluar'] : '',
                                filled($row['saldo'] ?? null) ? (string) $row['saldo'] : '',
                            ]);
                        }

                        fclose($handle);
                    };

                    return response()->streamDownload($callback, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                })->name('laporan-kartu-stok.export');

                Route::get('/laporan-nilai-persediaan/cetak', function (Request $request) {
                    $rows = LaporanNilaiPersediaanTable::buildRows(
                        (string) $request->query('cate_id', ''),
                        $request->query('only_available'),
                    );

                    return view('laporan.nilai-persediaan', ['rows' => $rows]);
                })->name('laporan-nilai-persediaan.cetak');

                Route::get('/laporan-nilai-persediaan/export', function (Request $request) {
                    $rows = LaporanNilaiPersediaanTable::buildRows(
                        (string) $request->query('cate_id', ''),
                        $request->query('only_available'),
                    );

                    $filename = 'laporan-nilai-persediaan-'.now()->format('Ymd-His').'.csv';

                    $callback = function () use ($rows): void {
                        $handle = fopen('php://output', 'w');

                        fwrite($handle, "\xEF\xBB\xBF");

                        fputcsv($handle, ['Kode', 'Nama Barang', 'Satuan', 'Stok', 'Harga Pokok', 'Nilai Persediaan', 'Kategori']);

                        $totalStok = 0.0;
                        $totalNilai = 0.0;

                        foreach ($rows as $row) {
                            $totalStok += (float) $row['stock'];
                            $totalNilai += (float) $row['nilai_persediaan'];

                            fputcsv($handle, [
                                $row['code'],
                                $row['descr'],
                                $row['satuan'],
                                (string) $row['stock'],
                                number_format((float) $row['harga_pokok'], 2, ',', '.'),
                                number_format((float) $row['nilai_persediaan'], 2, ',', '.'),
                                $row['kategori'],
                            ]);
                        }

                        fputcsv($handle, []);
                        fputcsv($handle, [
                            'TOTAL',
                            '',
                            '',
                            number_format($totalStok, 0, ',', '.'),
                            '',
                            number_format($totalNilai, 2, ',', '.'),
                            '',
                        ]);

                        fclose($handle);
                    };

                    return response()->streamDownload($callback, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                })->name('laporan-nilai-persediaan.export');

                Route::get('/laporan-penjualan/cetak', function (Request $request) {
                    $dateFrom = (string) $request->query('date_from', '');
                    $dateUntil = (string) $request->query('date_until', '');
                    $customerId = (string) $request->query('customer_id', '');
                    $trsType = (string) $request->query('trs_type', '');

                    $headers = TrHeader::query()
                        ->where(function ($q) {
                            $q->where('trr_type', 'SALE')
                                ->orWhere('trr_type', 'SALE_RET');
                        })
                        ->when(
                            filled($dateFrom),
                            fn ($q) => $q->whereDate('trs_date', '>=', $dateFrom)
                        )
                        ->when(
                            filled($dateUntil),
                            fn ($q) => $q->whereDate('trs_date', '<=', $dateUntil)
                        )
                        ->when(
                            filled($customerId),
                            fn ($q) => $q->where('customer_id', $customerId)
                        )
                        ->when(
                            filled($trsType) && $trsType !== '',
                            fn ($q) => $q->where('trs_type', (int) $trsType)
                        )
                        ->with('customer')
                        ->with('details')
                        ->orderBy('trs_date')
                        ->orderBy('trs_number')
                        ->get();

                    return view('laporan.penjualan-ringkas', [
                        'dateFrom' => $dateFrom,
                        'dateUntil' => $dateUntil,
                        'customerId' => $customerId,
                        'trsType' => $trsType,
                        'headers' => $headers,
                    ]);
                })->name('laporan-penjualan.cetak');

                Route::get('/laporan-penjualan/export', function (Request $request) {
                    $dateFrom = (string) $request->query('date_from', '');
                    $dateUntil = (string) $request->query('date_until', '');
                    $customerId = (string) $request->query('customer_id', '');
                    $trsType = (string) $request->query('trs_type', '');

                    $headers = TrHeader::query()
                        ->where(function ($q) {
                            $q->where('trr_type', 'SALE')
                                ->orWhere('trr_type', 'SALE_RET');
                        })
                        ->when(
                            filled($dateFrom),
                            fn ($q) => $q->whereDate('trs_date', '>=', $dateFrom)
                        )
                        ->when(
                            filled($dateUntil),
                            fn ($q) => $q->whereDate('trs_date', '<=', $dateUntil)
                        )
                        ->when(
                            filled($customerId),
                            fn ($q) => $q->where('customer_id', $customerId)
                        )
                        ->when(
                            filled($trsType) && $trsType !== '',
                            fn ($q) => $q->where('trs_type', (int) $trsType)
                        )
                        ->with('customer')
                        ->with('details')
                        ->orderBy('trs_date')
                        ->orderBy('trs_number')
                        ->get();

                    $filename = 'laporan-penjualan'.($dateFrom ? '-'.$dateFrom : '').($dateUntil ? '-'.$dateUntil : '').'.csv';

                    $callback = function () use ($headers, $dateFrom, $dateUntil): void {
                        $handle = fopen('php://output', 'w');

                        fwrite($handle, "\xEF\xBB\xBF");

                        fputcsv($handle, ['LAPORAN PENJUALAN']);

                        $periode = filled($dateFrom) || filled($dateUntil)
                            ? ($dateFrom ?: '-').' s/d '.($dateUntil ?: '-')
                            : 'Semua Tanggal';

                        fputcsv($handle, ['Periode', $periode]);
                        fputcsv($handle, []);
                        fputcsv($handle, ['No. Transaksi', 'Tanggal', 'Tipe', 'Customer', 'Jenis Bayar', 'Penjualan', 'Retur', 'HPP', 'Laba']);

                        $totalOmzet = 0.0;
                        $totalRetur = 0.0;
                        $totalHpp = 0.0;
                        $totalLaba = 0.0;

                        foreach ($headers as $header) {
                            $omzet = $header->trr_type === 'SALE' ? (float) $header->total_amount : 0.0;
                            $retur = $header->trr_type === 'SALE_RET' ? (float) $header->total_amount : 0.0;
                            $hpp = $header->details->sum(
                                fn ($detail): float => (float) $detail->qty * (float) $detail->hpp_at_transaction
                            ) * ($header->trr_type === 'SALE_RET' ? -1 : 1);
                            $laba = $omzet - $retur - $hpp;

                            $totalOmzet += $omzet;
                            $totalRetur += $retur;
                            $totalHpp += $hpp;
                            $totalLaba += $laba;

                            fputcsv($handle, [
                                $header->trs_number,
                                $header->trs_date->format('d M Y'),
                                $header->trr_type === 'SALE' ? 'Penjualan' : 'Retur Penjualan',
                                $header->customer?->descr ?? '',
                                (int) $header->trs_type === 1 ? 'Kredit' : 'Tunai',
                                $omzet > 0 ? number_format($omzet, 2, ',', '.') : '',
                                $retur > 0 ? number_format($retur, 2, ',', '.') : '',
                                number_format($hpp, 2, ',', '.'),
                                number_format($laba, 2, ',', '.'),
                            ]);
                        }

                        fputcsv($handle, []);
                        fputcsv($handle, [
                            'TOTAL',
                            '',
                            '',
                            '',
                            '',
                            number_format($totalOmzet, 2, ',', '.'),
                            number_format($totalRetur, 2, ',', '.'),
                            number_format($totalHpp, 2, ',', '.'),
                            number_format($totalLaba, 2, ',', '.'),
                        ]);

                        fclose($handle);
                    };

                    return response()->streamDownload($callback, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                })->name('laporan-penjualan.export');

                Route::get('/pembelian/{trHeader}/laporan', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'PURCHASE', 404);

                    $trHeader->load('supplier', 'details.stock');

                    return view('laporan.pembelian', ['header' => $trHeader, 'autoPrint' => false]);
                })->name('pembelian.laporan');

                Route::get('/pembelian/{trHeader}/cetak', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'PURCHASE', 404);

                    $trHeader->load('supplier', 'details.stock');

                    return view('laporan.pembelian', ['header' => $trHeader, 'autoPrint' => true]);
                })->name('pembelian.cetak');

                Route::get('/pembelian/{trHeader}/export', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'PURCHASE', 404);

                    $trHeader->load('supplier', 'details.stock');

                    $filename = 'pembelian-'.$trHeader->trs_number.'.csv';

                    $callback = function () use ($trHeader): void {
                        $handle = fopen('php://output', 'w');

                        fwrite($handle, "\xEF\xBB\xBF");

                        fputcsv($handle, ['LAPORAN PEMBELIAN']);
                        fputcsv($handle, ['No. Transaksi', 'Tanggal', 'Jenis', 'Supplier', 'Alamat', 'Phone']);
                        fputcsv($handle, [
                            $trHeader->trs_number,
                            $trHeader->trs_date->format('d M Y'),
                            (int) $trHeader->trs_type === 1 ? 'Kredit' : 'Tunai',
                            $trHeader->supplier?->descr ?? '',
                            $trHeader->supplier?->alamat ?? '',
                            $trHeader->supplier?->phone ?? '',
                        ]);
                        fputcsv($handle, []);
                        fputcsv($handle, ['Kode', 'Nama Barang', 'Satuan', 'Qty', 'Harga', 'Subtotal']);

                        $totalSubtotal = 0.0;

                        foreach ($trHeader->details as $item) {
                            $totalSubtotal += (float) $item->subtotal;

                            fputcsv($handle, [
                                $item->stock?->code ?? '',
                                $item->stock?->descr ?? '',
                                $item->stock?->satuan ?? '',
                                number_format((float) $item->qty, 2, ',', '.'),
                                number_format((float) $item->unit_price, 2, ',', '.'),
                                number_format((float) $item->subtotal, 2, ',', '.'),
                            ]);
                        }

                        fputcsv($handle, []);
                        fputcsv($handle, [
                            'TOTAL',
                            '',
                            '',
                            '',
                            '',
                            number_format($totalSubtotal, 2, ',', '.'),
                        ]);

                        fclose($handle);
                    };

                    return response()->streamDownload($callback, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                })->name('pembelian.export');

                Route::get('/penjualan/{trHeader}/laporan', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'SALE', 404);

                    $trHeader->load('customer', 'details.stock');

                    return view('laporan.penjualan', ['header' => $trHeader, 'autoPrint' => false]);
                })->name('penjualan.laporan');

                Route::get('/penjualan/{trHeader}/cetak', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'SALE', 404);

                    $trHeader->load('customer', 'details.stock');

                    return view('laporan.penjualan', ['header' => $trHeader, 'autoPrint' => true]);
                })->name('penjualan.cetak');

                Route::get('/penjualan/{trHeader}/export', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'SALE', 404);

                    $trHeader->load('customer', 'details.stock');

                    $filename = 'penjualan-'.$trHeader->trs_number.'.csv';

                    $callback = function () use ($trHeader): void {
                        $handle = fopen('php://output', 'w');

                        fwrite($handle, "\xEF\xBB\xBF");

                        fputcsv($handle, ['LAPORAN PENJUALAN']);
                        fputcsv($handle, ['No. Transaksi', 'Tanggal', 'Jenis', 'Customer', 'Alamat', 'Phone']);
                        fputcsv($handle, [
                            $trHeader->trs_number,
                            $trHeader->trs_date->format('d M Y'),
                            (int) $trHeader->trs_type === 1 ? 'Kredit' : 'Tunai',
                            $trHeader->customer?->descr ?? '',
                            $trHeader->customer?->alamat ?? '',
                            $trHeader->customer?->phone ?? '',
                        ]);
                        fputcsv($handle, []);
                        fputcsv($handle, ['Kode', 'Nama Barang', 'Satuan', 'Qty', 'Harga', 'Subtotal', 'HPP', 'Laba']);

                        $totalSubtotal = 0.0;
                        $totalHpp = 0.0;
                        $totalLaba = 0.0;

                        foreach ($trHeader->details as $item) {
                            $hpp = (float) $item->qty * (float) $item->hpp_at_transaction;
                            $laba = (float) $item->subtotal - $hpp;

                            $totalSubtotal += (float) $item->subtotal;
                            $totalHpp += $hpp;
                            $totalLaba += $laba;

                            fputcsv($handle, [
                                $item->stock?->code ?? '',
                                $item->stock?->descr ?? '',
                                $item->stock?->satuan ?? '',
                                number_format((float) $item->qty, 2, ',', '.'),
                                number_format((float) $item->unit_price, 2, ',', '.'),
                                number_format((float) $item->subtotal, 2, ',', '.'),
                                number_format($hpp, 2, ',', '.'),
                                number_format($laba, 2, ',', '.'),
                            ]);
                        }

                        fputcsv($handle, []);
                        fputcsv($handle, [
                            'TOTAL',
                            '',
                            '',
                            '',
                            '',
                            number_format($totalSubtotal, 2, ',', '.'),
                            number_format($totalHpp, 2, ',', '.'),
                            number_format($totalLaba, 2, ',', '.'),
                        ]);

                        fclose($handle);
                    };

                    return response()->streamDownload($callback, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                })->name('penjualan.export');

                Route::get('/retur-penjualan/{trHeader}/laporan', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'SALE_RET', 404);

                    $trHeader->load('customer', 'sourceSale', 'details.stock');

                    return view('laporan.retur-penjualan', ['header' => $trHeader, 'autoPrint' => false]);
                })->name('retur-penjualan.laporan');

                Route::get('/retur-penjualan/{trHeader}/cetak', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'SALE_RET', 404);

                    $trHeader->load('customer', 'sourceSale', 'details.stock');

                    return view('laporan.retur-penjualan', ['header' => $trHeader, 'autoPrint' => true]);
                })->name('retur-penjualan.cetak');

                Route::get('/retur-penjualan/{trHeader}/export', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'SALE_RET', 404);

                    $trHeader->load('customer', 'sourceSale', 'details.stock');

                    $filename = 'retur-penjualan-'.$trHeader->trs_number.'.csv';

                    $callback = function () use ($trHeader): void {
                        $handle = fopen('php://output', 'w');

                        fwrite($handle, "\xEF\xBB\xBF");

                        fputcsv($handle, ['LAPORAN RETUR PENJUALAN']);
                        fputcsv($handle, ['No. Transaksi', 'Tanggal', 'Jenis', 'No. Faktur Jual', 'Customer', 'Alamat', 'Phone']);
                        fputcsv($handle, [
                            $trHeader->trs_number,
                            $trHeader->trs_date->format('d M Y'),
                            (int) $trHeader->trs_type === 1 ? 'Kredit' : 'Tunai',
                            $trHeader->sourceSale?->trs_number ?? '',
                            $trHeader->customer?->descr ?? '',
                            $trHeader->customer?->alamat ?? '',
                            $trHeader->customer?->phone ?? '',
                        ]);
                        fputcsv($handle, []);
                        fputcsv($handle, ['Kode', 'Nama Barang', 'Satuan', 'Qty', 'Harga', 'Subtotal', 'HPP', 'Laba']);

                        $totalSubtotal = 0.0;
                        $totalHpp = 0.0;
                        $totalLaba = 0.0;

                        foreach ($trHeader->details as $item) {
                            $hpp = (float) $item->qty * (float) $item->hpp_at_transaction;
                            $laba = (float) $item->subtotal - $hpp;

                            $totalSubtotal += (float) $item->subtotal;
                            $totalHpp += $hpp;
                            $totalLaba += $laba;

                            fputcsv($handle, [
                                $item->stock?->code ?? '',
                                $item->stock?->descr ?? '',
                                $item->stock?->satuan ?? '',
                                number_format((float) $item->qty, 2, ',', '.'),
                                number_format((float) $item->unit_price, 2, ',', '.'),
                                number_format((float) $item->subtotal, 2, ',', '.'),
                                number_format($hpp, 2, ',', '.'),
                                number_format($laba, 2, ',', '.'),
                            ]);
                        }

                        fputcsv($handle, []);
                        fputcsv($handle, [
                            'TOTAL',
                            '',
                            '',
                            '',
                            '',
                            '',
                            number_format($totalSubtotal, 2, ',', '.'),
                            number_format($totalHpp, 2, ',', '.'),
                            number_format($totalLaba, 2, ',', '.'),
                        ]);

                        fclose($handle);
                    };

                    return response()->streamDownload($callback, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                })->name('retur-penjualan.export');

                Route::get('/retur-pembelian/{trHeader}/laporan', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'PURCHASE_RET', 404);

                    $trHeader->load('supplier', 'sourcePurchase', 'details.stock');

                    return view('laporan.retur-pembelian', ['header' => $trHeader, 'autoPrint' => false]);
                })->name('retur-pembelian.laporan');

                Route::get('/retur-pembelian/{trHeader}/cetak', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'PURCHASE_RET', 404);

                    $trHeader->load('supplier', 'sourcePurchase', 'details.stock');

                    return view('laporan.retur-pembelian', ['header' => $trHeader, 'autoPrint' => true]);
                })->name('retur-pembelian.cetak');

                Route::get('/retur-pembelian/{trHeader}/export', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'PURCHASE_RET', 404);

                    $trHeader->load('supplier', 'sourcePurchase', 'details.stock');

                    $filename = 'retur-pembelian-'.$trHeader->trs_number.'.csv';

                    $callback = function () use ($trHeader): void {
                        $handle = fopen('php://output', 'w');

                        fwrite($handle, "\xEF\xBB\xBF");

                        fputcsv($handle, ['LAPORAN RETUR PEMBELIAN']);
                        fputcsv($handle, ['No. Transaksi', 'Tanggal', 'Jenis', 'No. Faktur Beli', 'Supplier', 'Alamat', 'Phone']);
                        fputcsv($handle, [
                            $trHeader->trs_number,
                            $trHeader->trs_date->format('d M Y'),
                            (int) $trHeader->trs_type === 1 ? 'Kredit' : 'Tunai',
                            $trHeader->sourcePurchase?->trs_number ?? '',
                            $trHeader->supplier?->descr ?? '',
                            $trHeader->supplier?->alamat ?? '',
                            $trHeader->supplier?->phone ?? '',
                        ]);
                        fputcsv($handle, []);
                        fputcsv($handle, ['Kode', 'Nama Barang', 'Satuan', 'Qty', 'Harga', 'Subtotal']);

                        $totalSubtotal = 0.0;

                        foreach ($trHeader->details as $item) {
                            $totalSubtotal += (float) $item->subtotal;

                            fputcsv($handle, [
                                $item->stock?->code ?? '',
                                $item->stock?->descr ?? '',
                                $item->stock?->satuan ?? '',
                                number_format((float) $item->qty, 2, ',', '.'),
                                number_format((float) $item->unit_price, 2, ',', '.'),
                                number_format((float) $item->subtotal, 2, ',', '.'),
                            ]);
                        }

                        fputcsv($handle, []);
                        fputcsv($handle, [
                            'TOTAL',
                            '',
                            '',
                            '',
                            '',
                            number_format($totalSubtotal, 2, ',', '.'),
                        ]);

                        fclose($handle);
                    };

                    return response()->streamDownload($callback, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                })->name('retur-pembelian.export');

                Route::get('/laporan-pembelian/cetak', function (Request $request) {
                    $dateFrom = (string) $request->query('date_from', '');
                    $dateUntil = (string) $request->query('date_until', '');
                    $supplierId = (string) $request->query('supplier_id', '');
                    $trsType = (string) $request->query('trs_type', '');

                    $headers = TrHeader::query()
                        ->where(function ($q) {
                            $q->where('trr_type', 'PURCHASE')
                                ->orWhere('trr_type', 'PURCHASE_RET');
                        })
                        ->when(
                            filled($dateFrom),
                            fn ($q) => $q->whereDate('trs_date', '>=', $dateFrom)
                        )
                        ->when(
                            filled($dateUntil),
                            fn ($q) => $q->whereDate('trs_date', '<=', $dateUntil)
                        )
                        ->when(
                            filled($supplierId),
                            fn ($q) => $q->where('supplier_id', $supplierId)
                        )
                        ->when(
                            filled($trsType) && $trsType !== '',
                            fn ($q) => $q->where('trs_type', (int) $trsType)
                        )
                        ->with('supplier')
                        ->orderBy('trs_date')
                        ->orderBy('trs_number')
                        ->get();

                    return view('laporan.pembelian-ringkas', [
                        'dateFrom' => $dateFrom,
                        'dateUntil' => $dateUntil,
                        'supplierId' => $supplierId,
                        'trsType' => $trsType,
                        'headers' => $headers,
                    ]);
                })->name('laporan-pembelian.cetak');

                Route::get('/laporan-pembelian/export', function (Request $request) {
                    $dateFrom = (string) $request->query('date_from', '');
                    $dateUntil = (string) $request->query('date_until', '');
                    $supplierId = (string) $request->query('supplier_id', '');
                    $trsType = (string) $request->query('trs_type', '');

                    $headers = TrHeader::query()
                        ->where(function ($q) {
                            $q->where('trr_type', 'PURCHASE')
                                ->orWhere('trr_type', 'PURCHASE_RET');
                        })
                        ->when(
                            filled($dateFrom),
                            fn ($q) => $q->whereDate('trs_date', '>=', $dateFrom)
                        )
                        ->when(
                            filled($dateUntil),
                            fn ($q) => $q->whereDate('trs_date', '<=', $dateUntil)
                        )
                        ->when(
                            filled($supplierId),
                            fn ($q) => $q->where('supplier_id', $supplierId)
                        )
                        ->when(
                            filled($trsType) && $trsType !== '',
                            fn ($q) => $q->where('trs_type', (int) $trsType)
                        )
                        ->with('supplier')
                        ->orderBy('trs_date')
                        ->orderBy('trs_number')
                        ->get();

                    $filename = 'laporan-pembelian'.($dateFrom ? '-'.$dateFrom : '').($dateUntil ? '-'.$dateUntil : '').'.csv';

                    $callback = function () use ($headers, $dateFrom, $dateUntil): void {
                        $handle = fopen('php://output', 'w');

                        fwrite($handle, "\xEF\xBB\xBF");

                        fputcsv($handle, ['LAPORAN PEMBELIAN']);

                        $periode = filled($dateFrom) || filled($dateUntil)
                            ? ($dateFrom ?: '-').' s/d '.($dateUntil ?: '-')
                            : 'Semua Tanggal';

                        fputcsv($handle, ['Periode', $periode]);
                        fputcsv($handle, []);
                        fputcsv($handle, ['No. Transaksi', 'Tanggal', 'Tipe', 'Supplier', 'Jenis Bayar', 'Pembelian', 'Retur']);

                        $totalPembelian = 0.0;
                        $totalRetur = 0.0;

                        foreach ($headers as $header) {
                            $debet = str_starts_with($header->trs_number, 'PB') ? (float) $header->total_amount : 0;
                            $kredit = str_starts_with($header->trs_number, 'RPB') ? (float) $header->total_amount : 0;

                            $totalPembelian += $debet;
                            $totalRetur += $kredit;

                            fputcsv($handle, [
                                $header->trs_number,
                                $header->trs_date->format('d M Y'),
                                $header->trr_type === 'PURCHASE' ? 'Pembelian' : 'Retur Pembelian',
                                $header->supplier?->descr ?? '',
                                (int) $header->trs_type === 1 ? 'Kredit' : 'Tunai',
                                $debet > 0 ? number_format($debet, 2, ',', '.') : '',
                                $kredit > 0 ? number_format($kredit, 2, ',', '.') : '',
                            ]);
                        }

                        fputcsv($handle, []);
                        fputcsv($handle, [
                            'TOTAL',
                            '',
                            '',
                            '',
                            '',
                            number_format($totalPembelian, 2, ',', '.'),
                            number_format($totalRetur, 2, ',', '.'),
                        ]);

                        fclose($handle);
                    };

                    return response()->streamDownload($callback, $filename, [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                })->name('laporan-pembelian.export');
            })
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
