<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Tables\LaporanKartuStokTable;
use App\Models\TbStock;
use App\Models\TrHeader;
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
                Route::get('/penjualan/{trHeader}/struk', function (TrHeader $trHeader) {
                    abort_unless($trHeader->trr_type === 'SALE', 404);

                    $trHeader->load('customer', 'details.stock');

                    return view('struk.penjualan', ['header' => $trHeader]);
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
            })
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
