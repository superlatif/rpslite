<?php

use App\Models\Supplier;
use App\Models\TbStock;
use App\Models\TrDetail;
use App\Models\TrHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('fills harga_pokok with harga_beli on create when no purchase transaction exists', function () {
    $stock = TbStock::factory()->create(['harga_beli' => 5000]);

    expect((float) $stock->harga_pokok)->toBe(5000.0);
});

it('keeps harga_pokok in sync with harga_beli on edit when no purchase transaction exists', function () {
    $stock = TbStock::factory()->create(['harga_beli' => 5000, 'harga_pokok' => 5000]);

    $stock->update(['harga_beli' => 6000]);

    expect((float) $stock->fresh()->harga_pokok)->toBe(6000.0);
});

it('does not override harga_pokok on edit when purchase transaction exists', function () {
    $stock = TbStock::factory()->create(['harga_beli' => 5000]);
    $header = TrHeader::factory()->create(['trr_type' => 'PURCHASE']);

    TrDetail::factory()->create([
        'tr_header_id' => $header->id,
        'stock_id' => $stock->id,
        'qty' => 10,
        'unit_price' => 8000,
        'subtotal' => 80000,
    ]);

    $stock->recalculateHpp();

    $stock->update(['harga_beli' => 9000]);

    expect((float) $stock->fresh()->harga_pokok)->toBe(8000.0);
});

it('recalculates harga_pokok as weighted average from purchase transactions', function () {
    $stock = TbStock::factory()->create(['harga_beli' => 5000]);

    $header = TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'supplier_id' => Supplier::factory()->create()->id,
        'trr_type' => 'PURCHASE',
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $header->id,
        'stock_id' => $stock->id,
        'qty' => 10,
        'unit_price' => 1000,
        'subtotal' => 10000,
    ]);

    $stock->recalculateHpp();

    expect((float) $stock->fresh()->harga_pokok)->toBe(1000.0);

    $second = TrHeader::factory()->create([
        'trs_number' => 'PB-000002',
        'supplier_id' => Supplier::factory()->create()->id,
        'trr_type' => 'PURCHASE',
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $second->id,
        'stock_id' => $stock->id,
        'qty' => 10,
        'unit_price' => 2000,
        'subtotal' => 20000,
    ]);

    $stock->recalculateHpp();

    expect((float) $stock->fresh()->harga_pokok)->toBe(1500.0);
});

it('resets harga_pokok to zero when purchase transactions are removed', function () {
    $stock = TbStock::factory()->create(['harga_beli' => 5000]);

    $detail = TrDetail::factory()->create([
        'tr_header_id' => TrHeader::factory()->create(['trr_type' => 'PURCHASE'])->id,
        'stock_id' => $stock->id,
        'qty' => 10,
        'unit_price' => 8000,
        'subtotal' => 80000,
    ]);

    $stock->recalculateHpp();
    $detail->delete();

    $stock->recalculateHpp();

    expect((float) $stock->fresh()->harga_pokok)->toBe(0.0);
});
