<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = ['descr', 'alamat', 'phone'];

    public function headers(): HasMany
    {
        return $this->hasMany(TrHeader::class);
    }

    /**
     * Total sisa hutang dari pembelian kredit yang belum dibayar.
     */
    public function payableBalance(): float
    {
        return (float) $this->headers()
            ->where('trr_type', 'PURCHASE')
            ->where('trs_type', 1)
            ->sum('remaining_amount');
    }

    /**
     * Total retur pembelian kredit yang mengurangi hutang ke supplier.
     */
    public function returnCredit(): float
    {
        return (float) $this->headers()
            ->where('trr_type', 'PURCHASE_RET')
            ->where('trs_type', 1)
            ->sum('remaining_amount');
    }

    /**
     * Sisa hutang bersih setelah memperhitungkan retur pembelian.
     */
    public function netPayable(): float
    {
        return round($this->payableBalance() - $this->returnCredit(), 2);
    }

    /**
     * Menerapkan pembayaran ke hutang supplier secara proporsional
     * (pembelian kredit dan retur pembelian kredit). Bila $invoiceId
     * diberikan, bagian pembelian didahulukan ke header tersebut.
     */
    public function applyPayment(float $amount, ?int $invoiceId = null): void
    {
        $this->allocate($amount, $invoiceId, subtract: true);
    }

    /**
     * Membatalkan pembayaran dengan mengembalikan sisa hutang
     * (kebalikan dari applyPayment).
     */
    public function reversePayment(float $amount, ?int $invoiceId = null): void
    {
        $this->allocate($amount, $invoiceId, subtract: false);
    }

    /**
     * Mengalokasikan pembayaran secara proporsional: hutang bersih
     * berkurang sebesar $amount, sedangkan sisa retur pembelian kredit
     * ikut dikonsumsi sebanding dengan porsinya terhadap posisi supplier.
     * Bagian pembelian dialokasikan FIFO (bila $invoiceId diberikan, header
     * tersebut didahulukan dan kelebihan mengalir ke invoice lain), bagian
     * retur dialokasikan FIFO ke transaksi PURCHASE_RET.
     */
    private function allocate(float $amount, ?int $invoiceId, bool $subtract): void
    {
        $remaining = round($amount, 2);

        if ($remaining <= 0) {
            return;
        }

        $purchaseHeaders = $this->headers()
            ->where('trr_type', 'PURCHASE')
            ->where('trs_type', 1)
            ->orderBy('trs_date')
            ->orderBy('trs_number')
            ->get();

        $returnHeaders = $this->headers()
            ->where('trr_type', 'PURCHASE_RET')
            ->where('trs_type', 1)
            ->orderBy('trs_date')
            ->orderBy('trs_number')
            ->get();

        if ($invoiceId !== null) {
            $purchaseHeaders = $purchaseHeaders->sortBy(
                fn (TrHeader $header): int => $header->getKey() === (int) $invoiceId ? 0 : 1,
            );
        }

        $totalPurchase = (float) $purchaseHeaders->sum(fn (TrHeader $header): float => (float) $header->remaining_amount);
        $totalReturn = (float) $returnHeaders->sum(fn (TrHeader $header): float => (float) $header->remaining_amount);

        $net = round($totalPurchase - $totalReturn, 2);

        if ($net <= 0) {
            return;
        }

        // Bagian yang mengurangi pembelian lebih besar dari kas keluar karena
        // retur kredit ikut menutup hutang secara proporsional.
        $purchasePart = round($remaining * ($totalPurchase / $net), 2);
        $returnPart = round($purchasePart - $remaining, 2);

        if ($purchasePart > 0) {
            $this->distribute($purchaseHeaders, $purchasePart, $subtract);
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
