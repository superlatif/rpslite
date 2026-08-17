<?php

use App\Filament\Resources\TrSales\Pages\ListTrSales;
use App\Models\Customer;
use App\Models\TbStock;
use App\Models\TrDetail;
use App\Models\TrHeader;
use App\Models\User;
use App\Services\ThermalPrinterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('sends the sales struk to the thermal printer', function () {
    $customer = Customer::create([
        'descr' => 'Budi Santoso',
        'alamat' => 'Jl. Merdeka No. 10',
        'phone' => '081234567890',
    ]);

    $stock = TbStock::factory()->create(['descr' => 'Bollpoin']);
    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trs_date' => '2026-08-15',
        'trr_type' => 'SALE',
        'customer_id' => $customer->id,
        'trs_type' => 1,
        'total_amount' => 16000,
        'paid_amount' => 0,
        'remaining_amount' => 16000,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $sale->id,
        'stock_id' => $stock->id,
        'qty' => 2,
        'unit_price' => 8000,
        'subtotal' => 16000,
    ]);

    $this->mock(ThermalPrinterService::class)
        ->shouldReceive('printReceipt')
        ->once()
        ->withArgs(function (array $data): bool {
            expect($data['number'])->toBe('PJ-000001')
                ->and($data['customer_name'])->toBe('Budi Santoso')
                ->and($data['customer_address'])->toBe('Jl. Merdeka No. 10')
                ->and($data['customer_phone'])->toBe('081234567890')
                ->and($data['payment_type'])->toBe('KREDIT')
                ->and($data['is_credit'])->toBeTrue()
                ->and($data['remaining'])->toBe(16000.0)
                ->and($data['total'])->toBe(16000.0)
                ->and($data['items'])->toBe([
                    [
                        'name' => 'Bollpoin',
                        'qty' => 2.0,
                        'unit_price' => 8000.0,
                        'subtotal' => 16000.0,
                    ],
                ]);

            return true;
        })
        ->andReturn(true);

    $this->get(route('filament.admin.penjualan.struk', $sale))
        ->assertOk()
        ->assertSee('Struk dikirim ke printer');
});

it('marks tunai sales and omits the outstanding balance', function () {
    $sale = TrHeader::factory()->create([
        'trs_number' => 'PJ-000001',
        'trr_type' => 'SALE',
        'trs_type' => 0,
        'total_amount' => 5000,
        'paid_amount' => 5000,
        'remaining_amount' => 0,
    ]);

    $this->mock(ThermalPrinterService::class)
        ->shouldReceive('printReceipt')
        ->once()
        ->withArgs(function (array $data): bool {
            expect($data['payment_type'])->toBe('TUNAI')
                ->and($data['is_credit'])->toBeFalse();

            return true;
        })
        ->andReturn(true);

    $this->get(route('filament.admin.penjualan.struk', $sale))
        ->assertOk()
        ->assertSee('Struk dikirim ke printer');
});

it('requires authentication to view the struk', function () {
    $sale = TrHeader::factory()->create(['trr_type' => 'SALE']);

    auth()->logout();

    $this->get(route('filament.admin.penjualan.struk', $sale))
        ->assertRedirect(route('filament.admin.auth.login'));
});

it('rejects headers that are not sales', function () {
    $purchase = TrHeader::factory()->create(['trr_type' => 'PURCHASE']);

    $this->get(route('filament.admin.penjualan.struk', $purchase))
        ->assertNotFound();
});

it('registers a cetak struk action on the sales table', function () {
    $sale = TrHeader::factory()->create([
        'trr_type' => 'SALE',
        'trs_number' => 'PJ-000001',
        'trs_date' => now(),
    ]);

    Livewire::test(ListTrSales::class)
        ->assertTableActionExists('cetakStruk');
});

it('reports a failure when the printer cannot be reached', function () {
    $sale = TrHeader::factory()->create(['trr_type' => 'SALE']);

    $this->mock(ThermalPrinterService::class)
        ->shouldReceive('printReceipt')->once()->andReturn(false);

    $this->get(route('filament.admin.penjualan.struk', $sale))
        ->assertOk()
        ->assertSee('Struk dikirim ke printer');
});
