<?php

use App\Filament\Resources\TrSales\Pages\ListTrSales;
use App\Models\Customer;
use App\Models\TbStock;
use App\Models\TrDetail;
use App\Models\TrHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('renders the sales report with customer info, details and totals', function () {
    $customer = Customer::create([
        'descr' => 'Budi Santoso',
        'alamat' => 'Jl. Merdeka No. 10',
        'phone' => '081234567890',
    ]);

    $stock = TbStock::factory()->create([
        'code' => 'BRG-001',
        'descr' => 'Bollpoin',
        'satuan' => 'PCS',
    ]);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trs_date' => '2026-08-15',
        'trr_type' => 'SALE',
        'customer_id' => $customer->id,
        'trs_type' => 0,
        'total_amount' => 16000,
        'paid_amount' => 16000,
        'remaining_amount' => 0,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $sale->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.penjualan.laporan', $sale));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $html = view('laporan.penjualan', ['header' => $sale])->render();

    expect($html)->toContain('LAPORAN PENJUALAN')
        ->and($html)->toContain('PJ-000001')
        ->and($html)->toContain('Budi Santoso')
        ->and($html)->toContain('Jl. Merdeka No. 10')
        ->and($html)->toContain('081234567890')
        ->and($html)->toContain('BRG-001')
        ->and($html)->toContain('Bollpoin')
        ->and($html)->toContain('TOTAL')
        ->and($html)->not->toContain('Export Excel');
});

it('shows a dashed phone when the customer has none', function () {
    $customer = Customer::create(['descr' => 'Budi Santoso', 'phone' => null]);

    $sale = TrHeader::factory()->create([
        'trr_type' => 'SALE',
        'customer_id' => $customer->id,
    ]);

    $this->get(route('filament.admin.penjualan.laporan', $sale))
        ->assertOk();

    $html = view('laporan.penjualan', ['header' => $sale])->render();

    expect($html)->toContain('---');
});

it('prints the sales report from the cetak route', function () {
    $stock = TbStock::factory()->create(['descr' => 'Bollpoin']);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trr_type' => 'SALE',
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $sale->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.penjualan.cetak', $sale));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');

    $html = view('laporan.penjualan', ['header' => $sale])->render();

    expect($html)->toContain('LAPORAN PENJUALAN')
        ->and($html)->toContain('PJ-000001')
        ->and($html)->toContain('Bollpoin')
        ->and($html)->toContain('TOTAL')
        ->and($html)->not->toContain('Export Excel');
});

it('exports the sales report as a CSV with BOM', function () {
    $customer = Customer::create(['descr' => 'Budi Santoso']);
    $stock = TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin']);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trr_type' => 'SALE',
        'customer_id' => $customer->id,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $sale->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.penjualan.export', $sale));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('No. Transaksi')
        ->and($content)->toContain('PJ-000001')
        ->and($content)->toContain('Bollpoin')
        ->and($content)->toContain('TOTAL');
});

it('requires authentication to view the sales report', function () {
    $sale = TrHeader::factory()->create(['trr_type' => 'SALE']);

    auth()->logout();

    $this->get(route('filament.admin.penjualan.laporan', $sale))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('rejects headers that are not sales', function () {
    $purchase = TrHeader::factory()->create(['trr_type' => 'PURCHASE']);

    $this->get(route('filament.admin.penjualan.laporan', $purchase))->assertNotFound();
    $this->get(route('filament.admin.penjualan.cetak', $purchase))->assertNotFound();
    $this->get(route('filament.admin.penjualan.export', $purchase))->assertNotFound();
});

it('registers a sales report action on the table that opens the report URL', function () {
    $sale = TrHeader::factory()->create([
        'trr_type' => 'SALE',
        'trs_number' => 'PJ-000001',
        'trs_date' => now(),
    ]);

    Livewire::test(ListTrSales::class)
        ->assertTableActionHasUrl(
            'laporanPenjualan',
            route('filament.admin.penjualan.laporan', $sale),
            $sale->getKey(),
        )
        ->assertTableActionShouldOpenUrlInNewTab('laporanPenjualan', $sale->getKey());
});
