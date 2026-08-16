<?php

use App\Filament\Resources\TrPurchases\Pages\ListTrPurchases;
use App\Models\Supplier;
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

it('renders the purchase report with supplier info, details and totals', function () {
    $supplier = Supplier::factory()->create([
        'descr' => 'PT Maju Jaya',
        'alamat' => 'Jl. Industri No. 5',
        'phone' => '0215551234',
    ]);

    $stock = TbStock::factory()->create([
        'code' => 'BRG-001',
        'descr' => 'Bollpoin',
        'satuan' => 'PCS',
    ]);

    $purchase = TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'trs_date' => '2026-08-15',
        'trr_type' => 'PURCHASE',
        'supplier_id' => $supplier->id,
        'trs_type' => 0,
        'total_amount' => 16000,
        'paid_amount' => 16000,
        'remaining_amount' => 0,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $purchase->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $this->get(route('filament.admin.pembelian.laporan', $purchase))
        ->assertOk()
        ->assertSee('LAPORAN PEMBELIAN')
        ->assertSee('PB-000001')
        ->assertSee('PT Maju Jaya')
        ->assertSee('Jl. Industri No. 5')
        ->assertSee('0215551234')
        ->assertSee('BRG-001')
        ->assertSee('Bollpoin')
        ->assertSee('TOTAL')
        ->assertSee('Cetak')
        ->assertSee('Export Excel')
        ->assertDontSee('HPP')
        ->assertDontSee('Laba');
});

it('shows a dashed phone when the supplier has none', function () {
    $supplier = Supplier::factory()->create(['descr' => 'PT Maju Jaya', 'phone' => null]);

    $purchase = TrHeader::factory()->create([
        'trr_type' => 'PURCHASE',
        'supplier_id' => $supplier->id,
    ]);

    $this->get(route('filament.admin.pembelian.laporan', $purchase))
        ->assertOk()
        ->assertSee('---');
});

it('prints the purchase report from the cetak route', function () {
    $stock = TbStock::factory()->create(['descr' => 'Bollpoin']);

    $purchase = TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'trr_type' => 'PURCHASE',
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $purchase->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $this->get(route('filament.admin.pembelian.cetak', $purchase))
        ->assertOk()
        ->assertSee('LAPORAN PEMBELIAN')
        ->assertSee('PB-000001')
        ->assertSee('Bollpoin')
        ->assertSee('TOTAL')
        ->assertDontSee('Export Excel');
});

it('exports the purchase report as a CSV with BOM', function () {
    $supplier = Supplier::factory()->create(['descr' => 'PT Maju Jaya']);
    $stock = TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin']);

    $purchase = TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'trr_type' => 'PURCHASE',
        'supplier_id' => $supplier->id,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $purchase->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.pembelian.export', $purchase));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('No. Transaksi')
        ->and($content)->toContain('PB-000001')
        ->and($content)->toContain('Bollpoin')
        ->and($content)->toContain('TOTAL')
        ->and($content)->not->toContain('Laba')
        ->and($content)->not->toContain('HPP');
});

it('requires authentication to view the purchase report', function () {
    $purchase = TrHeader::factory()->create(['trr_type' => 'PURCHASE']);

    auth()->logout();

    $this->get(route('filament.admin.pembelian.laporan', $purchase))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('rejects headers that are not purchases', function () {
    $sale = TrHeader::factory()->create(['trr_type' => 'SALE']);

    $this->get(route('filament.admin.pembelian.laporan', $sale))->assertNotFound();
    $this->get(route('filament.admin.pembelian.cetak', $sale))->assertNotFound();
    $this->get(route('filament.admin.pembelian.export', $sale))->assertNotFound();
});

it('registers a purchase report action on the table that opens the report URL', function () {
    $purchase = TrHeader::factory()->create([
        'trr_type' => 'PURCHASE',
        'trs_number' => 'PB-000001',
        'trs_date' => now(),
    ]);

    Livewire::test(ListTrPurchases::class)
        ->assertTableActionHasUrl(
            'laporanPembelian',
            route('filament.admin.pembelian.laporan', $purchase),
            $purchase->getKey(),
        )
        ->assertTableActionShouldOpenUrlInNewTab('laporanPembelian', $purchase->getKey());
});
