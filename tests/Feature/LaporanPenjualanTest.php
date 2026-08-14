<?php

use App\Filament\Pages\LaporanPenjualan;
use App\Filament\Pages\Tables\LaporanPenjualanTable;
use App\Models\Customer;
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

function buatTransaksi(string $type, string $trsNumber, string $date, TbStock $stock, int $customerId, float $qty, float $price): void
{
    $header = TrHeader::factory()->create([
        'trs_number' => $trsNumber,
        'trs_date' => $date,
        'trr_type' => $type,
        'customer_id' => $customerId,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $header->id,
        'stock_id' => $stock->id,
        'qty' => $qty,
        'unit_price' => $price,
        'subtotal' => $qty * $price,
    ]);
}

it('summarizes qty and omzet per item, subtracting sales returns', function () {
    $customer = Customer::create(['descr' => 'Customer A']);
    $itemX = TbStock::factory()->create();
    $itemY = TbStock::factory()->create();

    buatTransaksi('SALE', 'PJ-000001', '2026-08-05', $itemX, $customer->id, 10, 1000);
    buatTransaksi('SALE_RET', 'RPJ-000001', '2026-08-10', $itemX, $customer->id, 2, 1000);
    buatTransaksi('SALE', 'PJ-000002', '2026-08-15', $itemY, $customer->id, 5, 2000);

    $rows = LaporanPenjualanTable::buildRows('2026-08-01', '2026-08-31', '', 'barang');

    $rowX = collect($rows)->firstWhere('nama', $itemX->descr);
    $rowY = collect($rows)->firstWhere('nama', $itemY->descr);
    $total = collect($rows)->firstWhere('nama', 'Total');

    expect((float) $rowX['qty'])->toBe(8.0)
        ->and((float) $rowX['omzet'])->toBe(8000.0)
        ->and((float) $rowY['qty'])->toBe(5.0)
        ->and((float) $rowY['omzet'])->toBe(10000.0)
        ->and((float) $total['qty'])->toBe(13.0)
        ->and((float) $total['omzet'])->toBe(18000.0);
});

it('summarizes qty and omzet per customer', function () {
    $customerA = Customer::create(['descr' => 'Customer A']);
    $customerB = Customer::create(['descr' => 'Customer B']);
    $itemX = TbStock::factory()->create();
    $itemY = TbStock::factory()->create();

    buatTransaksi('SALE', 'PJ-000001', '2026-08-05', $itemX, $customerA->id, 10, 1000);
    buatTransaksi('SALE_RET', 'RPJ-000001', '2026-08-10', $itemX, $customerA->id, 2, 1000);
    buatTransaksi('SALE', 'PJ-000002', '2026-08-15', $itemY, $customerB->id, 5, 2000);

    $rows = LaporanPenjualanTable::buildRows('2026-08-01', '2026-08-31', '', 'customer');

    $rowA = collect($rows)->firstWhere('nama', 'Customer A');
    $rowB = collect($rows)->firstWhere('nama', 'Customer B');
    $total = collect($rows)->firstWhere('nama', 'Total');

    expect((float) $rowA['qty'])->toBe(8.0)
        ->and((float) $rowA['omzet'])->toBe(8000.0)
        ->and((float) $rowB['qty'])->toBe(5.0)
        ->and((float) $rowB['omzet'])->toBe(10000.0)
        ->and((float) $total['qty'])->toBe(13.0)
        ->and((float) $total['omzet'])->toBe(18000.0);
});

it('filters sales report by a specific customer', function () {
    $customerA = Customer::create(['descr' => 'Customer A']);
    $customerB = Customer::create(['descr' => 'Customer B']);
    $itemX = TbStock::factory()->create();

    buatTransaksi('SALE', 'PJ-000001', '2026-08-05', $itemX, $customerA->id, 10, 1000);
    buatTransaksi('SALE', 'PJ-000002', '2026-08-15', $itemX, $customerB->id, 5, 2000);

    $rows = LaporanPenjualanTable::buildRows('2026-08-01', '2026-08-31', (string) $customerA->id, 'barang');

    $rowX = collect($rows)->firstWhere('nama', $itemX->descr);
    $total = collect($rows)->firstWhere('nama', 'Total');

    expect((float) $rowX['qty'])->toBe(10.0)
        ->and((float) $rowX['omzet'])->toBe(10000.0)
        ->and((float) $total['omzet'])->toBe(10000.0);
});

it('loads the sales report page and renders the summary', function () {
    $customer = Customer::create(['descr' => 'Customer A']);
    $itemX = TbStock::factory()->create();

    buatTransaksi('SALE', 'PJ-000001', '2026-08-05', $itemX, $customer->id, 10, 1000);

    Livewire::test(LaporanPenjualan::class)
        ->set('date_from', '2026-08-01')
        ->set('date_until', '2026-08-31')
        ->set('group_by', 'barang')
        ->assertOk()
        ->assertSee($itemX->descr)
        ->assertSee('Total');

    Livewire::test(LaporanPenjualan::class)
        ->set('date_from', '2026-08-01')
        ->set('date_until', '2026-08-31')
        ->set('group_by', 'customer')
        ->assertOk()
        ->assertSee('Customer A');
});
