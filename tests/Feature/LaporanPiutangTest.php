<?php

use App\Filament\Pages\LaporanPiutang;
use App\Filament\Pages\Tables\LaporanPiutangTable;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\TrHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

function headerKredit(string $trsNumber, string $type, string $date, Customer $customer, float $remaining): TrHeader
{
    return TrHeader::factory()->create([
        'trs_number' => $trsNumber,
        'trs_date' => $date,
        'trr_type' => $type,
        'customer_id' => $customer->id,
        'trs_type' => 1,
        'remaining_amount' => $remaining,
    ]);
}

it('ages credit sales into buckets by invoice age', function () {
    $customer = Customer::create(['descr' => 'Customer A']);

    headerKredit('PJ-000001', 'SALE', '2026-08-05', $customer, 100);
    headerKredit('PJ-000002', 'SALE', '2026-07-01', $customer, 200);
    headerKredit('PJ-000003', 'SALE', '2026-04-01', $customer, 300);

    $rows = LaporanPiutangTable::buildRows('2026-08-13', '');
    $row = collect($rows)->firstWhere('customer', 'Customer A');

    expect((float) $row['age30'])->toBe(100.0)
        ->and((float) $row['age60'])->toBe(200.0)
        ->and((float) $row['age90p'])->toBe(300.0)
        ->and((float) $row['total'])->toBe(600.0);
});

it('shows payment history and reduces outstanding', function () {
    $customer = Customer::create(['descr' => 'Customer A']);
    $sale = headerKredit('PJ-000001', 'SALE', '2026-08-05', $customer, 60);

    CustomerPayment::create([
        'customer_id' => $customer->id,
        'tr_header_id' => $sale->id,
        'payment_date' => '2026-08-10',
        'amount' => 40,
    ]);

    $rows = LaporanPiutangTable::buildRows('2026-08-13', '');
    $row = collect($rows)->firstWhere('customer', 'Customer A');

    expect((float) $row['total'])->toBe(60.0)
        ->and((float) $row['dibayar'])->toBe(40.0)
        ->and($row['angsuran'])->toHaveCount(1)
        ->and($row['angsuran'][0])->toContain('Rp 40');
});

it('subtracts credit sales returns from outstanding', function () {
    $customer = Customer::create(['descr' => 'Customer A']);

    headerKredit('PJ-000001', 'SALE', '2026-08-05', $customer, 100);
    headerKredit('RPJ-000001', 'SALE_RET', '2026-08-08', $customer, 30);

    $rows = LaporanPiutangTable::buildRows('2026-08-13', '');
    $row = collect($rows)->firstWhere('customer', 'Customer A');

    expect((float) $row['retur'])->toBe(30.0)
        ->and((float) $row['total'])->toBe(70.0);
});

it('does not count payments made after the as-of date', function () {
    $customer = Customer::create(['descr' => 'Customer A']);
    $sale = headerKredit('PJ-000001', 'SALE', '2026-08-05', $customer, 60);

    CustomerPayment::create([
        'customer_id' => $customer->id,
        'tr_header_id' => $sale->id,
        'payment_date' => '2026-08-20',
        'amount' => 40,
    ]);

    $rows = LaporanPiutangTable::buildRows('2026-08-13', '');
    $row = collect($rows)->firstWhere('customer', 'Customer A');

    expect((float) $row['total'])->toBe(100.0)
        ->and((float) $row['dibayar'])->toBe(0.0)
        ->and($row['angsuran'])->toHaveCount(0);
});

it('loads the aging report page and renders rows', function () {
    $customer = Customer::create(['descr' => 'Customer A']);

    headerKredit('PJ-000001', 'SALE', '2026-08-05', $customer, 100);

    Livewire::test(LaporanPiutang::class)
        ->set('asof', '2026-08-13')
        ->assertOk()
        ->assertSee('Customer A')
        ->assertSee('Total');
});
