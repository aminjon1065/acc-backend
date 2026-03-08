<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseItem>
 */
class PurchaseItemFactory extends Factory
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
            'purchase_id' => Purchase::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(3, 1, 50),
            'price' => fake()->randomFloat(2, 1, 100),
            'total' => fake()->randomFloat(2, 1, 5000),
        ];
    }
}
