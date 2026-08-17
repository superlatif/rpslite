<?php

use App\Filament\Pages\PengaturanPrinter;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('shows the current paper size in the form', function () {
    Setting::set('thermal.paper_size', '58');

    Livewire::test(PengaturanPrinter::class)
        ->assertFormSet(['paper_size' => '58']);
});

it('defaults the paper size to 80mm', function () {
    Livewire::test(PengaturanPrinter::class)
        ->assertFormSet(['paper_size' => '80']);
});

it('saves the selected paper size permanently', function () {
    Livewire::test(PengaturanPrinter::class)
        ->fillForm([
            'paper_size' => '58',
        ])
        ->call('save')
        ->assertNotified('Pengaturan tersimpan');

    expect(Setting::get('thermal.paper_size'))->toBe('58');
});

it('overwrites a previously saved paper size', function () {
    Setting::set('thermal.paper_size', '58');

    Livewire::test(PengaturanPrinter::class)
        ->fillForm([
            'paper_size' => '80',
        ])
        ->call('save');

    expect(Setting::get('thermal.paper_size'))->toBe('80')
        ->and(Setting::query()->where('key', 'thermal.paper_size')->count())->toBe(1);
});

it('requires a paper size to be selected', function () {
    Livewire::test(PengaturanPrinter::class)
        ->fillForm([
            'paper_size' => null,
        ])
        ->call('save')
        ->assertHasFormErrors([
            'paper_size' => 'required',
        ]);
});
