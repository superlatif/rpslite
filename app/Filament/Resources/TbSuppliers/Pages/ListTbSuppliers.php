<?php

namespace App\Filament\Resources\TbSuppliers\Pages;

use App\Filament\Resources\TbSuppliers\TbSupplierResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTbSuppliers extends ListRecords
{
    protected static string $resource = TbSupplierResource::class;

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
