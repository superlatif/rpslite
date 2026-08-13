<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'tr_header_id',
        'stock_id',
        'qty',
        'unit_price',
        'hpp_at_transaction',
        'subtotal',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'hpp_at_transaction' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(TrHeader::class, 'tr_header_id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(TbStock::class);
    }
}
