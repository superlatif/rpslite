<?php

use App\Filament\Resources\TrPurchases\Pages\ListTrPurchases;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('opens the stock create option modal inside the purchase create form', function () {
    $testable = Livewire::test(ListTrPurchases::class)
        ->mountAction('create');

    $repeater = $testable->instance()->getSchema('mountedActionSchema0')->getComponent('details');

    $itemKey = array_key_first($repeater->getState());

    $testable
        ->mountAction(TestAction::make('createOption')->schemaComponent("details.{$itemKey}.stock_id", 'mountedActionSchema0'))
        ->assertOk()
        ->assertSet('mountedActions.1.name', 'createOption');
});
