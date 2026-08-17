<?php

use App\Filament\Resources\LaporanPembelians\Pages\ListLaporanPembelians;
use App\Models\Supplier;
use App\Models\TrHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('loads the purchase summary report page', function () {
    Livewire::test(ListLaporanPembelians::class)
        ->assertOk();
});

it('registers cetak and export excel header actions that follow the table filters', function () {
    $supplier = Supplier::factory()->create(['descr' => 'PT Maju Jaya']);

    $html = Livewire::test(ListLaporanPembelians::class)
        ->set('tableFilters.trs_date.date_from', '2026-08-01')
        ->set('tableFilters.trs_date.date_until', '2026-08-31')
        ->set('tableFilters.supplier_id.value', $supplier->id)
        ->set('tableFilters.trs_type.value', '1')
        ->html();

    expect($html)->toContain('Cetak')
        ->and($html)->toContain('Export Excel')
        ->and($html)->toContain('laporan-pembelian/cetak?date_from=2026-08-01')
        ->and($html)->toContain('laporan-pembelian/export?date_from=2026-08-01')
        ->and($html)->toContain('supplier_id='.$supplier->id)
        ->and($html)->toContain('trs_type=1');
});

it('keeps cetak and export enabled even without a date filter', function () {
    $html = Livewire::test(ListLaporanPembelians::class)->html();

    expect($html)->toContain('laporan-pembelian/cetak')
        ->and($html)->toContain('laporan-pembelian/export')
        ->and($html)->not->toContain('pointer-events-none');
});

it('prints the purchase summary from the cetak route', function () {
    $purchase = TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'trs_date' => '2026-08-15',
        'trr_type' => 'PURCHASE',
        'total_amount' => 16000,
    ]);

    $this->get(route('filament.admin.laporan-pembelian.cetak', [
        'date_from' => '2026-08-01',
        'date_until' => '2026-08-31',
    ]))
        ->assertOk()
        ->assertSee('LAPORAN PEMBELIAN')
        ->assertSee('PB-000001')
        ->assertSee('Pembelian')
        ->assertSee('16.000,00')
        ->assertSee('TOTAL');
});

it('prints all purchases when no dates are provided', function () {
    $purchase = TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'trs_date' => '2026-08-15',
        'trr_type' => 'PURCHASE',
        'total_amount' => 16000,
    ]);

    $this->get(route('filament.admin.laporan-pembelian.cetak'))
        ->assertOk()
        ->assertSee('LAPORAN PEMBELIAN')
        ->assertSee('PB-000001')
        ->assertSee('16.000,00');
});

it('exports the purchase summary as a CSV with BOM', function () {
    $supplier = Supplier::factory()->create(['descr' => 'PT Maju Jaya']);

    TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'trs_date' => '2026-08-15',
        'trr_type' => 'PURCHASE',
        'supplier_id' => $supplier->id,
        'total_amount' => 16000,
    ]);

    $response = $this->get(route('filament.admin.laporan-pembelian.export', [
        'date_from' => '2026-08-01',
        'date_until' => '2026-08-31',
    ]));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('No. Transaksi')
        ->and($content)->toContain('PB-000001')
        ->and($content)->toContain('PT Maju Jaya')
        ->and($content)->toContain('16.000,00')
        ->and($content)->toContain('TOTAL');
});

it('exports all purchases when no dates are provided', function () {
    TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'trs_date' => '2026-08-15',
        'trr_type' => 'PURCHASE',
        'total_amount' => 16000,
    ]);

    $response = $this->get(route('filament.admin.laporan-pembelian.export'));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('PB-000001')
        ->and($content)->toContain('16.000,00');
});
