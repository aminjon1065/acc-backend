<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@ck-accounting.test'],
            [
                'name' => 'Super Admin',
                'shop_id' => null,
                'role' => UserRole::SuperAdmin->value,
                'password' => Hash::make('Momajon115877!'),
                'email_verified_at' => now(),
            ]
        );
    }
}
