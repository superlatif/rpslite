<?php

use App\Filament\Resources\CustomerPayments\Pages\ListCustomerPayments;
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

function kreditSale(string $trsNumber, string $date, Customer $customer, float $remaining): TrHeader
{
    return TrHeader::factory()->create([
        'trs_number' => $trsNumber,
        'trs_date' => $date,
        'trr_type' => 'SALE',
        'customer_id' => $customer->id,
        'trs_type' => 1,
        'remaining_amount' => $remaining,
    ]);
}

function kreditReturn(string $trsNumber, string $date, Customer $customer, float $remaining): TrHeader
{
    return TrHeader::factory()->create([
        'trs_number' => $trsNumber,
        'trs_date' => $date,
        'trr_type' => 'SALE_RET',
        'customer_id' => $customer->id,
        'trs_type' => 1,
        'remaining_amount' => $remaining,
    ]);
}

function createPayment(array $data)
{
    return Livewire::test(ListCustomerPayments::class)
        ->callAction('create')
        ->fillForm($data)
        ->callMountedAction();
}

it('caps the installment amount at the net balance after sales returns', function () {
    $customer = Customer::create(['descr' => 'Customer A']);

    $sale = kreditSale('PJ-000001', '2026-08-01', $customer, 100);
    kreditReturn('RPJ-000001', '2026-08-05', $customer, 30);

    createPayment([
        'customer_id' => $customer->id,
        'tr_header_id' => $sale->id,
        'payment_date' => '2026-08-10',
        'amount' => 80,
    ])->assertHasErrors(['amount']);

    expect(CustomerPayment::count())->toBe(0)
        ->and($sale->fresh()->remaining_amount)->toBe('100.00');
});

it('applies a linked installment against the selected invoice', function () {
    $customer = Customer::create(['descr' => 'Customer A']);

    $sale = kreditSale('PJ-000001', '2026-08-01', $customer, 100);

    createPayment([
        'customer_id' => $customer->id,
        'tr_header_id' => $sale->id,
        'payment_date' => '2026-08-10',
        'amount' => 40,
    ])->assertHasNoErrors();

    expect(CustomerPayment::count())->toBe(1)
        ->and($sale->fresh()->remaining_amount)->toBe('60.00')
        ->and($sale->fresh()->paid_amount)->toBe('40.00');
});

it('distributes payments without an invoice across invoices FIFO', function () {
    $customer = Customer::create(['descr' => 'Customer A']);

    $oldest = kreditSale('PJ-000001', '2026-07-01', $customer, 100);
    $newest = kreditSale('PJ-000002', '2026-08-01', $customer, 50);

    createPayment([
        'customer_id' => $customer->id,
        'payment_date' => '2026-08-10',
        'amount' => 120,
    ])->assertHasNoErrors();

    expect($oldest->fresh()->remaining_amount)->toBe('0.00')
        ->and($oldest->fresh()->paid_amount)->toBe('100.00')
        ->and($newest->fresh()->remaining_amount)->toBe('30.00')
        ->and($newest->fresh()->paid_amount)->toBe('20.00');
});

it('rejects paying an invoice that belongs to another customer', function () {
    $customerA = Customer::create(['descr' => 'Customer A']);
    $customerB = Customer::create(['descr' => 'Customer B']);

    kreditSale('PJ-000001', '2026-08-01', $customerA, 100);
    $saleB = kreditSale('PJ-000002', '2026-08-01', $customerB, 100);

    createPayment([
        'customer_id' => $customerA->id,
        'tr_header_id' => $saleB->id,
        'payment_date' => '2026-08-10',
        'amount' => 10,
    ])->assertHasErrors(['mountedActions.0.data.tr_header_id']);

    expect(CustomerPayment::count())->toBe(0);
});

it('restores the invoice balance when an installment is deleted', function () {
    $customer = Customer::create(['descr' => 'Customer A']);

    $sale = kreditSale('PJ-000001', '2026-08-01', $customer, 100);

    createPayment([
        'customer_id' => $customer->id,
        'tr_header_id' => $sale->id,
        'payment_date' => '2026-08-10',
        'amount' => 40,
    ])->assertHasNoErrors();

    $payment = CustomerPayment::firstOrFail();
    $payment->customer->reversePayment((float) $payment->amount, $payment->tr_header_id);
    $payment->delete();

    expect($sale->fresh()->remaining_amount)->toBe('100.00')
        ->and($sale->fresh()->paid_amount)->toBe('0.00');
});

it('shows the net balance helper text when a customer is selected', function () {
    $customer = Customer::create(['descr' => 'Customer A']);

    kreditSale('PJ-000001', '2026-08-01', $customer, 100);
    kreditReturn('RPJ-000001', '2026-08-05', $customer, 30);

    $component = Livewire::test(ListCustomerPayments::class)
        ->callAction('create')
        ->set('mountedActions.0.data.customer_id', (string) $customer->id);

    expect($component->get('mountedActions.0.data')['amount'])->toBe(70.0);
});
