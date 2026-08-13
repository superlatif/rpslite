<?php

namespace App\Filament\Resources\TbCustomers\Pages;

use App\Filament\Resources\TbCustomers\TbCustomerResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTbCustomers extends ListRecords
{
    protected static string $resource = TbCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah data')
                ->modalSubmitActionLabel('Simpan')
                ->createAnother(true)
                ->createAnotherAction(
                    fn (Action $action) => $action->label('Simpan & Tambah Lagi')
                )
                ->modalCancelActionLabel('Batal'),
        ];
    }
}
