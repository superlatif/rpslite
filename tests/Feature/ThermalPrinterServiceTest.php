<?php

use App\Models\Setting;
use App\Services\ThermalPrinterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;

uses(RefreshDatabase::class);

it('stores and reads settings permanently', function () {
    Setting::set('thermal.paper_size', '58');

    expect(Setting::get('thermal.paper_size'))->toBe('58')
        ->and(Setting::get('missing.key', 'fallback'))->toBe('fallback');
});

it('updates an existing setting instead of duplicating it', function () {
    Setting::set('thermal.paper_size', '58');
    Setting::set('thermal.paper_size', '80');

    expect(Setting::query()->where('key', 'thermal.paper_size')->count())->toBe(1)
        ->and(Setting::get('thermal.paper_size'))->toBe('80');
});

it('defaults to 80mm paper when no setting exists', function () {
    $content = printReceiptContent();

    expect($content)->toContain(str_repeat('-', 48));
});

it('uses 32 columns per line when 58mm paper is configured', function () {
    Setting::set('thermal.paper_size', '58');

    $content = printReceiptContent();

    expect($content)->toContain(str_repeat('-', 32))
        ->and($content)->not->toContain(str_repeat('-', 48));
});

it('uses 48 columns per line when 80mm paper is configured', function () {
    Setting::set('thermal.paper_size', '80');

    $content = printReceiptContent();

    expect($content)->toContain(str_repeat('-', 48));
});

it('reports false when the printer command fails', function () {
    $service = partialMockThermalPrinter(printRawResult: false);

    expect($service->printReceipt([
        'shop_name' => 'TOKO',
        'total' => 0,
        'items' => [],
    ]))->toBeFalse();
});

function printReceiptContent(): string
{
    $captured = '';

    $service = Mockery::mock(ThermalPrinterService::class, [])
        ->makePartial()
        ->shouldReceive('printRaw')
        ->andReturnUsing(function (string $content) use (&$captured): bool {
            $captured = $content;

            return true;
        })
        ->getMock();

    $service->printReceipt([
        'shop_name' => 'TOKO',
        'number' => 'PJ-000001',
        'date' => '17/08/2026',
        'payment_type' => 'TUNAI',
        'is_credit' => false,
        'remaining' => 0,
        'total' => 10000,
        'items' => [
            ['name' => 'Bollpoin', 'qty' => 1, 'unit_price' => 10000, 'subtotal' => 10000],
        ],
    ], new MemoryPrintConnector);

    return extractPrintableText($captured);
}

function partialMockThermalPrinter(bool $printRawResult = true): ThermalPrinterService
{
    return Mockery::mock(ThermalPrinterService::class, [])
        ->makePartial()
        ->shouldReceive('printRaw')
        ->andReturn($printRawResult)
        ->getMock();
}

function extractPrintableText(string $raw): string
{
    return preg_replace('/[\x00-\x1F]/', '', $raw) ?? '';
}
