<?php

use App\Filament\Resources\LaporanPenjualans\Pages\ListLaporanPenjualans;
use App\Models\Customer;
use App\Models\TbStock;
use App\Models\TrDetail;
use App\Models\TrHeader;
use App\Models\User;
use App\Services\PdfReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('loads the sales summary report page', function () {
    Livewire::test(ListLaporanPenjualans::class)
        ->assertOk();
});

it('registers cetak and export excel header actions that follow the table filters', function () {
    $customer = Customer::create(['descr' => 'Budi Santoso']);

    $html = Livewire::test(ListLaporanPenjualans::class)
        ->set('tableFilters.trs_date.date_from', '2026-07-01')
        ->set('tableFilters.trs_date.date_until', '2026-07-31')
        ->set('tableFilters.customer_id.value', $customer->id)
        ->set('tableFilters.trs_type.value', '1')
        ->html();

    expect($html)->toContain('Cetak')
        ->and($html)->toContain('Export Excel')
        ->and($html)->toContain('laporan-penjualan/cetak?date_from=2026-07-01')
        ->and($html)->toContain('laporan-penjualan/export?date_from=2026-07-01')
        ->and($html)->toContain('customer_id='.$customer->id)
        ->and($html)->toContain('trs_type=1');
});

it('keeps cetak and export enabled even without a date filter', function () {
    $html = Livewire::test(ListLaporanPenjualans::class)->html();

    expect($html)->toContain('laporan-penjualan/cetak')
        ->and($html)->toContain('laporan-penjualan/export')
        ->and($html)->not->toContain('pointer-events-none');
});

it('prints the sales summary from the cetak route with omzet hpp and laba', function () {
    $customer = Customer::create(['descr' => 'Budi Santoso']);
    $stock = TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin']);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trs_date' => '2026-07-05',
        'trr_type' => 'SALE',
        'customer_id' => $customer->id,
        'total_amount' => 16000,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $sale->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.laporan-penjualan.cetak', [
        'date_from' => '2026-07-01',
        'date_until' => '2026-07-31',
    ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');

    $html = view('laporan.penjualan-ringkas', [
        'dateFrom' => '2026-07-01',
        'dateUntil' => '2026-07-31',
        'customerId' => '',
        'trsType' => '',
        'headers' => PdfReportService::penjualanRingkasHeaders('2026-07-01', '2026-07-31'),
    ])->render();

    expect($html)->toContain('LAPORAN PENJUALAN')
        ->and($html)->toContain('PJ-000001')
        ->and($html)->toContain('Budi Santoso')
        ->and($html)->toContain('Penjualan')
        ->and($html)->toContain('HPP')
        ->and($html)->toContain('Laba')
        ->and($html)->toContain('16.000,00')
        ->and($html)->toContain('10.000,00')
        ->and($html)->toContain('6.000,00')
        ->and($html)->toContain('TOTAL');
});

it('prints all sales when no dates are provided', function () {
    $stock = TbStock::factory()->create(['descr' => 'Bollpoin']);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trs_date' => '2026-07-05',
        'trr_type' => 'SALE',
        'total_amount' => 16000,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $sale->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.laporan-penjualan.cetak'));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

it('exports the sales summary as a CSV with BOM', function () {
    $customer = Customer::create(['descr' => 'Budi Santoso']);
    $stock = TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin']);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trs_date' => '2026-07-05',
        'trr_type' => 'SALE',
        'customer_id' => $customer->id,
        'total_amount' => 16000,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $sale->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.laporan-penjualan.export', [
        'date_from' => '2026-07-01',
        'date_until' => '2026-07-31',
    ]));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('No. Transaksi')
        ->and($content)->toContain('PJ-000001')
        ->and($content)->toContain('Budi Santoso')
        ->and($content)->toContain('16.000,00')
        ->and($content)->toContain('10.000,00')
        ->and($content)->toContain('6.000,00')
        ->and($content)->toContain('TOTAL');
});

it('exports all sales when no dates are provided', function () {
    $stock = TbStock::factory()->create(['descr' => 'Bollpoin']);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trs_date' => '2026-07-05',
        'trr_type' => 'SALE',
        'total_amount' => 16000,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $sale->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.laporan-penjualan.export'));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('PJ-000001')
        ->and($content)->toContain('16.000,00');
});

it('shows omzet retur hpp and laba totals across sales and returns', function () {
    $stock = TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin']);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trs_date' => '2026-07-05',
        'trr_type' => 'SALE',
        'total_amount' => 16000,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $sale->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $saleReturn = TrHeader::factory()->create([
        'trs_number' => 'RJ-000001',
        'trs_date' => '2026-07-20',
        'trr_type' => 'SALE_RET',
        'total_amount' => 8000,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $saleReturn->id,
        'stock_id' => $stock->id,
        'qty' => 1,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 8000,
    ]);

    $response = $this->get(route('filament.admin.laporan-penjualan.cetak', [
        'date_from' => '2026-07-01',
        'date_until' => '2026-07-31',
    ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $html = view('laporan.penjualan-ringkas', [
        'dateFrom' => '2026-07-01',
        'dateUntil' => '2026-07-31',
        'customerId' => '',
        'trsType' => '',
        'headers' => PdfReportService::penjualanRingkasHeaders('2026-07-01', '2026-07-31'),
    ])->render();

    expect($html)->toContain('PJ-000001')
        ->and($html)->toContain('RJ-000001')
        ->and($html)->toContain('Retur Penjualan')
        ->and($html)->toContain('6.000,00')
        ->and($html)->toContain('3.000,00');
});
