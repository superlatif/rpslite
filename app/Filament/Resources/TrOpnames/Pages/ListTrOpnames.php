<?php

namespace App\Filament\Resources\TrOpnames\Pages;

use App\Filament\Resources\TrOpnames\TrOpnameResource;
use App\Models\TbStock;
use App\Models\TrHeader;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListTrOpnames extends ListRecords
{
    protected static string $resource = TrOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Opname')
                ->modalSubmitActionLabel('Simpan')
                ->modalCancelActionLabel('Batal')
                ->databaseTransaction()
                ->using(fn (array $data): Model => $this->createOpname($data)),
        ];
    }

    protected function createOpname(array $data): Model
    {
        return DB::transaction(function () use ($data) {

            // Siapkan detail dan hitung selisih dari server
            $details = $this->prepareDetails(
                $data['details'] ?? []
            );

            // =========================
            // HEADER
            // =========================
            $header = TrHeader::create([
                'trs_number' => $this->generateNumber('OP'),
                'trs_date' => $data['trs_date'],
                'trr_type' => 'OPNAME',
                'total_amount' => 0,
                'trs_type' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
            ]);

            // =========================
            // DETAIL
            // =========================
            $header->details()->createMany($details);

            // =========================
            // INVENTORY
            // =========================
            foreach ($details as $row) {

                TbStock::query()
                    ->lockForUpdate()
                    ->whereKey($row['stock_id'])
                    ->update(['stock' => $row['stok_fisik']]);
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

                $stock = TbStock::findOrFail($row['stock_id']);

                if ($stock->is_jasa) {
                    throw ValidationException::withMessages([
                        'details' => 'Jasa tidak memiliki stok sehingga tidak dapat diopname.',
                    ]);
                }

                $sistem = (float) $stock->stock;
                $fisik = (float) ($row['stok_fisik'] ?? 0);

                if ($fisik < 0) {
                    throw ValidationException::withMessages([
                        'details' => 'Stok fisik tidak boleh negatif.',
                    ]);
                }

                return [
                    'stock_id' => $row['stock_id'],
                    'stok_fisik' => $fisik,
                    'qty' => round($fisik - $sistem, 2),
                    'unit_price' => 0,
                    'hpp_at_transaction' => (float) $stock->harga_pokok,
                    'subtotal' => 0,
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
