<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
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
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'type' => fake()->randomElement(['product', 'service']),
            'discount' => fake()->randomFloat(2, 0, 50),
            'paid' => fake()->randomFloat(2, 0, 500),
            'debt' => fake()->randomFloat(2, 0, 500),
            'total' => fake()->randomFloat(2, 10, 1000),
            'payment_type' => fake()->randomElement(['cash', 'card', 'transfer']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
