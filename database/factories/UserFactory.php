<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'shop_id' => null,
            'role' => UserRole::Seller->value,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Test ergonomics: callers historically create owners with
     * `User::factory()->create(['shop_id' => $shop->id, 'role' => 'owner'])`
     * and expect the user to "own" that shop. After the multi-shop owner
     * model (shops.owner_id), that's no longer automatic — owners have
     * shop_id=null and ownership lives on the shop side.
     *
     * configure() runs after `create()`. If a freshly-created user has
     * role=owner AND shop_id set, we treat shop_id as a shorthand for
     * "assign me to this shop" — set `shops.owner_id` and clear the
     * user's shop_id. Tests using the legacy shape keep working without
     * changes; new tests can either continue using the shorthand or
     * explicitly set `Shop::owner_id` themselves.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $isOwnerRole = ($user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role)) === UserRole::Owner;
            if ($isOwnerRole && $user->shop_id !== null) {
                Shop::query()->whereKey($user->shop_id)->update(['owner_id' => $user->id]);
                $user->forceFill(['shop_id' => null])->save();
            }
        });
    }
}
