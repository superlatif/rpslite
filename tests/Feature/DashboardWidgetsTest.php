<?php

use App\Filament\Widgets\InventoryStatsOverview;
use App\Filament\Widgets\LowStockTable;
use App\Filament\Widgets\SalesStatsOverview;
use App\Filament\Widgets\SalesTrendChart;
use App\Filament\Widgets\StockValueByCategoryChart;
use App\Models\TbCate;
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

function buatPenjualan(string $trsNumber, string $date, int $trsType, float $total, float $unitPrice, float $hpp, float $qty): void
{
    $header = TrHeader::factory()->create([
        'trs_number' => $trsNumber,
        'trs_date' => $date,
        'trr_type' => 'SALE',
        'total_amount' => $total,
        'trs_type' => $trsType,
        'paid_amount' => $trsType === 1 ? 0 : $total,
        'remaining_amount' => $trsType === 1 ? $total : 0,
    ]);

    TrDetail::factory()->create([
        'tr_header_id' => $header->id,
        'stock_id' => TbStock::factory()->create()->id,
        'qty' => $qty,
        'unit_price' => $unitPrice,
        'hpp_at_transaction' => $hpp,
        'subtotal' => $qty * $unitPrice,
    ]);
}

function buatPembelian(string $trsNumber, string $date, float $total): void
{
    TrHeader::factory()->create([
        'trs_number' => $trsNumber,
        'trs_date' => $date,
        'trr_type' => 'PURCHASE',
        'total_amount' => $total,
        'trs_type' => 0,
        'paid_amount' => $total,
        'remaining_amount' => 0,
    ]);
}

it('shows today and month sales totals with transaction counts', function () {
    $today = now()->toDateString();

    buatPenjualan('PJ-000001', $today, 0, 10000, 1000, 600, 10);
    buatPenjualan('PJ-000002', $today, 1, 20000, 2000, 1200, 10);

    Livewire::test(SalesStatsOverview::class)
        ->assertOk()
        ->assertSee('Penjualan Hari Ini')
        ->assertSee('2 transaksi')
        ->assertSee('Penjualan Bulan Ini');
});

it('breaks down cash vs credit for the month', function () {
    $today = now()->toDateString();

    buatPenjualan('PJ-000001', $today, 0, 30000, 3000, 1500, 10);
    buatPenjualan('PJ-000002', $today, 1, 70000, 7000, 4000, 10);

    Livewire::test(SalesStatsOverview::class)
        ->assertOk()
        ->assertSee('Tunai vs Kredit')
        ->assertSee('Kredit: Rp 70.000');
});

it('computes gross profit as (unit_price - hpp_at_transaction) * qty', function () {
    $today = now()->toDateString();

    buatPenjualan('PJ-000001', $today, 0, 10000, 1000, 600, 10);

    Livewire::test(SalesStatsOverview::class)
        ->assertOk()
        ->assertSee('Laba Kotor Bulan Ini')
        ->assertSee('Rp 4.000');
});

it('renders a 30 day sales trend line chart', function () {
    buatPenjualan('PJ-000001', now()->toDateString(), 0, 10000, 1000, 600, 10);

    Livewire::test(SalesTrendChart::class)
        ->assertOk()
        ->assertSee('Tren Penjualan & Pembelian 30 Hari Terakhir')
        ->assertSee('Penjualan')
        ->assertSee('Pembelian');
});

it('shows total purchases for the current month', function () {
    $today = now()->toDateString();
    $lastMonth = now()->subMonth()->toDateString();

    buatPembelian('PB-000001', $today, 50000);
    buatPembelian('PB-000002', $today, 30000);
    buatPembelian('PB-000003', $lastMonth, 90000);

    Livewire::test(SalesStatsOverview::class)
        ->assertOk()
        ->assertSee('Total Pembelian Bulan Ini')
        ->assertSee('Rp 80.000')
        ->assertSee('2 transaksi');
});

it('shows total items and total quantity stats', function () {
    TbStock::factory()->create(['stock' => 10]);
    TbStock::factory()->create(['stock' => 4]);

    Livewire::test(InventoryStatsOverview::class)
        ->assertOk()
        ->assertSee('Total Item')
        ->assertSee('14 total qty')
        ->assertSee('Nilai Stok');
});

it('shows stock value as sum of stock times harga_pokok', function () {
    TbStock::factory()->create(['stock' => 10, 'harga_beli' => 1000]);
    TbStock::factory()->create(['stock' => 4, 'harga_beli' => 500]);

    Livewire::test(InventoryStatsOverview::class)
        ->assertOk()
        ->assertSee('Rp 12.000');
});

it('lists low and out of stock items in the table', function () {
    TbStock::factory()->create(['descr' => 'Barang Habis', 'stock' => 0]);
    TbStock::factory()->create(['descr' => 'Barang Menipis', 'stock' => 4]);
    TbStock::factory()->create(['descr' => 'Barang Aman', 'stock' => 20]);

    Livewire::test(LowStockTable::class)
        ->assertOk()
        ->assertSee('Barang Habis')
        ->assertSee('Barang Menipis')
        ->assertDontSee('Barang Aman')
        ->assertSee('Habis');
});

it('renders stock value bar chart grouped by category', function () {
    $cateA = TbCate::create(['descr' => 'Kategori A']);
    $cateB = TbCate::create(['descr' => 'Kategori B']);

    TbStock::factory()->create(['tb_cate_id' => $cateA->id, 'stock' => 10, 'harga_beli' => 1000]);
    TbStock::factory()->create(['tb_cate_id' => $cateB->id, 'stock' => 4, 'harga_beli' => 500]);
    TbStock::factory()->create(['tb_cate_id' => null, 'stock' => 2, 'harga_beli' => 100]);

    Livewire::test(StockValueByCategoryChart::class)
        ->assertOk()
        ->assertSee('Nilai Stok per Kategori')
        ->assertSee('Kategori A')
        ->assertSee('Kategori B')
        ->assertSee('Tanpa Kategori');
});
