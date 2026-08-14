<?php

use App\Filament\Pages\LaporanKartuStok;
use App\Filament\Pages\Tables\LaporanKartuStokTable;
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

it('computes opening, movements and closing stock correctly', function () {
    $stock = TbStock::factory()->create();

    $createTx = function (string $type, string $date, float $qty) use ($stock): void {
        $header = TrHeader::factory()->create([
            'trs_number' => fake()->unique()->numerify('TX-######'),
            'trs_date' => $date,
            'trr_type' => $type,
        ]);

        TrDetail::factory()->create([
            'tr_header_id' => $header->id,
            'stock_id' => $stock->id,
            'qty' => $qty,
        ]);
    };

    $createTx('PURCHASE', '2026-06-20', 10);
    $createTx('PURCHASE', '2026-07-05', 5);
    $createTx('SALE', '2026-07-10', 3);
    $createTx('SALE_RET', '2026-07-15', 2);
    $createTx('PURCHASE_RET', '2026-07-20', 1);
    $createTx('PURCHASE', '2026-07-31', 3);
    $createTx('PURCHASE', '2026-08-05', 7);

    $rows = LaporanKartuStokTable::buildRows((string) $stock->id, '2026-07-01', '2026-07-31');

    expect($rows)->toHaveCount(9);

    $first = $rows[0];

    expect($first['jenis'])->toBe('Stok Awal')
        ->and((float) $first['saldo'])->toBe(10.0);

    $totalIn = collect($rows)->firstWhere('jenis', 'Total Mutasi Masuk');
    $totalOut = collect($rows)->firstWhere('jenis', 'Total Mutasi Keluar');
    $closing = collect($rows)->firstWhere('jenis', 'Stok Akhir');

    expect((float) $totalIn['masuk'])->toBe(10.0)
        ->and((float) $totalOut['keluar'])->toBe(4.0)
        ->and((float) $closing['saldo'])->toBe(16.0);
});

it('includes a transaction that happens exactly on the end date of the period', function () {
    $stock = TbStock::factory()->create();

    $header = TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'trs_date' => '2026-08-13',
        'trr_type' => 'PURCHASE',
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $header->id,
        'stock_id' => $stock->id,
        'qty' => 5,
    ]);

    $rows = LaporanKartuStokTable::buildRows((string) $stock->id, '2026-08-01', '2026-08-13');

    $movement = collect($rows)->firstWhere('trs_number', 'PB-000001');

    expect($movement)->not->toBeNull()
        ->and((float) $movement['masuk'])->toBe(5.0)
        ->and((float) collect($rows)->firstWhere('jenis', 'Stok Akhir')['saldo'])->toBe(5.0);
});

it('loads the stock card report page', function () {
    Livewire::test(LaporanKartuStok::class)
        ->assertOk();
});

it('renders the stock card when a report is generated', function () {
    $stock = TbStock::factory()->create();

    $header = TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'trs_date' => '2026-07-05',
        'trr_type' => 'PURCHASE',
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $header->id,
        'stock_id' => $stock->id,
        'qty' => 5,
    ]);

    Livewire::test(LaporanKartuStok::class)
        ->set('stock_id', (string) $stock->id)
        ->set('date_from', '2026-07-01')
        ->set('date_until', '2026-07-31')
        ->assertOk()
        ->assertSee('PB-000001')
        ->assertSee('Stok Awal')
        ->assertSee('Stok Akhir')
        ->assertSee('Pembelian');
});
