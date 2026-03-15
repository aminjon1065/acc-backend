<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'created_by' => User::factory(),
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->bothify('PRD-####')),
            'unit' => fake()->randomElement(['piece', 'kg', 'liter', 'meter']),
            'cost_price' => fake()->randomFloat(2, 1, 300),
            'sale_price' => fake()->randomFloat(2, 1, 500),
            'stock_quantity' => fake()->randomFloat(3, 0, 1000),
            'low_stock_alert' => fake()->randomFloat(3, 0, 20),
            'image_path' => null,
        ];
    }
}
