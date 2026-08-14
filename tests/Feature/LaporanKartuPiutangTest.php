<?php

use App\Filament\Pages\LaporanKartuPiutang;
use App\Filament\Pages\Tables\LaporanKartuPiutangTable;
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

function piutangHeader(string $trsNumber, string $type, string $date, Customer $customer, float $total): TrHeader
{
    return TrHeader::factory()->create([
        'trs_number' => $trsNumber,
        'trs_date' => $date,
        'trr_type' => $type,
        'customer_id' => $customer->id,
        'trs_type' => 1,
        'total_amount' => $total,
        'remaining_amount' => $total,
    ]);
}

function piutangPayment(Customer $customer, string $date, float $amount): CustomerPayment
{
    return CustomerPayment::create([
        'customer_id' => $customer->id,
        'payment_date' => $date,
        'amount' => $amount,
    ]);
}

it('builds a per-customer receivable ledger with opening balance', function () {
    $customer = Customer::create(['descr' => 'Customer A']);

    piutangHeader('PJ-000001', 'SALE', '2026-07-01', $customer, 100);
    piutangHeader('PJ-000002', 'SALE', '2026-08-10', $customer, 50);
    piutangHeader('RPJ-000001', 'SALE_RET', '2026-08-05', $customer, 40);
    piutangPayment($customer, '2026-07-15', 20);
    piutangPayment($customer, '2026-08-01', 30);

    $rows = LaporanKartuPiutangTable::buildRows((string) $customer->id, '2026-08-01', '2026-08-31');
    $byJenis = collect($rows);

    expect($byJenis->firstWhere('jenis', 'Saldo Awal')['saldo'])->toBe(80.0)
        ->and($byJenis->firstWhere('trs_number', 'PJ-000002')['masuk'])->toBe(50.0)
        ->and($byJenis->firstWhere('jenis', 'Pembayaran')['saldo'])->toBe(50.0)
        ->and($byJenis->firstWhere('trs_number', 'RPJ-000001')['keluar'])->toBe(40.0)
        ->and($byJenis->firstWhere('trs_number', 'RPJ-000001')['saldo'])->toBe(10.0)
        ->and($byJenis->firstWhere('jenis', 'Pembayaran')['keluar'])->toBe(30.0)
        ->and($byJenis->firstWhere('trs_number', 'PJ-000002')['saldo'])->toBe(60.0)
        ->and($byJenis->firstWhere('jenis', 'Total Masuk')['masuk'])->toBe(50.0)
        ->and($byJenis->firstWhere('jenis', 'Total Keluar')['keluar'])->toBe(70.0)
        ->and($byJenis->firstWhere('jenis', 'Saldo Akhir')['saldo'])->toBe(60.0);
});

it('lists all customers when no customer filter is given', function () {
    $customerA = Customer::create(['descr' => 'Customer A']);
    $customerB = Customer::create(['descr' => 'Customer B']);

    piutangHeader('PJ-000001', 'SALE', '2026-08-05', $customerA, 100);
    piutangHeader('PJ-000002', 'SALE', '2026-08-06', $customerB, 200);

    $rows = LaporanKartuPiutangTable::buildRows(null, '2026-08-01', '2026-08-31');

    expect(collect($rows)->where('jenis', 'Saldo Awal')->count())->toBe(2)
        ->and(collect($rows)->pluck('customer')->unique()->values()->all())
        ->toBe(['Customer A', 'Customer B']);
});

it('only includes customers with activity', function () {
    $customerA = Customer::create(['descr' => 'Customer A']);
    Customer::create(['descr' => 'Customer Tanpa Transaksi']);

    piutangHeader('PJ-000001', 'SALE', '2026-08-05', $customerA, 100);

    $rows = LaporanKartuPiutangTable::buildRows(null, '2026-08-01', '2026-08-31');

    expect(collect($rows)->pluck('customer')->unique()->values()->all())
        ->toBe(['Customer A']);
});

it('loads the receivable card page and renders rows', function () {
    $customer = Customer::create(['descr' => 'Customer A']);

    piutangHeader('PJ-000001', 'SALE', '2026-08-05', $customer, 100);

    Livewire::test(LaporanKartuPiutang::class)
        ->set('customer_id', (string) $customer->id)
        ->set('date_from', '2026-08-01')
        ->set('date_until', '2026-08-31')
        ->assertOk()
        ->assertSee('Customer A')
        ->assertSee('Saldo Akhir');
});
