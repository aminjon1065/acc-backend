<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
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
            'name' => fake()->words(2, true),
            'quantity' => fake()->randomFloat(3, 1, 20),
            'price' => fake()->randomFloat(2, 1, 200),
            'total' => fake()->randomFloat(2, 1, 2000),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
