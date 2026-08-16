<?php

namespace App\Filament\Resources\SupplierPayments\Pages;

use App\Filament\Resources\SupplierPayments\SupplierPaymentResource;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TrHeader;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListSupplierPayments extends ListRecords
{
    protected static string $resource = SupplierPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Angsuran')
                ->modalSubmitActionLabel('Simpan')
                ->modalCancelActionLabel('Batal')
                ->databaseTransaction()
                ->using(fn (array $data): Model => $this->createPayment($data)),
        ];
    }

    protected function createPayment(array $data): Model
    {
        $amount = round((float) $data['amount'], 2);
        $invoiceId = isset($data['tr_header_id']) ? (int) $data['tr_header_id'] : null;

        $supplier = Supplier::findOrFail($data['supplier_id']);

        $net = $supplier->netPayable();

        if ($net <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Supplier tidak memiliki sisa hutang (sudah lunas atau tertutup retur pembelian).',
            ]);
        }

        if ($amount > $net) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah bayar melebihi sisa hutang bersih (Rp '
                    .number_format($net, 2, '.', ',').').',
            ]);
        }

        if ($invoiceId !== null) {
            $header = TrHeader::findOrFail($invoiceId);

            if ((int) $header->supplier_id !== (int) $supplier->id) {
                throw ValidationException::withMessages([
                    'tr_header_id' => 'Invoice bukan milik supplier yang dipilih.',
                ]);
            }

            if ($amount > (float) $header->remaining_amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah bayar melebihi sisa hutang invoice (Rp '
                        .number_format((float) $header->remaining_amount, 2, '.', ',').').',
                ]);
            }
        }

        $payment = SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'tr_header_id' => $invoiceId,
            'payment_date' => $data['payment_date'],
            'amount' => $amount,
        ]);

        $supplier->applyPayment($amount, $invoiceId);

        return $payment;
    }
}
