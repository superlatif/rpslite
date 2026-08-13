<?php

namespace Database\Factories;

use App\Models\TrHeader;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrHeader>
 */
class TrHeaderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trs_number' => fake()->unique()->numerify('PB-######'),
            'trs_date' => fake()->date(),
            'trr_type' => 'PURCHASE',
            'total_amount' => 0,
            'trs_type' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 0,
        ];
    }
}
