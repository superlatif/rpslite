<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrHeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'trs_number',
        'trs_date',
        'trr_type',
        'customer_id',
        'supplier_id',
        'source_sale_id',
        'source_purchase_id',
        'total_amount',
        'trs_type',
        'paid_amount',
        'remaining_amount',
    ];

    protected $casts = [
        'trs_date' => 'date',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sourceSale(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_sale_id');
    }

    public function sourcePurchase(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_purchase_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TrDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    protected function debet(): Attribute
    {
        return Attribute::make(
            get: fn () => str_starts_with($this->trs_number, 'PB') ? $this->total_amount : 0,
        );
    }

    // Accessor untuk Kredit
    protected function kredit(): Attribute
    {
        return Attribute::make(
            get: fn () => str_starts_with($this->trs_number, 'RPB') ? $this->total_amount : 0,
        );
    }

    // Accessor opsional untuk Jenis Bayar
    protected function jenisBayar(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->trs_type === 0 ? 'Tunai' : 'Kredit',
        );
    }
}
