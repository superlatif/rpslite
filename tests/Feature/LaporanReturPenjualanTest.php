<?php

use App\Filament\Resources\TrSaleReturns\Pages\ListTrSaleReturns;
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

it('renders the sale return report with customer, source invoice, details and totals', function () {
    $customer = Customer::create([
        'descr' => 'Budi Santoso',
        'alamat' => 'Jl. Merdeka No. 10',
        'phone' => '081234567890',
    ]);

    $source = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trr_type' => 'SALE',
        'customer_id' => $customer->id,
        'trs_type' => 1,
        'remaining_amount' => 16000,
    ]);

    $stock = TbStock::factory()->create([
        'code' => 'BRG-001',
        'descr' => 'Bollpoin',
        'satuan' => 'PCS',
    ]);

    $return = TrHeader::factory()->create([
        'trs_number' => 'RPJ-000001',
        'trs_date' => '2026-08-15',
        'trr_type' => 'SALE_RET',
        'customer_id' => $customer->id,
        'source_sale_id' => $source->id,
        'trs_type' => 1,
        'total_amount' => 16000,
        'paid_amount' => 16000,
        'remaining_amount' => 0,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $return->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.retur-penjualan.laporan', $return));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $html = view('laporan.retur-penjualan', ['header' => $return])->render();

    expect($html)->toContain('LAPORAN RETUR PENJUALAN')
        ->and($html)->toContain('RPJ-000001')
        ->and($html)->toContain('Budi Santoso')
        ->and($html)->toContain('Jl. Merdeka No. 10')
        ->and($html)->toContain('081234567890')
        ->and($html)->toContain('PJ-000001')
        ->and($html)->toContain('BRG-001')
        ->and($html)->toContain('Bollpoin')
        ->and($html)->toContain('TOTAL')
        ->and($html)->not->toContain('Export Excel');
});

it('prints the sale return report from the cetak route', function () {
    $stock = TbStock::factory()->create(['descr' => 'Bollpoin']);

    $return = TrHeader::factory()->create([
        'trs_number' => 'RPJ-000001',
        'trr_type' => 'SALE_RET',
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $return->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.retur-penjualan.cetak', $return));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');

    $html = view('laporan.retur-penjualan', ['header' => $return])->render();

    expect($html)->toContain('LAPORAN RETUR PENJUALAN')
        ->and($html)->toContain('RPJ-000001')
        ->and($html)->toContain('Bollpoin')
        ->and($html)->toContain('TOTAL')
        ->and($html)->not->toContain('Export Excel');
});

it('exports the sale return report as a CSV with BOM', function () {
    $customer = Customer::create(['descr' => 'Budi Santoso']);
    $source = TrHeader::factory()->create([
        'trr_type' => 'SALE',
        'customer_id' => $customer->id,
    ]);
    $stock = TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin']);

    $return = TrHeader::factory()->create([
        'trs_number' => 'RPJ-000001',
        'trr_type' => 'SALE_RET',
        'customer_id' => $customer->id,
        'source_sale_id' => $source->id,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $return->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.retur-penjualan.export', $return));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('No. Transaksi')
        ->and($content)->toContain('RPJ-000001')
        ->and($content)->toContain('Bollpoin')
        ->and($content)->toContain('TOTAL');
});

it('requires authentication to view the sale return report', function () {
    $return = TrHeader::factory()->create(['trr_type' => 'SALE_RET']);

    auth()->logout();

    $this->get(route('filament.admin.retur-penjualan.laporan', $return))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('rejects headers that are not sale returns', function () {
    $purchase = TrHeader::factory()->create(['trr_type' => 'PURCHASE']);

    $this->get(route('filament.admin.retur-penjualan.laporan', $purchase))->assertNotFound();
    $this->get(route('filament.admin.retur-penjualan.cetak', $purchase))->assertNotFound();
    $this->get(route('filament.admin.retur-penjualan.export', $purchase))->assertNotFound();
});

it('registers a sale return report action on the table that opens the report URL', function () {
    $return = TrHeader::factory()->create([
        'trr_type' => 'SALE_RET',
        'trs_number' => 'RPJ-000001',
        'trs_date' => now(),
    ]);

    Livewire::test(ListTrSaleReturns::class)
        ->assertTableActionHasUrl(
            'laporanReturPenjualan',
            route('filament.admin.retur-penjualan.laporan', $return),
            $return->getKey(),
        )
        ->assertTableActionShouldOpenUrlInNewTab('laporanReturPenjualan', $return->getKey());
});
