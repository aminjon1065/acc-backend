<?php

namespace Database\Factories;

use App\Models\Debt;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DebtTransaction>
 */
class DebtTransactionFactory extends Factory
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
            'debt_id' => Debt::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['give', 'take', 'repay']),
            'amount' => fake()->randomFloat(2, 1, 500),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
