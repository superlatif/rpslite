<?php

namespace App\Filament\Resources\TbStocks\Pages;

use App\Filament\Resources\TbStocks\TbStockResource;
use App\Models\TbStock;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTbStocks extends ListRecords
{
    protected static string $resource = TbStockResource::class;

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
                ->modalCancelActionLabel('Batal')
                ->mutateDataUsing(function (array $data): array {

                    $lastCode = TbStock::query()
                        ->whereRaw('LENGTH(code) = 8')
                        ->orderByDesc('code')
                        ->value('code');

                    $next = $lastCode
                        ? ((int) $lastCode + 1)
                        : 1;

                    $data['code'] = str_pad(
                        (string) $next,
                        8,
                        '0',
                        STR_PAD_LEFT
                    );

                    return $data;
                }),
        ];
    }
}
