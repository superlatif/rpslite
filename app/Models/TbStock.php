<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TbStock extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'descr', 'satuan', 'harga_beli',
        'harga_jual', 'harga_pokok', 'tb_cate_id', 'gambar'];

    protected $casts = ['harga_beli' => 'decimal:2',
        'harga_jual' => 'decimal:2',
        'harga_pokok' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (TbStock $stock): void {
            if ($stock->hasNoPurchaseTransactions()) {
                $stock->harga_pokok = $stock->harga_beli;
            }
        });
    }

    public function hasNoPurchaseTransactions(): bool
    {
        return ! $this->purchaseDetails()->exists();
    }

    public function recalculateHpp(): void
    {
        $aggregate = $this->purchaseDetails()
            ->selectRaw('SUM(qty) as total_qty, SUM(subtotal) as total_cost')
            ->first();

        $hpp = ($aggregate && (float) $aggregate->total_qty > 0)
            ? (float) $aggregate->total_cost / (float) $aggregate->total_qty
            : 0;

        TbStock::query()->whereKey($this->id)->update(['harga_pokok' => $hpp]);
    }

    public function purchaseDetails(): HasMany
    {
        return $this->hasMany(TrDetail::class, 'stock_id')
            ->whereHas('header', fn ($query) => $query->where('trr_type', 'PURCHASE'));
    }

    public function cate(): BelongsTo
    {
        return $this->belongsTo(TbCate::class, 'tb_cate_id');
    }
}
