<?php

use App\Filament\Resources\TrSales\Pages\ListTrSales;
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

it('renders the sales struk with customer and payment info', function () {
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

    $this->get(route('filament.admin.penjualan.struk', $sale))
        ->assertOk()
        ->assertSee('PJ-000001')
        ->assertSee('Budi Santoso')
        ->assertSee('Jl. Merdeka No. 10')
        ->assertSee('081234567890')
        ->assertSee('KREDIT')
        ->assertSee('Sisa Piutang')
        ->assertSee('Bollpoin')
        ->assertSee('TOTAL');
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

    $this->get(route('filament.admin.penjualan.struk', $sale))
        ->assertOk()
        ->assertSee('TUNAI')
        ->assertDontSee('Sisa Piutang');
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

it('registers a cetak struk action on the sales table that opens the struk URL', function () {
    $sale = TrHeader::factory()->create([
        'trr_type' => 'SALE',
        'trs_number' => 'PJ-000001',
        'trs_date' => now(),
    ]);

    Livewire::test(ListTrSales::class)
        ->assertTableActionHasUrl(
            'cetakStruk',
            route('filament.admin.penjualan.struk', $sale),
            $sale->getKey(),
        )
        ->assertTableActionShouldOpenUrlInNewTab('cetakStruk', $sale->getKey());
});
