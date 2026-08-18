<?php

namespace Database\Factories;

use App\Models\TbCate;
use App\Models\TbStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TbStock>
 */
class TbStockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => str_pad((string) fake()->unique()->numberBetween(1, 99999999), 8, '0', STR_PAD_LEFT),
            'descr' => fake()->unique()->word(),
            'satuan' => 'PCS',
            'harga_beli' => fake()->randomFloat(2, 1000, 100000),
            'harga_jual' => fake()->randomFloat(2, 1000, 100000),
            'harga_pokok' => 0,
            'stock' => 0,
            'is_jasa' => false,
            'tb_cate_id' => TbCate::factory(),
        ];
    }

    public function jasa(): static
    {
        return $this->state(fn (): array => [
            'is_jasa' => true,
            'stock' => 0,
        ]);
    }
}
