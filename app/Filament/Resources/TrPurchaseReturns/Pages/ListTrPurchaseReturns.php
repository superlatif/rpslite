<?php

namespace App\Filament\Resources\TrPurchaseReturns\Pages;

use App\Filament\Resources\TrPurchaseReturns\TrPurchaseReturnResource;
use App\Models\TbStock;
use App\Models\TrHeader;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListTrPurchaseReturns extends ListRecords
{
    protected static string $resource = TrPurchaseReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Retur Pembelian')
                ->modalSubmitActionLabel('Simpan')
                ->modalCancelActionLabel('Batal')
                ->databaseTransaction()
                ->using(fn (array $data): Model => $this->createPurchaseReturn($data)),
        ];
    }

    protected function createPurchaseReturn(array $data): Model
    {
        return DB::transaction(function () use ($data) {

            // Siapkan detail dan hitung total dari server
            $details = $this->prepareDetails(
                $data['details'] ?? []
            );

            $total = array_sum(
                array_column($details, 'subtotal')
            );

            // Faktur beli sumber retur: hanya PURCHASE kredit terbuka milik supplier terpilih
            $source = TrHeader::query()
                ->lockForUpdate()
                ->find($data['source_purchase_id'] ?? null);

            if (! $source
                || $source->trr_type !== 'PURCHASE'
                || (int) $source->trs_type !== 1
                || (float) $source->remaining_amount <= 0
            ) {
                throw ValidationException::withMessages([
                    'source_purchase_id' => 'Faktur beli tidak valid atau sudah tidak memiliki sisa tagihan.',
                ]);
            }

            if ((int) $source->supplier_id !== (int) ($data['supplier_id'] ?? 0)) {
                throw ValidationException::withMessages([
                    'source_purchase_id' => 'Faktur beli bukan milik supplier yang dipilih.',
                ]);
            }

            if ($total > (float) $source->remaining_amount) {
                throw ValidationException::withMessages([
                    'source_purchase_id' => 'Nilai retur melebihi sisa faktur beli (sisa: '.number_format((float) $source->remaining_amount, 0, ',', '.').').',
                ]);
            }

            $isKredit = (int) ($data['trs_type'] ?? 0) === 1;

            // =========================
            // HEADER
            // =========================
            // Retur selalu diisi paid_amount (walau kredit) karena otomatis
            // menambah paid_amount pada faktur beli sumber. Retur tunai
            // dianggap supplier mengembalikan uang tunai kepada toko.
            $header = TrHeader::create([
                'trs_number' => $this->generateNumber('RPB'),
                'trs_date' => $data['trs_date'],
                'trr_type' => 'PURCHASE_RET',
                'supplier_id' => $source->supplier_id,
                'source_purchase_id' => $source->id,
                'total_amount' => $total,
                'trs_type' => $isKredit ? 1 : 0,
                'paid_amount' => $total,
                'remaining_amount' => 0,
            ]);

            // =========================
            // DETAIL
            // =========================
            $header->details()->createMany($details);

            // =========================
            // FAKTUR BELI SUMBER
            // =========================
            $source->decrement('remaining_amount', $total);
            $source->increment('paid_amount', $total);

            // =========================
            // INVENTORY
            // =========================
            foreach ($details as $index => $row) {

                $stock = TbStock::query()
                    ->lockForUpdate()
                    ->findOrFail($row['stock_id']);

                if ($stock->is_jasa) {
                    continue;
                }

                if ((float) $stock->stock < (float) $row['qty']) {
                    throw ValidationException::withMessages([
                        "details.{$index}.stock_id" => "Stok '{$stock->descr}' hanya tersedia {$stock->stock}.",
                    ]);
                }

                $stock->decrement(
                    'stock',
                    (float) $row['qty']
                );
            }

            return $header;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     * @return array<int, array<string, mixed>>
     */
    protected function prepareDetails(array $details): array
    {
        $stockIds = collect($details)
            ->pluck('stock_id')
            ->filter();

        if ($stockIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Barang yang sama tidak boleh dimasukkan lebih dari satu kali.',
            ]);
        }

        return collect($details)
            ->map(function (array $row) {

                $qty = (float) ($row['qty'] ?? 0);
                $price = (float) ($row['unit_price'] ?? 0);

                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'details' => 'Qty harus lebih besar dari 0.',
                    ]);
                }

                return [
                    'stock_id' => $row['stock_id'],
                    'qty' => $qty,
                    'unit_price' => $price,
                    'subtotal' => round($qty * $price, 2),
                ];
            })
            ->values()
            ->all();
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
