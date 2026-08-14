<?php

use App\Filament\Pages\Tables\LaporanKartuStokTable;
use App\Filament\Resources\TrOpnames\Pages\ListTrOpnames;
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

it('loads the opname resource page', function () {
    Livewire::test(ListTrOpnames::class)
        ->assertOk();
});

it('creates an opname and sets stock to the physical count on surplus', function () {
    $stock = TbStock::factory()->create(['stock' => 10, 'harga_beli' => 5000]);

    Livewire::test(ListTrOpnames::class)
        ->callAction('create', data: [
            'trs_date' => '2026-08-13',
            'details' => [
                ['stock_id' => (string) $stock->id, 'stok_fisik' => 15],
            ],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $this->assertDatabaseHas('tr_headers', [
        'trr_type' => 'OPNAME',
        'trs_number' => 'OP-000001',
    ]);

    $this->assertDatabaseHas('tr_details', [
        'stock_id' => $stock->id,
        'qty' => '5.00',
    ]);

    expect($stock->fresh()->stock)->toBe(15);
});

it('creates an opname and sets stock to the physical count on shortage', function () {
    $stock = TbStock::factory()->create(['stock' => 10, 'harga_beli' => 5000]);

    Livewire::test(ListTrOpnames::class)
        ->callAction('create', data: [
            'trs_date' => '2026-08-13',
            'details' => [
                ['stock_id' => (string) $stock->id, 'stok_fisik' => 8],
            ],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $this->assertDatabaseHas('tr_details', [
        'stock_id' => $stock->id,
        'qty' => '-2.00',
    ]);

    expect($stock->fresh()->stock)->toBe(8);
});

it('handles opname surplus and shortage movements in the stock card', function () {
    $stock = TbStock::factory()->create();

    $surplus = TrHeader::factory()->create([
        'trs_number' => 'OP-000001',
        'trs_date' => '2026-07-05',
        'trr_type' => 'OPNAME',
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $surplus->id,
        'stock_id' => $stock->id,
        'qty' => 5,
    ]);

    $shortage = TrHeader::factory()->create([
        'trs_number' => 'OP-000002',
        'trs_date' => '2026-07-10',
        'trr_type' => 'OPNAME',
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $shortage->id,
        'stock_id' => $stock->id,
        'qty' => -3,
    ]);

    $rows = LaporanKartuStokTable::buildRows((string) $stock->id, '2026-07-01', '2026-07-31');

    $byNumber = collect($rows)->keyBy('trs_number');

    expect((float) $byNumber['OP-000001']['masuk'])->toBe(5.0)
        ->and((float) $byNumber['OP-000002']['keluar'])->toBe(3.0)
        ->and((float) collect($rows)->firstWhere('jenis', 'Stok Akhir')['saldo'])->toBe(2.0);
});
