<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MobileDemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'MobileTest123!';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shopAlpha = Shop::query()->updateOrCreate(
            ['name' => 'Mobile Demo Alpha'],
            [
                'owner_name' => 'Owner Alpha',
                'phone' => '+992900000001',
                'email' => 'alpha.shop@ck-accounting.test',
                'address' => 'Dushanbe, Demo street 1',
                'status' => 'active',
            ]
        );

        $shopBeta = Shop::query()->updateOrCreate(
            ['name' => 'Mobile Demo Beta'],
            [
                'owner_name' => 'Owner Beta',
                'phone' => '+992900000002',
                'email' => 'beta.shop@ck-accounting.test',
                'address' => 'Khujand, Demo street 2',
                'status' => 'active',
            ]
        );

        $this->upsertUser('owner.alpha@ck-accounting.test', 'Owner Alpha', UserRole::Owner, $shopAlpha->id);
        $this->upsertUser('seller.alpha.1@ck-accounting.test', 'Seller Alpha 1', UserRole::Seller, $shopAlpha->id);
        $this->upsertUser('seller.alpha.2@ck-accounting.test', 'Seller Alpha 2', UserRole::Seller, $shopAlpha->id);

        $this->upsertUser('owner.beta@ck-accounting.test', 'Owner Beta', UserRole::Owner, $shopBeta->id);
        $this->upsertUser('seller.beta.1@ck-accounting.test', 'Seller Beta 1', UserRole::Seller, $shopBeta->id);
        $this->upsertUser('seller.beta.2@ck-accounting.test', 'Seller Beta 2', UserRole::Seller, $shopBeta->id);
    }

    private function upsertUser(string $email, string $name, UserRole $role, int $shopId): void
    {
        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'shop_id' => $shopId,
                'role' => $role->value,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
            ]
        );
    }
}
