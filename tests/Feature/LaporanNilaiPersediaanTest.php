<?php

use App\Filament\Pages\LaporanNilaiPersediaan;
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
