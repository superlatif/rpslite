<?php

use App\Filament\Pages\LaporanNilaiPersediaan;
use App\Filament\Pages\Tables\LaporanNilaiPersediaanTable;
use App\Models\TbStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('loads the inventory value report page', function () {
    TbStock::factory()->create(['stock' => 0]);

    Livewire::test(LaporanNilaiPersediaan::class)
        ->assertOk();
});

it('shows the computed inventory value per item', function () {
    $stock = TbStock::factory()->create(['stock' => 10, 'harga_beli' => 5000]);

    Livewire::test(LaporanNilaiPersediaan::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$stock]);
});

it('summarizes total stock and total inventory value', function () {
    TbStock::factory()->create(['stock' => 10, 'harga_beli' => 5000]);
    TbStock::factory()->create(['stock' => 5, 'harga_beli' => 2000]);

    Livewire::test(LaporanNilaiPersediaan::class)
        ->assertOk()
        ->assertTableColumnSummarySet('stock', 'total_stok', 15)
        ->assertTableColumnSummarySet('nilai_persediaan', 'total_nilai', 60000);
});

it('registers cetak and export excel header actions with the report URL', function () {
    Livewire::test(LaporanNilaiPersediaan::class)
        ->assertActionHasUrl('cetak', route('filament.admin.laporan-nilai-persediaan.cetak', [
            'only_available' => 1,
        ]))
        ->assertActionHasUrl('exportExcel', route('filament.admin.laporan-nilai-persediaan.export', [
            'only_available' => 1,
        ]));
});

it('prints the inventory value report from the cetak route', function () {
    TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin', 'stock' => 10, 'harga_beli' => 5000]);

    $response = $this->get(route('filament.admin.laporan-nilai-persediaan.cetak'));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');

    $html = view('laporan.nilai-persediaan', [
        'rows' => LaporanNilaiPersediaanTable::buildRows('', null),
    ])->render();

    expect($html)->toContain('LAPORAN NILAI PERSEDIAAN')
        ->and($html)->toContain('Bollpoin')
        ->and($html)->toContain('TOTAL');
});

it('exports the inventory value report as a CSV with BOM', function () {
    $stock = TbStock::factory()->create(['code' => 'BRG-001', 'descr' => 'Bollpoin', 'stock' => 10, 'harga_beli' => 5000]);

    $response = $this->get(route('filament.admin.laporan-nilai-persediaan.export'));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('Nilai Persediaan')
        ->and($content)->toContain('BRG-001')
        ->and($content)->toContain('Bollpoin')
        ->and($content)->toContain('TOTAL');
});
