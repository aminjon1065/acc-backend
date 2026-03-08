<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleItem>
 */
class SaleItemFactory extends Factory
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
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(3, 1, 10),
            'price' => fake()->randomFloat(2, 1, 100),
            'cost_price' => fake()->randomFloat(2, 1, 100),
            'total' => fake()->randomFloat(2, 1, 1000),
        ];
    }
}
