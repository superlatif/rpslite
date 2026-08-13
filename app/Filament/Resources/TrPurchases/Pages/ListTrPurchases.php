<?php

namespace App\Filament\Resources\TrPurchases\Pages;

use App\Filament\Resources\TrPurchases\TrPurchaseResource;
use App\Models\TbStock;
use App\Models\TrHeader;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListTrPurchases extends ListRecords
{
    protected static string $resource = TrPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Pembelian')
                ->modalSubmitActionLabel('Simpan')
                ->modalCancelActionLabel('Batal')
                ->databaseTransaction()
                ->using(fn (array $data): Model => $this->createPurchase($data)),
        ];
    }

    protected function createPurchase(array $data): Model
    {
        $details = $this->prepareDetails($data['details'] ?? []);
        $total = array_sum(array_column($details, 'subtotal'));
        $isKredit = (int) ($data['trs_type'] ?? 0) === 1;

        $header = TrHeader::create([
            'trs_number' => $this->generateNumber('PB'),
            'trs_date' => $data['trs_date'],
            'trr_type' => 'PURCHASE',
            'supplier_id' => $data['supplier_id'],
            'total_amount' => $total,
            'trs_type' => $isKredit ? 1 : 0,
            'paid_amount' => $isKredit ? 0 : $total,
            'remaining_amount' => $isKredit ? $total : 0,
        ]);

        $header->details()->createMany($details);

        foreach ($details as $row) {
            TbStock::query()->whereKey($row['stock_id'])->increment('stock', (float) $row['qty']);
            TbStock::find($row['stock_id'])?->recalculateHpp();
        }

        return $header;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function prepareDetails(array $rows): array
    {
        return array_map(function (array $row): array {
            $stock = TbStock::find($row['stock_id']);

            return [
                'stock_id' => $row['stock_id'],
                'qty' => $row['qty'],
                'unit_price' => $row['unit_price'],
                'hpp_at_transaction' => (float) ($stock?->harga_pokok ?? 0),
                'subtotal' => (float) $row['qty'] * (float) $row['unit_price'],
            ];
        }, $rows);
    }

    protected function generateNumber(string $prefix): string
    {
        $last = TrHeader::query()
            ->where('trs_number', 'LIKE', $prefix.'-%')
            ->orderByDesc('trs_number')
            ->value('trs_number');

        $next = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
