<?php

namespace App\Filament\Resources\CustomerPayments\Pages;

use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\TrHeader;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ListCustomerPayments extends ListRecords
{
    protected static string $resource = CustomerPaymentResource::class;

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

        $customer = Customer::findOrFail($data['customer_id']);

        $net = $customer->netReceivable();

        if ($net <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Customer tidak memiliki sisa tagihan (sudah lunas atau tertutup retur penjualan).',
            ]);
        }

        if ($amount > $net) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah bayar melebihi sisa tagihan bersih (Rp '
                    .number_format($net, 2, '.', ',').').',
            ]);
        }

        if ($invoiceId !== null) {
            $header = TrHeader::findOrFail($invoiceId);

            if ((int) $header->customer_id !== (int) $customer->id) {
                throw ValidationException::withMessages([
                    'tr_header_id' => 'Invoice bukan milik customer yang dipilih.',
                ]);
            }

            if ($amount > (float) $header->remaining_amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah bayar melebihi sisa tagihan invoice (Rp '
                        .number_format((float) $header->remaining_amount, 2, '.', ',').').',
                ]);
            }
        }

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'tr_header_id' => $invoiceId,
            'payment_date' => $data['payment_date'],
            'amount' => $amount,
        ]);

        $customer->applyPayment($amount, $invoiceId);

        return $payment;
    }
}
