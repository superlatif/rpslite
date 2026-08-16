<?php

use App\Filament\Resources\TrSaleReturns\Pages\ListTrSaleReturns;
use App\Models\Customer;
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

function kreditSaleInvoice(string $trsNumber, string $date, Customer $customer, float $remaining, float $paid = 0): TrHeader
{
    return TrHeader::factory()->create([
        'trs_number' => $trsNumber,
        'trs_date' => $date,
        'trr_type' => 'SALE',
        'customer_id' => $customer->id,
        'trs_type' => 1,
        'total_amount' => $remaining + $paid,
        'paid_amount' => $paid,
        'remaining_amount' => $remaining,
    ]);
}

function createSaleReturn(array $data)
{
    return Livewire::test(ListTrSaleReturns::class)
        ->callAction('create', data: $data);
}

function returnDetail(TbStock $stock, float $qty, float $unitPrice): array
{
    return [
        'stock_id' => $stock->id,
        'qty' => $qty,
        'unit_price' => $unitPrice,
        'subtotal' => round($qty * $unitPrice, 2),
    ];
}

it('requires a sales invoice number for the return', function () {
    $customer = Customer::create(['descr' => 'Customer A']);
    $stock = TbStock::factory()->create();

    createSaleReturn([
        'trs_date' => '2026-08-05',
        'customer_id' => $customer->id,
        'trs_type' => 1,
        'details' => [returnDetail($stock, 1, 10000)],
    ])->assertHasErrors(['mountedActions.0.data.source_sale_id']);

    expect(TrHeader::where('trr_type', 'SALE_RET')->count())->toBe(0);
});

it('creates a credit return that fills paid_amount and reduces the referenced invoice', function () {
    $customer = Customer::create(['descr' => 'Customer A']);
    $stock = TbStock::factory()->create();
    $sale = kreditSaleInvoice('PJ-000001', '2026-08-01', $customer, 100000);

    createSaleReturn([
        'trs_date' => '2026-08-05',
        'customer_id' => $customer->id,
        'source_sale_id' => $sale->id,
        'trs_type' => 1,
        'details' => [returnDetail($stock, 2, 30000)],
    ])->assertHasNoErrors()
        ->assertNotified();

    $retur = TrHeader::where('trr_type', 'SALE_RET')->sole();

    expect($retur->paid_amount)->toBe('60000.00')
        ->and($retur->remaining_amount)->toBe('0.00')
        ->and($retur->trs_type)->toBe(1)
        ->and($retur->source_sale_id)->toBe($sale->id)
        ->and($sale->fresh()->remaining_amount)->toBe('40000.00')
        ->and($sale->fresh()->paid_amount)->toBe('60000.00')
        ->and($stock->fresh()->stock)->toBe(2)
        ->and($customer->fresh()->netReceivable())->toBe(40000.0);
});

it('treats a cash return as the owner returning cash while still reducing the invoice', function () {
    $customer = Customer::create(['descr' => 'Customer A']);
    $stock = TbStock::factory()->create();
    $sale = kreditSaleInvoice('PJ-000001', '2026-08-01', $customer, 100000);

    createSaleReturn([
        'trs_date' => '2026-08-05',
        'customer_id' => $customer->id,
        'source_sale_id' => $sale->id,
        'trs_type' => 0,
        'details' => [returnDetail($stock, 1, 40000)],
    ])->assertHasNoErrors()
        ->assertNotified();

    $retur = TrHeader::where('trr_type', 'SALE_RET')->sole();

    expect($retur->paid_amount)->toBe('40000.00')
        ->and($retur->remaining_amount)->toBe('0.00')
        ->and($retur->trs_type)->toBe(0)
        ->and($sale->fresh()->remaining_amount)->toBe('60000.00')
        ->and($sale->fresh()->paid_amount)->toBe('40000.00');
});

it('rejects a return whose total exceeds the invoice remaining amount', function () {
    $customer = Customer::create(['descr' => 'Customer A']);
    $stock = TbStock::factory()->create();
    $sale = kreditSaleInvoice('PJ-000001', '2026-08-01', $customer, 50000);

    createSaleReturn([
        'trs_date' => '2026-08-05',
        'customer_id' => $customer->id,
        'source_sale_id' => $sale->id,
        'trs_type' => 1,
        'details' => [returnDetail($stock, 1, 60000)],
    ])->assertHasErrors(['source_sale_id']);

    expect(TrHeader::where('trr_type', 'SALE_RET')->count())->toBe(0)
        ->and($sale->fresh()->remaining_amount)->toBe('50000.00')
        ->and($sale->fresh()->paid_amount)->toBe('0.00')
        ->and($stock->fresh()->stock)->toBe(0);
});

it('rejects a return against an invoice of another customer', function () {
    $customerA = Customer::create(['descr' => 'Customer A']);
    $customerB = Customer::create(['descr' => 'Customer B']);
    $stock = TbStock::factory()->create();
    $saleB = kreditSaleInvoice('PJ-000001', '2026-08-01', $customerB, 100);

    createSaleReturn([
        'trs_date' => '2026-08-05',
        'customer_id' => $customerA->id,
        'source_sale_id' => $saleB->id,
        'trs_type' => 1,
        'details' => [returnDetail($stock, 1, 10000)],
    ])->assertHasErrors(['mountedActions.0.data.source_sale_id']);

    expect(TrHeader::where('trr_type', 'SALE_RET')->count())->toBe(0);
});

it('rejects a return against an invoice with no remaining balance', function () {
    $customer = Customer::create(['descr' => 'Customer A']);
    $stock = TbStock::factory()->create();
    $paidOff = kreditSaleInvoice('PJ-000001', '2026-08-01', $customer, 0, 100);

    createSaleReturn([
        'trs_date' => '2026-08-05',
        'customer_id' => $customer->id,
        'source_sale_id' => $paidOff->id,
        'trs_type' => 1,
        'details' => [returnDetail($stock, 1, 10000)],
    ])->assertHasErrors(['mountedActions.0.data.source_sale_id']);

    expect(TrHeader::where('trr_type', 'SALE_RET')->count())->toBe(0);
});
