<?php

namespace Database\Factories;

use App\Models\TbCate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TbCate>
 */
class TbCateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'descr' => fake()->unique()->word(),
        ];
    }
}
