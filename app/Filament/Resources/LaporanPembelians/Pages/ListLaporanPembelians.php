<?php

namespace App\Filament\Resources\LaporanPembelians\Pages;

use App\Filament\Resources\LaporanPembelians\LaporanPembelianResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLaporanPembelians extends ListRecords
{
    protected static string $resource = LaporanPembelianResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         CreateAction::make(),
    //     ];
    // }
}
