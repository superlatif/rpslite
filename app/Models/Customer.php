<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['descr', 'alamat', 'phone'];

    public function headers(): HasMany
    {
        return $this->hasMany(TrHeader::class);
    }

    /**
     * Total sisa tagihan dari penjualan kredit yang belum dibayar.
     */
    public function receivableBalance(): float
    {
        return (float) $this->headers()
            ->where('trr_type', 'SALE')
            ->where('trs_type', 1)
            ->sum('remaining_amount');
    }

    /**
     * Total retur penjualan kredit yang mengurangi piutang customer.
     */
    public function returnCredit(): float
    {
        return (float) $this->headers()
            ->where('trr_type', 'SALE_RET')
            ->where('trs_type', 1)
            ->sum('remaining_amount');
    }

    /**
     * Sisa piutang bersih setelah memperhitungkan retur penjualan.
     */
    public function netReceivable(): float
    {
        return round($this->receivableBalance() - $this->returnCredit(), 2);
    }

    /**
     * Menerapkan pembayaran ke header penjualan kredit secara FIFO
     * (transaksi paling lama lebih dulu). Bila $invoiceId diberikan,
     * pembayaran hanya diterapkan ke header tersebut.
     */
    public function applyPayment(float $amount, ?int $invoiceId = null): void
    {
        $this->allocate($amount, $invoiceId, subtract: true);
    }

    /**
     * Membatalkan pembayaran dengan mengembalikan sisa tagihan
     * ke header penjualan kredit (kebalikan dari applyPayment).
     */
    public function reversePayment(float $amount, ?int $invoiceId = null): void
    {
        $this->allocate($amount, $invoiceId, subtract: false);
    }

    private function allocate(float $amount, ?int $invoiceId, bool $subtract): void
    {
        $remaining = round($amount, 2);

        $query = $this->headers()
            ->where('trr_type', 'SALE')
            ->where('trs_type', 1)
            ->orderBy('trs_date')
            ->orderBy('trs_number');

        if ($invoiceId !== null) {
            $query->whereKey($invoiceId);
        }

        foreach ($query->get() as $header) {
            if ($remaining <= 0) {
                break;
            }

            $take = $subtract
                ? min((float) $header->remaining_amount, $remaining)
                : $remaining;

            if ($take > 0) {
                if ($subtract) {
                    $header->decrement('remaining_amount', $take);
                    $header->increment('paid_amount', $take);
                } else {
                    $header->increment('remaining_amount', $take);
                    $header->decrement('paid_amount', $take);
                }

                $remaining = round($remaining - $take, 2);
            }
        }
    }
}
