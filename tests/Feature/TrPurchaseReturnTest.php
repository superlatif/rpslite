<?php

use App\Filament\Resources\TrPurchaseReturns\Pages\ListTrPurchaseReturns;
use App\Filament\Resources\TrPurchases\Pages\ListTrPurchases;
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

function kreditPurchaseInvoice(string $trsNumber, string $date, Supplier $supplier, float $remaining, float $paid = 0): TrHeader
{
    return TrHeader::factory()->create([
        'trs_number' => $trsNumber,
        'trs_date' => $date,
        'trr_type' => 'PURCHASE',
        'supplier_id' => $supplier->id,
        'trs_type' => 1,
        'total_amount' => $remaining + $paid,
        'paid_amount' => $paid,
        'remaining_amount' => $remaining,
    ]);
}

function createPurchaseReturn(array $data)
{
    return Livewire::test(ListTrPurchaseReturns::class)
        ->callAction('create', data: $data);
}

function purchaseReturnDetail(TbStock $stock, float $qty, float $unitPrice): array
{
    return [
        'stock_id' => $stock->id,
        'qty' => $qty,
        'unit_price' => $unitPrice,
        'subtotal' => round($qty * $unitPrice, 2),
    ];
}

it('records a credit purchase with zero paid and the total as remaining', function () {
    $supplier = Supplier::factory()->create();
    $stock = TbStock::factory()->create();

    Livewire::test(ListTrPurchases::class)
        ->callAction('create', data: [
            'trs_date' => '2026-08-05',
            'supplier_id' => $supplier->id,
            'trs_type' => 1,
            'details' => [purchaseReturnDetail($stock, 3, 10000)],
        ])->assertHasNoErrors()
        ->assertNotified();

    $purchase = TrHeader::where('trr_type', 'PURCHASE')->sole();

    expect($purchase->trs_type)->toBe(1)
        ->and($purchase->paid_amount)->toBe('0.00')
        ->and($purchase->remaining_amount)->toBe('30000.00')
        ->and($stock->fresh()->stock)->toBe(3);
});

it('requires a purchase invoice number for the return', function () {
    $supplier = Supplier::factory()->create();
    $stock = TbStock::factory()->create(['stock' => 5]);

    createPurchaseReturn([
        'trs_date' => '2026-08-05',
        'supplier_id' => $supplier->id,
        'trs_type' => 1,
        'details' => [purchaseReturnDetail($stock, 1, 10000)],
    ])->assertHasErrors(['mountedActions.0.data.source_purchase_id']);

    expect(TrHeader::where('trr_type', 'PURCHASE_RET')->count())->toBe(0);
});

it('creates a credit return that fills paid_amount and reduces the referenced invoice', function () {
    $supplier = Supplier::factory()->create();
    $stock = TbStock::factory()->create(['stock' => 5]);
    $purchase = kreditPurchaseInvoice('PB-000001', '2026-08-01', $supplier, 100000);

    createPurchaseReturn([
        'trs_date' => '2026-08-05',
        'supplier_id' => $supplier->id,
        'source_purchase_id' => $purchase->id,
        'trs_type' => 1,
        'details' => [purchaseReturnDetail($stock, 2, 30000)],
    ])->assertHasNoErrors()
        ->assertNotified();

    $retur = TrHeader::where('trr_type', 'PURCHASE_RET')->sole();

    expect($retur->paid_amount)->toBe('60000.00')
        ->and($retur->remaining_amount)->toBe('0.00')
        ->and($retur->trs_type)->toBe(1)
        ->and($retur->source_purchase_id)->toBe($purchase->id)
        ->and($purchase->fresh()->remaining_amount)->toBe('40000.00')
        ->and($purchase->fresh()->paid_amount)->toBe('60000.00')
        ->and($stock->fresh()->stock)->toBe(3);
});

it('treats a cash return as the supplier returning cash while still reducing the invoice', function () {
    $supplier = Supplier::factory()->create();
    $stock = TbStock::factory()->create(['stock' => 5]);
    $purchase = kreditPurchaseInvoice('PB-000001', '2026-08-01', $supplier, 100000);

    createPurchaseReturn([
        'trs_date' => '2026-08-05',
        'supplier_id' => $supplier->id,
        'source_purchase_id' => $purchase->id,
        'trs_type' => 0,
        'details' => [purchaseReturnDetail($stock, 1, 40000)],
    ])->assertHasNoErrors()
        ->assertNotified();

    $retur = TrHeader::where('trr_type', 'PURCHASE_RET')->sole();

    expect($retur->paid_amount)->toBe('40000.00')
        ->and($retur->remaining_amount)->toBe('0.00')
        ->and($retur->trs_type)->toBe(0)
        ->and($purchase->fresh()->remaining_amount)->toBe('60000.00')
        ->and($purchase->fresh()->paid_amount)->toBe('40000.00');
});

it('rejects a return whose total exceeds the invoice remaining amount', function () {
    $supplier = Supplier::factory()->create();
    $stock = TbStock::factory()->create(['stock' => 5]);
    $purchase = kreditPurchaseInvoice('PB-000001', '2026-08-01', $supplier, 50000);

    createPurchaseReturn([
        'trs_date' => '2026-08-05',
        'supplier_id' => $supplier->id,
        'source_purchase_id' => $purchase->id,
        'trs_type' => 1,
        'details' => [purchaseReturnDetail($stock, 1, 60000)],
    ])->assertHasErrors(['source_purchase_id']);

    expect(TrHeader::where('trr_type', 'PURCHASE_RET')->count())->toBe(0)
        ->and($purchase->fresh()->remaining_amount)->toBe('50000.00')
        ->and($purchase->fresh()->paid_amount)->toBe('0.00')
        ->and($stock->fresh()->stock)->toBe(5);
});

it('rejects a return against an invoice of another supplier', function () {
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();
    $stock = TbStock::factory()->create(['stock' => 5]);
    $purchaseB = kreditPurchaseInvoice('PB-000001', '2026-08-01', $supplierB, 100);

    createPurchaseReturn([
        'trs_date' => '2026-08-05',
        'supplier_id' => $supplierA->id,
        'source_purchase_id' => $purchaseB->id,
        'trs_type' => 1,
        'details' => [purchaseReturnDetail($stock, 1, 10000)],
    ])->assertHasErrors(['mountedActions.0.data.source_purchase_id']);

    expect(TrHeader::where('trr_type', 'PURCHASE_RET')->count())->toBe(0);
});

it('rejects a return against an invoice with no remaining balance', function () {
    $supplier = Supplier::factory()->create();
    $stock = TbStock::factory()->create(['stock' => 5]);
    $paidOff = kreditPurchaseInvoice('PB-000001', '2026-08-01', $supplier, 0, 100);

    createPurchaseReturn([
        'trs_date' => '2026-08-05',
        'supplier_id' => $supplier->id,
        'source_purchase_id' => $paidOff->id,
        'trs_type' => 1,
        'details' => [purchaseReturnDetail($stock, 1, 10000)],
    ])->assertHasErrors(['mountedActions.0.data.source_purchase_id']);

    expect(TrHeader::where('trr_type', 'PURCHASE_RET')->count())->toBe(0);
});
