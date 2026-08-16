<?php

use App\Filament\Pages\LaporanPenjualan;
use App\Filament\Pages\Tables\LaporanPenjualanTable;
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

it('computes omzet, hpp and laba per item', function () {
    $stock = TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin']);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trs_date' => '2026-07-05',
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

    $saleReturn = TrHeader::factory()->create([
        'trs_number' => 'RJ-000001',
        'trs_date' => '2026-07-20',
        'trr_type' => 'SALE_RET',
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $saleReturn->id,
        'stock_id' => $stock->id,
        'qty' => 1,
        'unit_price' => 8000,
        'hpp_at_transaction' => 5000,
        'subtotal' => 8000,
    ]);

    $rows = LaporanPenjualanTable::buildRows('2026-07-01', '2026-07-31', '', 'barang');

    expect($rows)->toHaveCount(2);

    $item = $rows[0];

    expect($item['kode'])->toBe('BRG-001')
        ->and($item['nama'])->toBe('Bollpoin')
        ->and((float) $item['qty'])->toBe(1.0)
        ->and((float) $item['omzet'])->toBe(8000.0)
        ->and((float) $item['hpp'])->toBe(5000.0)
        ->and((float) $item['laba'])->toBe(3000.0);

    $total = $rows[1];

    expect($total['nama'])->toBe('Total')
        ->and((float) $total['qty'])->toBe(1.0)
        ->and((float) $total['omzet'])->toBe(8000.0)
        ->and((float) $total['hpp'])->toBe(5000.0)
        ->and((float) $total['laba'])->toBe(3000.0);
});

it('loads the sales summary report page', function () {
    Livewire::test(LaporanPenjualan::class)
        ->assertOk();
});

it('registers cetak and export excel header actions with the report URL', function () {
    Livewire::test(LaporanPenjualan::class)
        ->set('date_from', '2026-07-01')
        ->set('date_until', '2026-07-31')
        ->set('group_by', 'barang')
        ->assertActionHasUrl('cetak', route('filament.admin.laporan-penjualan.cetak', [
            'date_from' => '2026-07-01',
            'date_until' => '2026-07-31',
            'customer_id' => null,
            'group_by' => 'barang',
        ]))
        ->assertActionShouldOpenUrlInNewTab('cetak')
        ->assertActionHasUrl('exportExcel', route('filament.admin.laporan-penjualan.export', [
            'date_from' => '2026-07-01',
            'date_until' => '2026-07-31',
            'customer_id' => null,
            'group_by' => 'barang',
        ]));
});

it('disables cetak and export until a period is selected', function () {
    Livewire::test(LaporanPenjualan::class)
        ->assertActionDisabled('cetak')
        ->assertActionDisabled('exportExcel');
});

it('prints the sales summary report from the cetak route', function () {
    $stock = TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin']);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trs_date' => '2026-07-05',
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

    $this->get(route('filament.admin.laporan-penjualan.cetak', [
        'date_from' => '2026-07-01',
        'date_until' => '2026-07-31',
        'group_by' => 'barang',
    ]))
        ->assertOk()
        ->assertSee('LAPORAN PENJUALAN')
        ->assertSee('Bollpoin')
        ->assertSee('Total');
});

it('exports the sales summary report as a CSV with BOM', function () {
    $stock = TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin']);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trs_date' => '2026-07-05',
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

    $response = $this->get(route('filament.admin.laporan-penjualan.export', [
        'date_from' => '2026-07-01',
        'date_until' => '2026-07-31',
        'group_by' => 'barang',
    ]));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('Nama Barang / Customer')
        ->and($content)->toContain('BRG-001')
        ->and($content)->toContain('Bollpoin')
        ->and($content)->toContain('10.000,00')
        ->and($content)->toContain('6.000,00');
});

it('rejects cetak and export when parameters are missing', function () {
    $this->get(route('filament.admin.laporan-penjualan.cetak'))
        ->assertNotFound();

    $this->get(route('filament.admin.laporan-penjualan.export'))
        ->assertNotFound();
});
