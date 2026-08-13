<?php

namespace Database\Factories;

use App\Models\TbStock;
use App\Models\TrDetail;
use App\Models\TrHeader;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrDetail>
 */
class TrDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tr_header_id' => TrHeader::factory(),
            'stock_id' => TbStock::factory(),
            'qty' => fake()->randomFloat(2, 1, 100),
            'unit_price' => fake()->randomFloat(2, 1000, 100000),
            'hpp_at_transaction' => 0,
            'subtotal' => 0,
        ];
    }
}
