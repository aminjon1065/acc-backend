<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\Debt;
use App\Models\DebtTransaction;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class MobileDemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'MobileTest123!';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedCurrencies();

            $alphaShop = $this->upsertShop([
                'name' => 'Mobile Demo Alpha',
                'owner_name' => 'Owner Alpha',
                'phone' => '+992900000001',
                'email' => 'alpha.shop@ck-accounting.test',
                'address' => 'Dushanbe, Rudaki avenue 11',
                'status' => 'active',
            ]);

            $betaShop = $this->upsertShop([
                'name' => 'Mobile Demo Beta',
                'owner_name' => 'Owner Beta',
                'phone' => '+992900000002',
                'email' => 'beta.shop@ck-accounting.test',
                'address' => 'Khujand, Somoni street 24',
                'status' => 'active',
            ]);

            $gammaShop = $this->upsertShop([
                'name' => 'Mobile Demo Gamma',
                'owner_name' => 'Owner Gamma',
                'phone' => '+992900000003',
                'email' => 'gamma.shop@ck-accounting.test',
                'address' => 'Bokhtar, Ismoili Somoni street 7',
                'status' => 'suspended',
            ]);

            $this->cleanupDemoData([$alphaShop, $betaShop, $gammaShop]);

            $alphaOwner = $this->createDemoUser('owner.alpha@ck-accounting.test', 'Owner Alpha', UserRole::Owner, $alphaShop->id);
            $alphaSellerOne = $this->createDemoUser('seller.alpha.1@ck-accounting.test', 'Seller Alpha 1', UserRole::Seller, $alphaShop->id);
            $alphaSellerTwo = $this->createDemoUser('seller.alpha.2@ck-accounting.test', 'Seller Alpha 2', UserRole::Seller, $alphaShop->id);

            $betaOwner = $this->createDemoUser('owner.beta@ck-accounting.test', 'Owner Beta', UserRole::Owner, $betaShop->id);
            $betaSellerOne = $this->createDemoUser('seller.beta.1@ck-accounting.test', 'Seller Beta 1', UserRole::Seller, $betaShop->id);
            $betaSellerTwo = $this->createDemoUser('seller.beta.2@ck-accounting.test', 'Seller Beta 2', UserRole::Seller, $betaShop->id);

            $gammaOwner = $this->createDemoUser('owner.gamma@ck-accounting.test', 'Owner Gamma', UserRole::Owner, $gammaShop->id);
            $gammaSeller = $this->createDemoUser('seller.gamma.1@ck-accounting.test', 'Seller Gamma 1', UserRole::Seller, $gammaShop->id);

            $this->createShopSetting($alphaShop->id, 'TJS', 2.50);
            $this->createShopSetting($betaShop->id, 'USD', 1.75);
            $this->createShopSetting($gammaShop->id, 'TJS', 0.00);

            $alphaProducts = $this->seedProducts($alphaShop, $alphaOwner, [
                [
                    'name' => 'Coca-Cola 1L',
                    'code' => 'ALPHA-COLA-1L',
                    'unit' => 'piece',
                    'cost_price' => 8.50,
                    'sale_price' => 11.00,
                    'stock_quantity' => 92,
                    'low_stock_alert' => 15,
                ],
                [
                    'name' => 'Sugar',
                    'code' => 'ALPHA-SUGAR-5KG',
                    'unit' => 'kg',
                    'cost_price' => 6.80,
                    'sale_price' => 8.90,
                    'stock_quantity' => 54,
                    'low_stock_alert' => 10,
                ],
                [
                    'name' => 'Sunflower Oil',
                    'code' => 'ALPHA-OIL-1L',
                    'unit' => 'liter',
                    'cost_price' => 18.00,
                    'sale_price' => 23.50,
                    'stock_quantity' => 37,
                    'low_stock_alert' => 8,
                ],
                [
                    'name' => 'Laundry Powder',
                    'code' => 'ALPHA-POWDER-3KG',
                    'unit' => 'piece',
                    'cost_price' => 28.00,
                    'sale_price' => 36.00,
                    'stock_quantity' => 11,
                    'low_stock_alert' => 12,
                ],
            ]);

            $betaProducts = $this->seedProducts($betaShop, $betaOwner, [
                [
                    'name' => 'Chocolate Bar',
                    'code' => 'BETA-CHOCO-90G',
                    'unit' => 'piece',
                    'cost_price' => 4.20,
                    'sale_price' => 6.50,
                    'stock_quantity' => 150,
                    'low_stock_alert' => 20,
                ],
                [
                    'name' => 'Mineral Water 1.5L',
                    'code' => 'BETA-WATER-15',
                    'unit' => 'piece',
                    'cost_price' => 3.40,
                    'sale_price' => 5.00,
                    'stock_quantity' => 120,
                    'low_stock_alert' => 18,
                ],
                [
                    'name' => 'Rice',
                    'code' => 'BETA-RICE-25KG',
                    'unit' => 'kg',
                    'cost_price' => 7.10,
                    'sale_price' => 9.60,
                    'stock_quantity' => 83,
                    'low_stock_alert' => 15,
                ],
                [
                    'name' => 'Battery AA',
                    'code' => 'BETA-BATTERY-AA',
                    'unit' => 'piece',
                    'cost_price' => 2.10,
                    'sale_price' => 3.40,
                    'stock_quantity' => 24,
                    'low_stock_alert' => 10,
                ],
            ]);

            $gammaProducts = $this->seedProducts($gammaShop, $gammaOwner, [
                [
                    'name' => 'Notebook',
                    'code' => 'GAMMA-NOTE-80',
                    'unit' => 'piece',
                    'cost_price' => 7.00,
                    'sale_price' => 10.00,
                    'stock_quantity' => 18,
                    'low_stock_alert' => 6,
                ],
                [
                    'name' => 'Blue Pen',
                    'code' => 'GAMMA-PEN-BLUE',
                    'unit' => 'piece',
                    'cost_price' => 1.50,
                    'sale_price' => 2.50,
                    'stock_quantity' => 40,
                    'low_stock_alert' => 10,
                ],
            ]);

            $this->createPurchase(
                $alphaShop->id,
                $alphaOwner->id,
                'Dushanbe Food Supply',
                Carbon::now()->subDays(12),
                [
                    ['product_id' => $alphaProducts['ALPHA-COLA-1L']->id, 'quantity' => 80, 'price' => 8.50],
                    ['product_id' => $alphaProducts['ALPHA-SUGAR-5KG']->id, 'quantity' => 30, 'price' => 6.80],
                ],
            );
            $this->createPurchase(
                $alphaShop->id,
                $alphaSellerOne->id,
                'Oil Import LLC',
                Carbon::now()->subDays(8),
                [
                    ['product_id' => $alphaProducts['ALPHA-OIL-1L']->id, 'quantity' => 24, 'price' => 18.00],
                    ['product_id' => $alphaProducts['ALPHA-POWDER-3KG']->id, 'quantity' => 18, 'price' => 28.00],
                ],
            );

            $this->createPurchase(
                $betaShop->id,
                $betaOwner->id,
                'Khujand Retail Group',
                Carbon::now()->subDays(11),
                [
                    ['product_id' => $betaProducts['BETA-CHOCO-90G']->id, 'quantity' => 120, 'price' => 4.20],
                    ['product_id' => $betaProducts['BETA-WATER-15']->id, 'quantity' => 100, 'price' => 3.40],
                ],
            );
            $this->createPurchase(
                $betaShop->id,
                $betaSellerOne->id,
                'Northern Grain Hub',
                Carbon::now()->subDays(6),
                [
                    ['product_id' => $betaProducts['BETA-RICE-25KG']->id, 'quantity' => 60, 'price' => 7.10],
                    ['product_id' => $betaProducts['BETA-BATTERY-AA']->id, 'quantity' => 50, 'price' => 2.10],
                ],
            );

            $this->createPurchase(
                $gammaShop->id,
                $gammaOwner->id,
                'School Goods Market',
                Carbon::now()->subDays(10),
                [
                    ['product_id' => $gammaProducts['GAMMA-NOTE-80']->id, 'quantity' => 20, 'price' => 7.00],
                    ['product_id' => $gammaProducts['GAMMA-PEN-BLUE']->id, 'quantity' => 50, 'price' => 1.50],
                ],
            );

            $this->createSale(
                $alphaShop->id,
                $alphaSellerOne->id,
                'Customer Alpha 1',
                5.00,
                61.00,
                'cash',
                Carbon::now()->subDays(7),
                [
                    ['product_id' => $alphaProducts['ALPHA-COLA-1L']->id, 'quantity' => 4, 'price' => 11.00, 'cost_price' => 8.50],
                    ['product_id' => $alphaProducts['ALPHA-SUGAR-5KG']->id, 'quantity' => 3, 'price' => 8.90, 'cost_price' => 6.80],
                ],
            );
            $this->createSale(
                $alphaShop->id,
                $alphaSellerTwo->id,
                'Cafe Rudaki',
                0.00,
                58.50,
                'transfer',
                Carbon::now()->subDays(4),
                [
                    ['product_id' => $alphaProducts['ALPHA-OIL-1L']->id, 'quantity' => 2, 'price' => 23.50, 'cost_price' => 18.00],
                    ['product_id' => $alphaProducts['ALPHA-POWDER-3KG']->id, 'quantity' => 1, 'price' => 36.00, 'cost_price' => 28.00],
                ],
            );
            $this->createSale(
                $alphaShop->id,
                $alphaSellerOne->id,
                'Customer Alpha 2',
                2.00,
                20.00,
                'card',
                Carbon::now()->subDays(1),
                [
                    ['product_id' => $alphaProducts['ALPHA-COLA-1L']->id, 'quantity' => 2, 'price' => 11.00, 'cost_price' => 8.50],
                ],
            );

            $this->createSale(
                $betaShop->id,
                $betaSellerOne->id,
                'Customer Beta 1',
                0.00,
                39.00,
                'cash',
                Carbon::now()->subDays(5),
                [
                    ['product_id' => $betaProducts['BETA-CHOCO-90G']->id, 'quantity' => 6, 'price' => 6.50, 'cost_price' => 4.20],
                ],
            );
            $this->createSale(
                $betaShop->id,
                $betaSellerTwo->id,
                'Office Beta',
                3.00,
                52.60,
                'card',
                Carbon::now()->subDays(3),
                [
                    ['product_id' => $betaProducts['BETA-WATER-15']->id, 'quantity' => 7, 'price' => 5.00, 'cost_price' => 3.40],
                    ['product_id' => $betaProducts['BETA-BATTERY-AA']->id, 'quantity' => 6, 'price' => 3.40, 'cost_price' => 2.10],
                ],
            );
            $this->createSale(
                $betaShop->id,
                $betaSellerOne->id,
                'Family Beta',
                0.00,
                28.80,
                'transfer',
                Carbon::now()->subDays(1),
                [
                    ['product_id' => $betaProducts['BETA-RICE-25KG']->id, 'quantity' => 3, 'price' => 9.60, 'cost_price' => 7.10],
                ],
            );

            $this->createSale(
                $gammaShop->id,
                $gammaSeller->id,
                'School Client',
                0.00,
                17.50,
                'cash',
                Carbon::now()->subDays(2),
                [
                    ['product_id' => $gammaProducts['GAMMA-NOTE-80']->id, 'quantity' => 1, 'price' => 10.00, 'cost_price' => 7.00],
                    ['product_id' => $gammaProducts['GAMMA-PEN-BLUE']->id, 'quantity' => 3, 'price' => 2.50, 'cost_price' => 1.50],
                ],
            );

            $this->createExpense($alphaShop->id, $alphaOwner->id, 'Delivery', 2, 35.00, 'Weekly supplier delivery', Carbon::now()->subDays(9));
            $this->createExpense($alphaShop->id, $alphaSellerTwo->id, 'Packaging', 10, 3.50, 'Plastic bags and boxes', Carbon::now()->subDays(5));
            $this->createExpense($alphaShop->id, $alphaOwner->id, 'Internet', 1, 180.00, 'Monthly internet payment', Carbon::now()->subDays(1));

            $this->createExpense($betaShop->id, $betaOwner->id, 'Taxi delivery', 3, 28.00, 'City delivery costs', Carbon::now()->subDays(8));
            $this->createExpense($betaShop->id, $betaSellerOne->id, 'Cleaning', 4, 12.50, 'Store cleaning supplies', Carbon::now()->subDays(4));
            $this->createExpense($betaShop->id, $betaOwner->id, 'Electricity', 1, 240.00, 'Monthly bill', Carbon::now()->subDays(1));

            $this->createExpense($gammaShop->id, $gammaOwner->id, 'Shelf repair', 1, 90.00, 'Suspended shop maintenance', Carbon::now()->subDays(3));

            $this->createDebt(
                $alphaShop->id,
                $alphaSellerOne->id,
                'Cafe Rudaki',
                Carbon::now()->subDays(6),
                [
                    ['type' => 'give', 'amount' => 120.00, 'note' => 'Goods on credit', 'created_at' => Carbon::now()->subDays(6)],
                    ['type' => 'repay', 'amount' => 45.00, 'note' => 'Partial repayment', 'created_at' => Carbon::now()->subDays(2)],
                ],
            );
            $this->createDebt(
                $alphaShop->id,
                $alphaOwner->id,
                'Neighbour Store',
                Carbon::now()->subDays(10),
                [
                    ['type' => 'give', 'amount' => 60.00, 'note' => 'Opening balance', 'created_at' => Carbon::now()->subDays(10)],
                    ['type' => 'take', 'amount' => 15.00, 'note' => 'Returned goods', 'created_at' => Carbon::now()->subDays(3)],
                ],
            );

            $this->createDebt(
                $betaShop->id,
                $betaSellerTwo->id,
                'Family Beta',
                Carbon::now()->subDays(4),
                [
                    ['type' => 'give', 'amount' => 80.00, 'note' => 'Deferred payment', 'created_at' => Carbon::now()->subDays(4)],
                    ['type' => 'repay', 'amount' => 25.00, 'note' => 'Cash repayment', 'created_at' => Carbon::now()->subDay()],
                ],
            );

            $this->createDebt(
                $gammaShop->id,
                $gammaOwner->id,
                'School 15',
                Carbon::now()->subDays(5),
                [
                    ['type' => 'give', 'amount' => 35.00, 'note' => 'Stationery on credit', 'created_at' => Carbon::now()->subDays(5)],
                ],
            );

            $this->createAuditLog('auth.login', $alphaOwner->id, $alphaShop->id, ['device_name' => 'iphone-15-pro'], Carbon::now()->subDays(1));
            $this->createAuditLog('sales.created', $alphaSellerOne->id, $alphaShop->id, ['source' => 'mobile-demo'], Carbon::now()->subHours(20));
            $this->createAuditLog('expenses.created', $betaOwner->id, $betaShop->id, ['source' => 'mobile-demo'], Carbon::now()->subHours(16));
            $this->createAuditLog('debts.transaction_recorded', $betaSellerTwo->id, $betaShop->id, ['source' => 'mobile-demo'], Carbon::now()->subHours(8));
            $this->createAuditLog('auth.login', $gammaOwner->id, $gammaShop->id, ['device_name' => 'android-test'], Carbon::now()->subHours(4));
        });
    }

    private function upsertShop(array $attributes): Shop
    {
        return Shop::query()->updateOrCreate(
            ['name' => $attributes['name']],
            $attributes,
        );
    }

    /**
     * @param  array<int, Shop>  $shops
     */
    private function cleanupDemoData(array $shops): void
    {
        $shopIds = array_map(fn (Shop $shop): int => $shop->id, $shops);

        $demoUserEmails = [
            'owner.alpha@ck-accounting.test',
            'seller.alpha.1@ck-accounting.test',
            'seller.alpha.2@ck-accounting.test',
            'owner.beta@ck-accounting.test',
            'seller.beta.1@ck-accounting.test',
            'seller.beta.2@ck-accounting.test',
            'owner.gamma@ck-accounting.test',
            'seller.gamma.1@ck-accounting.test',
        ];

        $demoUserIds = User::query()
            ->whereIn('email', $demoUserEmails)
            ->pluck('id')
            ->all();

        if ($demoUserIds !== []) {
            PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $demoUserIds)
                ->delete();
        }

        AuditLog::query()->whereIn('shop_id', $shopIds)->delete();
        DebtTransaction::query()->whereIn('shop_id', $shopIds)->delete();
        SaleItem::query()->whereIn('shop_id', $shopIds)->delete();
        PurchaseItem::query()->whereIn('shop_id', $shopIds)->delete();
        Sale::query()->whereIn('shop_id', $shopIds)->delete();
        Purchase::query()->whereIn('shop_id', $shopIds)->delete();
        Expense::query()->whereIn('shop_id', $shopIds)->delete();
        Debt::query()->whereIn('shop_id', $shopIds)->delete();
        Product::query()->whereIn('shop_id', $shopIds)->delete();
        ShopSetting::query()->whereIn('shop_id', $shopIds)->delete();
        User::query()->whereIn('email', $demoUserEmails)->delete();
    }

    private function seedCurrencies(): void
    {
        Currency::query()->updateOrCreate(
            ['code' => 'TJS'],
            ['name' => 'Tajikistani Somoni', 'rate' => 1, 'is_default' => true],
        );

        Currency::query()->updateOrCreate(
            ['code' => 'USD'],
            ['name' => 'US Dollar', 'rate' => 10.920000, 'is_default' => false],
        );

        Currency::query()->updateOrCreate(
            ['code' => 'RUB'],
            ['name' => 'Russian Ruble', 'rate' => 0.120000, 'is_default' => false],
        );
    }

    private function createDemoUser(string $email, string $name, UserRole $role, int $shopId): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'shop_id' => $shopId,
            'role' => $role->value,
            'password' => Hash::make(self::DEMO_PASSWORD),
            'email_verified_at' => now(),
        ]);
    }

    private function createShopSetting(int $shopId, string $currencyCode, float $taxPercent): ShopSetting
    {
        return ShopSetting::query()->create([
            'shop_id' => $shopId,
            'default_currency' => $currencyCode,
            'tax_percent' => $taxPercent,
        ]);
    }

    /**
     * @param  array<int, array<string, int|float|string>>  $products
     * @return array<string, Product>
     */
    private function seedProducts(Shop $shop, User $creator, array $products): array
    {
        $seededProducts = [];

        foreach ($products as $attributes) {
            $product = Product::query()->create([
                ...$attributes,
                'shop_id' => $shop->id,
                'created_by' => $creator->id,
            ]);

            $seededProducts[$product->code] = $product;
        }

        return $seededProducts;
    }

    /**
     * @param  array<int, array<string, float|int>>  $items
     */
    private function createPurchase(int $shopId, int $userId, string $supplierName, Carbon $createdAt, array $items): Purchase
    {
        $totalAmount = collect($items)->sum(
            fn (array $item): float => (float) $item['quantity'] * (float) $item['price']
        );

        $purchase = Purchase::query()->create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'supplier_name' => $supplierName,
            'total_amount' => $totalAmount,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        foreach ($items as $item) {
            PurchaseItem::query()->create([
                'shop_id' => $shopId,
                'purchase_id' => $purchase->id,
                'product_id' => (int) $item['product_id'],
                'quantity' => (float) $item['quantity'],
                'price' => (float) $item['price'],
                'total' => (float) $item['quantity'] * (float) $item['price'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        return $purchase;
    }

    /**
     * @param  array<int, array<string, float|int>>  $items
     */
    private function createSale(
        int $shopId,
        int $userId,
        string $customerName,
        float $discount,
        float $paid,
        string $paymentType,
        Carbon $createdAt,
        array $items,
    ): Sale {
        $subTotal = collect($items)->sum(
            fn (array $item): float => (float) $item['quantity'] * (float) $item['price']
        );
        $total = max($subTotal - $discount, 0);
        $debt = max($total - $paid, 0);

        $sale = Sale::query()->create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'customer_name' => $customerName,
            'discount' => $discount,
            'paid' => $paid,
            'debt' => $debt,
            'total' => $total,
            'payment_type' => $paymentType,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        foreach ($items as $item) {
            SaleItem::query()->create([
                'shop_id' => $shopId,
                'sale_id' => $sale->id,
                'product_id' => (int) $item['product_id'],
                'quantity' => (float) $item['quantity'],
                'price' => (float) $item['price'],
                'cost_price' => (float) $item['cost_price'],
                'total' => (float) $item['quantity'] * (float) $item['price'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        return $sale;
    }

    private function createExpense(
        int $shopId,
        int $userId,
        string $name,
        float $quantity,
        float $price,
        ?string $note,
        Carbon $createdAt,
    ): Expense {
        return Expense::query()->create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'name' => $name,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $quantity * $price,
            'note' => $note,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /**
     * @param  array<int, array{type: string, amount: float, note: string, created_at: Carbon}>  $transactions
     */
    private function createDebt(int $shopId, int $userId, string $personName, Carbon $createdAt, array $transactions): Debt
    {
        $balance = collect($transactions)->sum(function (array $transaction): float {
            return $transaction['type'] === 'give'
                ? $transaction['amount']
                : -$transaction['amount'];
        });

        $debt = Debt::query()->create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'person_name' => $personName,
            'balance' => $balance,
            'created_at' => $createdAt,
            'updated_at' => Carbon::createFromTimestamp(max(array_map(
                fn (array $transaction): int => $transaction['created_at']->getTimestamp(),
                $transactions,
            ))),
        ]);

        foreach ($transactions as $transaction) {
            DebtTransaction::query()->create([
                'shop_id' => $shopId,
                'debt_id' => $debt->id,
                'user_id' => $userId,
                'type' => $transaction['type'],
                'amount' => $transaction['amount'],
                'note' => $transaction['note'],
                'created_at' => $transaction['created_at'],
                'updated_at' => $transaction['created_at'],
            ]);
        }

        return $debt;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createAuditLog(string $event, int $userId, int $shopId, array $metadata, Carbon $createdAt): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $userId,
            'shop_id' => $shopId,
            'event' => $event,
            'metadata' => $metadata,
            'created_at' => $createdAt,
        ]);
    }
}
