<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
     * Menerapkan pembayaran ke piutang customer secara proporsional
     * (penjualan kredit dan retur penjualan kredit). Bila $invoiceId
     * diberikan, bagian penjualan didahulukan ke header tersebut.
     */
    public function applyPayment(float $amount, ?int $invoiceId = null): void
    {
        $this->allocate($amount, $invoiceId, subtract: true);
    }

    /**
     * Membatalkan pembayaran dengan mengembalikan sisa tagihan
     * (kebalikan dari applyPayment).
     */
    public function reversePayment(float $amount, ?int $invoiceId = null): void
    {
        $this->allocate($amount, $invoiceId, subtract: false);
    }

    /**
     * Mengalokasikan pembayaran secara proporsional: piutang bersih
     * berkurang sebesar $amount, sedangkan sisa retur penjualan kredit
     * ikut dikonsumsi sebanding dengan porsinya terhadap posisi customer.
     * Bagian penjualan dialokasikan FIFO (bila $invoiceId diberikan, header
     * tersebut didahulukan dan kelebihan mengalir ke invoice lain), bagian
     * retur dialokasikan FIFO ke transaksi SALE_RET.
     */
    private function allocate(float $amount, ?int $invoiceId, bool $subtract): void
    {
        $remaining = round($amount, 2);

        if ($remaining <= 0) {
            return;
        }

        $saleHeaders = $this->headers()
            ->where('trr_type', 'SALE')
            ->where('trs_type', 1)
            ->orderBy('trs_date')
            ->orderBy('trs_number')
            ->get();

        $returnHeaders = $this->headers()
            ->where('trr_type', 'SALE_RET')
            ->where('trs_type', 1)
            ->orderBy('trs_date')
            ->orderBy('trs_number')
            ->get();

        if ($invoiceId !== null) {
            $saleHeaders = $saleHeaders->sortBy(
                fn (TrHeader $header): int => $header->getKey() === (int) $invoiceId ? 0 : 1,
            );
        }

        $totalSale = (float) $saleHeaders->sum(fn (TrHeader $header): float => (float) $header->remaining_amount);
        $totalReturn = (float) $returnHeaders->sum(fn (TrHeader $header): float => (float) $header->remaining_amount);

        $net = round($totalSale - $totalReturn, 2);

        if ($net <= 0) {
            return;
        }

        // Bagian yang mengurangi penjualan lebih besar dari kas masuk karena
        // retur kredit ikut menutup tagihan secara proporsional.
        $salePart = round($remaining * ($totalSale / $net), 2);
        $returnPart = round($salePart - $remaining, 2);

        if ($salePart > 0) {
            $this->distribute($saleHeaders, $salePart, $subtract);
        }

        if ($returnPart > 0) {
            $this->distribute($returnHeaders, $returnPart, $subtract);
        }
    }

    /**
     * @param  Collection<int, TrHeader>  $headers
     */
    private function distribute(Collection $headers, float $amount, bool $subtract): void
    {
        $remaining = round($amount, 2);

        foreach ($headers as $header) {
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
