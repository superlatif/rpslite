<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TbCate extends Model
{
    use HasFactory;

    protected $fillable = ['descr'];

    public function stock(): HasMany
    {
        return $this->hasMany(TbStock::class, 'tb_cate_id');
    }
}
