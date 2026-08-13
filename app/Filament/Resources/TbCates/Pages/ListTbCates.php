<?php

namespace App\Filament\Resources\TbCates\Pages;

use App\Filament\Resources\TbCates\TbCateResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTbCates extends ListRecords
{
    protected static string $resource = TbCateResource::class;

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
