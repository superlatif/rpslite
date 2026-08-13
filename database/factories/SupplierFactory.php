<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'descr' => fake()->unique()->company(),
            'alamat' => fake()->address(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
