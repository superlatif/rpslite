<?php

use App\Filament\Resources\TrPurchaseReturns\Pages\ListTrPurchaseReturns;
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

it('renders the purchase return report with supplier, source invoice, details and totals', function () {
    $supplier = Supplier::factory()->create([
        'descr' => 'PT Maju Jaya',
        'alamat' => 'Jl. Industri No. 5',
        'phone' => '0215551234',
    ]);

    $source = TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'trr_type' => 'PURCHASE',
        'supplier_id' => $supplier->id,
        'trs_type' => 1,
        'remaining_amount' => 16000,
    ]);

    $stock = TbStock::factory()->create([
        'code' => 'BRG-001',
        'descr' => 'Bollpoin',
        'satuan' => 'PCS',
    ]);

    $return = TrHeader::factory()->create([
        'trs_number' => 'RPB-000001',
        'trs_date' => '2026-08-15',
        'trr_type' => 'PURCHASE_RET',
        'supplier_id' => $supplier->id,
        'source_purchase_id' => $source->id,
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

    $response = $this->get(route('filament.admin.retur-pembelian.laporan', $return));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $html = view('laporan.retur-pembelian', ['header' => $return])->render();

    expect($html)->toContain('LAPORAN RETUR PEMBELIAN')
        ->and($html)->toContain('RPB-000001')
        ->and($html)->toContain('PT Maju Jaya')
        ->and($html)->toContain('Jl. Industri No. 5')
        ->and($html)->toContain('0215551234')
        ->and($html)->toContain('PB-000001')
        ->and($html)->toContain('BRG-001')
        ->and($html)->toContain('Bollpoin')
        ->and($html)->toContain('TOTAL')
        ->and($html)->not->toContain('HPP')
        ->and($html)->not->toContain('Laba')
        ->and($html)->not->toContain('Export Excel');
});

it('prints the purchase return report from the cetak route', function () {
    $stock = TbStock::factory()->create(['descr' => 'Bollpoin']);

    $return = TrHeader::factory()->create([
        'trs_number' => 'RPB-000001',
        'trr_type' => 'PURCHASE_RET',
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $return->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.retur-pembelian.cetak', $return));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');

    $html = view('laporan.retur-pembelian', ['header' => $return])->render();

    expect($html)->toContain('LAPORAN RETUR PEMBELIAN')
        ->and($html)->toContain('RPB-000001')
        ->and($html)->toContain('Bollpoin')
        ->and($html)->toContain('TOTAL')
        ->and($html)->not->toContain('Export Excel');
});

it('exports the purchase return report as a CSV with BOM', function () {
    $supplier = Supplier::factory()->create(['descr' => 'PT Maju Jaya']);
    $source = TrHeader::factory()->create([
        'trr_type' => 'PURCHASE',
        'supplier_id' => $supplier->id,
    ]);
    $stock = TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin']);

    $return = TrHeader::factory()->create([
        'trs_number' => 'RPB-000001',
        'trr_type' => 'PURCHASE_RET',
        'supplier_id' => $supplier->id,
        'source_purchase_id' => $source->id,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $return->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 16000,
    ]);

    $response = $this->get(route('filament.admin.retur-pembelian.export', $return));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('No. Transaksi')
        ->and($content)->toContain('RPB-000001')
        ->and($content)->toContain('Bollpoin')
        ->and($content)->toContain('TOTAL')
        ->and($content)->not->toContain('Laba')
        ->and($content)->not->toContain('HPP');
});

it('requires authentication to view the purchase return report', function () {
    $return = TrHeader::factory()->create(['trr_type' => 'PURCHASE_RET']);

    auth()->logout();

    $this->get(route('filament.admin.retur-pembelian.laporan', $return))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('rejects headers that are not purchase returns', function () {
    $sale = TrHeader::factory()->create(['trr_type' => 'SALE']);

    $this->get(route('filament.admin.retur-pembelian.laporan', $sale))->assertNotFound();
    $this->get(route('filament.admin.retur-pembelian.cetak', $sale))->assertNotFound();
    $this->get(route('filament.admin.retur-pembelian.export', $sale))->assertNotFound();
});

it('registers a purchase return report action on the table that opens the report URL', function () {
    $return = TrHeader::factory()->create([
        'trr_type' => 'PURCHASE_RET',
        'trs_number' => 'RPB-000001',
        'trs_date' => now(),
    ]);

    Livewire::test(ListTrPurchaseReturns::class)
        ->assertTableActionHasUrl(
            'laporanReturPembelian',
            route('filament.admin.retur-pembelian.laporan', $return),
            $return->getKey(),
        )
        ->assertTableActionShouldOpenUrlInNewTab('laporanReturPembelian', $return->getKey());
});
