<?php

namespace App\Filament\Resources\TrSales\Pages;

use App\Filament\Resources\TrSales\TrSaleResource;
use App\Models\TbStock;
use App\Models\TrHeader;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListTrSales extends ListRecords
{
    protected static string $resource = TrSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Penjualan')
                ->modalSubmitActionLabel('Simpan')
                ->modalCancelActionLabel('Batal')
                ->databaseTransaction()
                ->using(fn (array $data): Model => $this->createSale($data)),
        ];
    }

    protected function createSale(array $data): Model
    {
        $details = $this->prepareDetails($data['details'] ?? []);
        $errors = [];

        foreach ($details as $index => $row) {
            $stock = TbStock::find($row['stock_id']);

            if ($stock && (float) $stock->stock < (float) $row['qty']) {
                $errors["details.{$index}.stock_id"] = "Stok '{$stock->descr}' tersedia {$stock->stock}.";
            }
        }

        if (count($errors) > 0) {
            throw ValidationException::withMessages($errors);
        }

        $total = array_sum(array_column($details, 'subtotal'));
        $isKredit = (int) ($data['trs_type'] ?? 0) === 1;

        $header = TrHeader::create([
            'trs_number' => $this->generateNumber('PJ'),
            'trs_date' => $data['trs_date'],
            'trr_type' => 'SALE',
            'customer_id' => $data['customer_id'] ?? null,
            'total_amount' => $total,
            'trs_type' => $isKredit ? 1 : 0,
            'paid_amount' => $isKredit ? 0 : $total,
            'remaining_amount' => $isKredit ? $total : 0,
        ]);

        $header->details()->createMany($details);

        foreach ($details as $row) {
            TbStock::query()->whereKey($row['stock_id'])->decrement('stock', (float) $row['qty']);
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
