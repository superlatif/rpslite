<?php

use App\Filament\Pages\LaporanNilaiPersediaan;
use App\Filament\Pages\Tables\LaporanNilaiPersediaanTable;
use App\Filament\Resources\TrOpnames\Pages\ListTrOpnames;
use App\Filament\Resources\TrPurchaseReturns\Pages\ListTrPurchaseReturns;
use App\Filament\Resources\TrPurchases\Pages\ListTrPurchases;
use App\Filament\Resources\TrSaleReturns\Pages\ListTrSaleReturns;
use App\Filament\Resources\TrSales\Pages\ListTrSales;
use App\Filament\Widgets\InventoryStatsOverview;
use App\Filament\Widgets\LowStockTable;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\TbStock;
use App\Models\TrHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('persists the service flag and exposes barang/jasa scopes', function () {
    $jasa = TbStock::factory()->jasa()->create();

    expect($jasa->is_jasa)->toBeTrue()
        ->and(TbStock::query()->barang()->find($jasa->id))->toBeNull()
        ->and(TbStock::query()->jasa()->find($jasa->id))->not->toBeNull();
});

it('keeps harga_pokok synced to harga_beli for a service with no purchases', function () {
    $jasa = TbStock::factory()->jasa()->create(['harga_beli' => 20000]);

    expect((float) $jasa->fresh()->harga_pokok)->toBe(20000.0);
});

it('sells a service without touching stock and records its cost basis', function () {
    $jasa = TbStock::factory()->jasa()->create([
        'descr' => 'Jasa Servis',
        'harga_beli' => 20000,
        'harga_jual' => 50000,
    ]);

    Livewire::test(ListTrSales::class)
        ->callAction('create', data: [
            'trs_date' => '2026-08-15',
            'details' => [
                ['stock_id' => (string) $jasa->id, 'qty' => 1, 'unit_price' => 50000, 'subtotal' => 50000],
            ],
        ])
        ->assertHasNoErrors()
        ->assertNotified();

    $this->assertDatabaseHas('tr_headers', [
        'trr_type' => 'SALE',
        'trs_number' => 'PJ-000001',
        'total_amount' => '50000.00',
    ]);

    $this->assertDatabaseHas('tr_details', [
        'stock_id' => $jasa->id,
        'qty' => '1.00',
        'hpp_at_transaction' => '20000.00',
        'subtotal' => '50000.00',
    ]);

    expect($jasa->fresh()->stock)->toBe(0);
});

it('does not require available stock to sell a service', function () {
    $jasa = TbStock::factory()->jasa()->create([
        'descr' => 'Jasa Konsultasi',
        'harga_jual' => 75000,
    ]);

    Livewire::test(ListTrSales::class)
        ->callAction('create', data: [
            'trs_date' => '2026-08-15',
            'details' => [
                ['stock_id' => (string) $jasa->id, 'qty' => 1, 'unit_price' => 75000, 'subtotal' => 75000],
            ],
        ])
        ->assertHasNoErrors()
        ->assertNotified();

    expect($jasa->fresh()->stock)->toBe(0);
});

it('purchases a service without touching stock', function () {
    $supplier = Supplier::factory()->create();
    $jasa = TbStock::factory()->jasa()->create([
        'descr' => 'Jasa Instalasi',
        'harga_beli' => 30000,
    ]);

    Livewire::test(ListTrPurchases::class)
        ->callAction('create', data: [
            'trs_date' => '2026-08-15',
            'supplier_id' => (string) $supplier->id,
            'trs_type' => 0,
            'details' => [
                ['stock_id' => (string) $jasa->id, 'qty' => 1, 'unit_price' => 30000, 'subtotal' => 30000],
            ],
        ])
        ->assertHasNoErrors()
        ->assertNotified();

    $this->assertDatabaseHas('tr_headers', [
        'trr_type' => 'PURCHASE',
        'trs_number' => 'PB-000001',
        'total_amount' => '30000.00',
    ]);

    expect($jasa->fresh()->stock)->toBe(0);
});

it('returns a sold service without restocking', function () {
    $customer = Customer::create(['descr' => 'Customer A']);
    $jasa = TbStock::factory()->jasa()->create([
        'descr' => 'Jasa Servis',
        'harga_jual' => 50000,
    ]);

    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trs_date' => '2026-08-01',
        'trr_type' => 'SALE',
        'customer_id' => $customer->id,
        'trs_type' => 1,
        'total_amount' => 100000,
        'paid_amount' => 0,
        'remaining_amount' => 100000,
    ]);

    Livewire::test(ListTrSaleReturns::class)
        ->callAction('create', data: [
            'trs_date' => '2026-08-05',
            'customer_id' => $customer->id,
            'source_sale_id' => (string) $sale->id,
            'trs_type' => 1,
            'details' => [
                ['stock_id' => (string) $jasa->id, 'qty' => 1, 'unit_price' => 50000, 'subtotal' => 50000],
            ],
        ])
        ->assertHasNoErrors()
        ->assertNotified();

    $retur = TrHeader::where('trr_type', 'SALE_RET')->sole();

    expect($retur->paid_amount)->toBe('50000.00')
        ->and($sale->fresh()->remaining_amount)->toBe('50000.00')
        ->and($jasa->fresh()->stock)->toBe(0);
});

it('returns a purchased service without decrementing stock', function () {
    $supplier = Supplier::factory()->create();
    $jasa = TbStock::factory()->jasa()->create([
        'descr' => 'Jasa Instalasi',
        'harga_beli' => 30000,
    ]);

    $purchase = TrHeader::factory()->create([
        'trs_number' => 'PB-000001',
        'trs_date' => '2026-08-01',
        'trr_type' => 'PURCHASE',
        'supplier_id' => $supplier->id,
        'trs_type' => 1,
        'total_amount' => 100000,
        'paid_amount' => 0,
        'remaining_amount' => 100000,
    ]);

    Livewire::test(ListTrPurchaseReturns::class)
        ->callAction('create', data: [
            'trs_date' => '2026-08-05',
            'supplier_id' => $supplier->id,
            'source_purchase_id' => (string) $purchase->id,
            'trs_type' => 1,
            'details' => [
                ['stock_id' => (string) $jasa->id, 'qty' => 1, 'unit_price' => 30000, 'subtotal' => 30000],
            ],
        ])
        ->assertHasNoErrors()
        ->assertNotified();

    $retur = TrHeader::where('trr_type', 'PURCHASE_RET')->sole();

    expect($retur->paid_amount)->toBe('30000.00')
        ->and($purchase->fresh()->remaining_amount)->toBe('70000.00')
        ->and($jasa->fresh()->stock)->toBe(0);
});

it('does not offer services in the stock opname form', function () {
    $barang = TbStock::factory()->create(['descr' => 'Barang Stok']);
    $jasa = TbStock::factory()->jasa()->create(['descr' => 'Jasa Layanan']);

    $test = Livewire::test(ListTrOpnames::class)
        ->mountAction('create');

    $schema = $test->instance()->mountedActionSchema0;
    $flatFields = $schema->getFlatFields(withHidden: true);

    $stockField = collect($flatFields)
        ->first(fn ($field, string $key): bool => str_starts_with($key, 'details.') && str_ends_with($key, '.stock_id'));

    expect($stockField)->not->toBeNull();

    $options = $stockField->getOptions();

    expect($options)->toHaveKey($barang->id)
        ->and($options)->not->toHaveKey($jasa->id);
});

it('rejects a service in a stock opname', function () {
    $jasa = TbStock::factory()->jasa()->create();

    Livewire::test(ListTrOpnames::class)
        ->callAction('create', data: [
            'trs_date' => '2026-08-13',
            'details' => [
                ['stock_id' => (string) $jasa->id, 'stok_fisik' => 5],
            ],
        ])
        ->assertHasErrors(['mountedActions.0.data.details.0.stock_id']);

    expect(TrHeader::where('trr_type', 'OPNAME')->count())->toBe(0);
});

it('excludes services from the inventory value report', function () {
    $barang = TbStock::factory()->create(['descr' => 'Barang Stok', 'stock' => 5, 'harga_beli' => 1000]);
    $jasa = TbStock::factory()->jasa()->create(['descr' => 'Jasa Layanan', 'stock' => 5, 'harga_beli' => 1000]);

    Livewire::test(LaporanNilaiPersediaan::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$barang])
        ->assertCanNotSeeTableRecords([$jasa]);
});

it('excludes services from the inventory value print/export rows', function () {
    TbStock::factory()->create(['descr' => 'Barang Stok', 'stock' => 5, 'harga_beli' => 1000]);
    TbStock::factory()->jasa()->create(['descr' => 'Jasa Layanan', 'stock' => 5, 'harga_beli' => 1000]);

    $rows = LaporanNilaiPersediaanTable::buildRows();

    expect($rows)->toHaveCount(1)
        ->and(collect($rows)->pluck('descr'))->not->toContain('Jasa Layanan');
});

it('excludes services from the low stock widget', function () {
    $barang = TbStock::factory()->create(['descr' => 'Barang Habis', 'stock' => 0]);
    $jasa = TbStock::factory()->jasa()->create(['descr' => 'Jasa Tanpa Stok']);

    Livewire::test(LowStockTable::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$barang])
        ->assertCanNotSeeTableRecords([$jasa]);
});

it('excludes services from the inventory stats', function () {
    TbStock::factory()->create(['stock' => 10, 'harga_beli' => 1000]);
    TbStock::factory()->jasa()->create(['stock' => 100, 'harga_beli' => 1000]);

    Livewire::test(InventoryStatsOverview::class)
        ->assertOk()
        ->assertSee('Total Item')
        ->assertSee('10 total qty')
        ->assertSee('Rp 10.000')
        ->assertDontSee('110 total qty');
});
