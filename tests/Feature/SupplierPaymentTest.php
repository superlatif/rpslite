<?php

use App\Filament\Resources\SupplierPayments\Pages\ListSupplierPayments;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TrHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

function kreditPurchase(string $trsNumber, string $date, Supplier $supplier, float $remaining): TrHeader
{
    return TrHeader::factory()->create([
        'trs_number' => $trsNumber,
        'trs_date' => $date,
        'trr_type' => 'PURCHASE',
        'supplier_id' => $supplier->id,
        'trs_type' => 1,
        'remaining_amount' => $remaining,
    ]);
}

function kreditPurchaseReturn(string $trsNumber, string $date, Supplier $supplier, float $remaining): TrHeader
{
    return TrHeader::factory()->create([
        'trs_number' => $trsNumber,
        'trs_date' => $date,
        'trr_type' => 'PURCHASE_RET',
        'supplier_id' => $supplier->id,
        'trs_type' => 1,
        'remaining_amount' => $remaining,
    ]);
}

function createSupplierPayment(array $data)
{
    return Livewire::test(ListSupplierPayments::class)
        ->callAction('create')
        ->fillForm($data)
        ->callMountedAction();
}

it('caps the installment amount at the net balance after purchase returns', function () {
    $supplier = Supplier::factory()->create();

    $purchase = kreditPurchase('PB-000001', '2026-08-01', $supplier, 100);
    kreditPurchaseReturn('RPB-000001', '2026-08-05', $supplier, 30);

    createSupplierPayment([
        'supplier_id' => $supplier->id,
        'tr_header_id' => $purchase->id,
        'payment_date' => '2026-08-10',
        'amount' => 80,
    ])->assertHasErrors(['amount']);

    expect(SupplierPayment::count())->toBe(0)
        ->and($purchase->fresh()->remaining_amount)->toBe('100.00');
});

it('applies a linked installment against the selected invoice', function () {
    $supplier = Supplier::factory()->create();

    $purchase = kreditPurchase('PB-000001', '2026-08-01', $supplier, 100);

    createSupplierPayment([
        'supplier_id' => $supplier->id,
        'tr_header_id' => $purchase->id,
        'payment_date' => '2026-08-10',
        'amount' => 40,
    ])->assertHasNoErrors();

    expect(SupplierPayment::count())->toBe(1)
        ->and($purchase->fresh()->remaining_amount)->toBe('60.00')
        ->and($purchase->fresh()->paid_amount)->toBe('40.00');
});

it('distributes payments without an invoice across invoices FIFO', function () {
    $supplier = Supplier::factory()->create();

    $oldest = kreditPurchase('PB-000001', '2026-07-01', $supplier, 100);
    $newest = kreditPurchase('PB-000002', '2026-08-01', $supplier, 50);

    createSupplierPayment([
        'supplier_id' => $supplier->id,
        'payment_date' => '2026-08-10',
        'amount' => 120,
    ])->assertHasNoErrors();

    expect($oldest->fresh()->remaining_amount)->toBe('0.00')
        ->and($oldest->fresh()->paid_amount)->toBe('100.00')
        ->and($newest->fresh()->remaining_amount)->toBe('30.00')
        ->and($newest->fresh()->paid_amount)->toBe('20.00');
});

it('rejects paying an invoice that belongs to another supplier', function () {
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();

    kreditPurchase('PB-000001', '2026-08-01', $supplierA, 100);
    $purchaseB = kreditPurchase('PB-000002', '2026-08-01', $supplierB, 100);

    createSupplierPayment([
        'supplier_id' => $supplierA->id,
        'tr_header_id' => $purchaseB->id,
        'payment_date' => '2026-08-10',
        'amount' => 10,
    ])->assertHasErrors(['mountedActions.0.data.tr_header_id']);

    expect(SupplierPayment::count())->toBe(0);
});

it('restores the invoice balance when an installment is deleted', function () {
    $supplier = Supplier::factory()->create();

    $purchase = kreditPurchase('PB-000001', '2026-08-01', $supplier, 100);

    createSupplierPayment([
        'supplier_id' => $supplier->id,
        'tr_header_id' => $purchase->id,
        'payment_date' => '2026-08-10',
        'amount' => 40,
    ])->assertHasNoErrors();

    $payment = SupplierPayment::firstOrFail();
    $payment->supplier->reversePayment((float) $payment->amount, $payment->tr_header_id);
    $payment->delete();

    expect($purchase->fresh()->remaining_amount)->toBe('100.00')
        ->and($purchase->fresh()->paid_amount)->toBe('0.00');
});

it('shows the net balance helper text when a supplier is selected', function () {
    $supplier = Supplier::factory()->create();

    kreditPurchase('PB-000001', '2026-08-01', $supplier, 100);
    kreditPurchaseReturn('RPB-000001', '2026-08-05', $supplier, 30);

    $component = Livewire::test(ListSupplierPayments::class)
        ->callAction('create')
        ->set('mountedActions.0.data.supplier_id', (string) $supplier->id);

    expect($component->get('mountedActions.0.data')['amount'])->toBe(70.0);
});

it('consumes the return credit proportionally when an installment is paid', function () {
    $supplier = Supplier::factory()->create();

    $purchase = kreditPurchase('PB-000001', '2026-08-01', $supplier, 100);
    $return = kreditPurchaseReturn('RPB-000001', '2026-08-05', $supplier, 40);

    createSupplierPayment([
        'supplier_id' => $supplier->id,
        'payment_date' => '2026-08-10',
        'amount' => 30,
    ])->assertHasNoErrors();

    expect($purchase->fresh()->remaining_amount)->toBe('50.00')
        ->and($purchase->fresh()->paid_amount)->toBe('50.00')
        ->and($return->fresh()->remaining_amount)->toBe('20.00')
        ->and($return->fresh()->paid_amount)->toBe('20.00')
        ->and($supplier->fresh()->netPayable())->toBe(30.0);
});

it('settles both invoice and return credit when paying the full net balance', function () {
    $supplier = Supplier::factory()->create();

    $purchase = kreditPurchase('PB-000001', '2026-08-01', $supplier, 100);
    $return = kreditPurchaseReturn('RPB-000001', '2026-08-05', $supplier, 40);

    createSupplierPayment([
        'supplier_id' => $supplier->id,
        'payment_date' => '2026-08-10',
        'amount' => 60,
    ])->assertHasNoErrors();

    expect($purchase->fresh()->remaining_amount)->toBe('0.00')
        ->and($purchase->fresh()->paid_amount)->toBe('100.00')
        ->and($return->fresh()->remaining_amount)->toBe('0.00')
        ->and($return->fresh()->paid_amount)->toBe('40.00')
        ->and($supplier->fresh()->netPayable())->toBe(0.0);
});

it('applies a linked installment to the selected invoice while consuming the return credit', function () {
    $supplier = Supplier::factory()->create();

    $purchase = kreditPurchase('PB-000001', '2026-08-01', $supplier, 100);
    $return = kreditPurchaseReturn('RPB-000001', '2026-08-05', $supplier, 40);

    createSupplierPayment([
        'supplier_id' => $supplier->id,
        'tr_header_id' => $purchase->id,
        'payment_date' => '2026-08-10',
        'amount' => 30,
    ])->assertHasNoErrors();

    expect($purchase->fresh()->remaining_amount)->toBe('50.00')
        ->and($return->fresh()->remaining_amount)->toBe('20.00');
});

it('distributes the purchase portion across invoices FIFO before consuming the return credit', function () {
    $supplier = Supplier::factory()->create();

    $oldest = kreditPurchase('PB-000001', '2026-07-01', $supplier, 100);
    $newest = kreditPurchase('PB-000002', '2026-08-01', $supplier, 50);
    $return = kreditPurchaseReturn('RPB-000001', '2026-08-05', $supplier, 40);

    createSupplierPayment([
        'supplier_id' => $supplier->id,
        'payment_date' => '2026-08-10',
        'amount' => 55,
    ])->assertHasNoErrors();

    expect($oldest->fresh()->remaining_amount)->toBe('25.00')
        ->and($oldest->fresh()->paid_amount)->toBe('75.00')
        ->and($newest->fresh()->remaining_amount)->toBe('50.00')
        ->and($return->fresh()->remaining_amount)->toBe('20.00')
        ->and($supplier->fresh()->netPayable())->toBe(55.0);
});

it('restores invoice and return credit when a linked installment is deleted', function () {
    $supplier = Supplier::factory()->create();

    $purchase = kreditPurchase('PB-000001', '2026-08-01', $supplier, 100);
    $return = kreditPurchaseReturn('RPB-000001', '2026-08-05', $supplier, 40);

    createSupplierPayment([
        'supplier_id' => $supplier->id,
        'tr_header_id' => $purchase->id,
        'payment_date' => '2026-08-10',
        'amount' => 30,
    ])->assertHasNoErrors();

    $payment = SupplierPayment::firstOrFail();
    $payment->supplier->reversePayment((float) $payment->amount, $payment->tr_header_id);
    $payment->delete();

    expect($purchase->fresh()->remaining_amount)->toBe('100.00')
        ->and($purchase->fresh()->paid_amount)->toBe('0.00')
        ->and($return->fresh()->remaining_amount)->toBe('40.00')
        ->and($return->fresh()->paid_amount)->toBe('0.00')
        ->and($supplier->fresh()->netPayable())->toBe(60.0);
});
