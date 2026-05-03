<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'admin@ck.top';

    private const ADMIN_PASSWORD = 'Demo12345!';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'CK Top Admin',
                'shop_id' => null,
                'role' => UserRole::SuperAdmin->value,
                'password' => Hash::make(self::ADMIN_PASSWORD),
            ]
        );

        $admin->forceFill([
            'email_verified_at' => now(),
        ])->save();
    }
}
