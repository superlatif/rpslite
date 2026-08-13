<?php

namespace App\Filament\Resources\CustomerPayments\Pages;

use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
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
        $header = isset($data['tr_header_id'])
            ? TrHeader::find($data['tr_header_id'])
            : null;

        if ($header && (float) $data['amount'] > (float) $header->remaining_amount) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah bayar melebihi sisa tagihan ('
                    .number_format((float) $header->remaining_amount, 2, '.', ',').').',
            ]);
        }

        $payment = CustomerPayment::create([
            'customer_id' => $data['customer_id'],
            'tr_header_id' => $data['tr_header_id'] ?? null,
            'payment_date' => $data['payment_date'],
            'amount' => $data['amount'],
        ]);

        if ($header) {
            $header->increment('paid_amount', (float) $data['amount']);
            $header->decrement('remaining_amount', (float) $data['amount']);
        }

        return $payment;
    }
}
