<?php

use App\Filament\Resources\TbStocks\Pages\ListTbStocks;
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

it('rolls back the whole bulk delete when one stock is in use', function () {
    $inUse = TbStock::factory()->create(['descr' => 'Dipakai']);
    $free = TbStock::factory()->create(['descr' => 'Bebas']);

    TrDetail::factory()->create([
        'tr_header_id' => TrHeader::factory()->create(['trr_type' => 'PURCHASE']),
        'stock_id' => $inUse->id,
    ]);

    Livewire::test(ListTbStocks::class)
        ->callTableBulkAction('delete', [
            $inUse->getKey(),
            $free->getKey(),
        ]);

    expect(TbStock::query()->count())->toBe(2)
        ->and(TbStock::find($free->getKey()))->not->toBeNull();
});

it('deletes all stocks when none are in use', function () {
    $a = TbStock::factory()->create();
    $b = TbStock::factory()->create();

    Livewire::test(ListTbStocks::class)
        ->callTableBulkAction('delete', [
            $a->getKey(),
            $b->getKey(),
        ]);

    expect(TbStock::query()->count())->toBe(0);
});
