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
    private const DEMO_PASSWORD = 'Demo12345!';

    /**
     * @var array<string, array{name: string, email: string, role: UserRole}>
     */
    private const DEMO_USERS = [
        'alphaOwner' => [
            'name' => 'Фарзона Каримова',
            'email' => 'farzona@ck.top',
            'role' => UserRole::Owner,
        ],
        'alphaSellerOne' => [
            'name' => 'Далер Саидов',
            'email' => 'daler@ck.top',
            'role' => UserRole::Seller,
        ],
        'alphaSellerTwo' => [
            'name' => 'Нилуфар Юсупова',
            'email' => 'nilufar@ck.top',
            'role' => UserRole::Seller,
        ],
        'betaOwner' => [
            'name' => 'Камол Набиев',
            'email' => 'kamol@ck.top',
            'role' => UserRole::Owner,
        ],
        'betaSellerOne' => [
            'name' => 'Зебо Хасанова',
            'email' => 'zebo@ck.top',
            'role' => UserRole::Seller,
        ],
        'betaSellerTwo' => [
            'name' => 'Рустам Мирзоев',
            'email' => 'rustam@ck.top',
            'role' => UserRole::Seller,
        ],
        'gammaOwner' => [
            'name' => 'Мохира Ашурова',
            'email' => 'mohira@ck.top',
            'role' => UserRole::Owner,
        ],
        'gammaSeller' => [
            'name' => 'Сиёвуш Рахимов',
            'email' => 'siyovush@ck.top',
            'role' => UserRole::Seller,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedCurrencies();

            $alphaShop = $this->upsertShop([
                'name' => 'Сомон Маркет',
                'owner_name' => self::DEMO_USERS['alphaOwner']['name'],
                'phone' => '+992900000101',
                'email' => 'somon@ck.top',
                'address' => 'Душанбе, проспект Рудаки 11',
                'status' => 'active',
            ]);

            // Second shop owned by the same owner — exercises the multi-shop
            // owner UI (shop picker, owned_shop_ids accessor, write-time shop
            // resolution in `User::resolveShopIdForWrite`).
            $alphaShopTwo = $this->upsertShop([
                'name' => 'Сомон Маркет — Филиал',
                'owner_name' => self::DEMO_USERS['alphaOwner']['name'],
                'phone' => '+992900000111',
                'email' => 'somon2@ck.top',
                'address' => 'Душанбе, улица Айни 47',
                'status' => 'active',
            ]);

            $betaShop = $this->upsertShop([
                'name' => 'Сугд Минимаркет',
                'owner_name' => self::DEMO_USERS['betaOwner']['name'],
                'phone' => '+992900000102',
                'email' => 'sugd@ck.top',
                'address' => 'Худжанд, улица Сомони 24',
                'status' => 'active',
            ]);

            $gammaShop = $this->upsertShop([
                'name' => 'Орзу Канцтовары',
                'owner_name' => self::DEMO_USERS['gammaOwner']['name'],
                'phone' => '+992900000103',
                'email' => 'orzu@ck.top',
                'address' => 'Бохтар, улица Исмоили Сомони 7',
                'status' => 'suspended',
            ]);

            $this->cleanupDemoData([$alphaShop, $alphaShopTwo, $betaShop, $gammaShop]);

            // Owners are NOT pinned to a single `users.shop_id`. Ownership is
            // expressed via `shops.owner_id`; the accessor `owned_shop_ids`
            // resolves it. Setting shop_id on an owner row would be confusing
            // and conflict with multi-shop ownership.
            $alphaOwner = $this->createDemoUserByKey('alphaOwner', null);
            $alphaSellerOne = $this->createDemoUserByKey('alphaSellerOne', $alphaShop->id);
            $alphaSellerTwo = $this->createDemoUserByKey('alphaSellerTwo', $alphaShop->id);

            $betaOwner = $this->createDemoUserByKey('betaOwner', null);
            $betaSellerOne = $this->createDemoUserByKey('betaSellerOne', $betaShop->id);
            $betaSellerTwo = $this->createDemoUserByKey('betaSellerTwo', $betaShop->id);

            $gammaOwner = $this->createDemoUserByKey('gammaOwner', null);
            $gammaSeller = $this->createDemoUserByKey('gammaSeller', $gammaShop->id);

            // Assign ownership now that the owner users exist.
            $alphaShop->forceFill(['owner_id' => $alphaOwner->id])->save();
            $alphaShopTwo->forceFill(['owner_id' => $alphaOwner->id])->save();
            $betaShop->forceFill(['owner_id' => $betaOwner->id])->save();
            $gammaShop->forceFill(['owner_id' => $gammaOwner->id])->save();

            $this->createShopSetting($alphaShop->id, 'TJS', 2.50);
            $this->createShopSetting($alphaShopTwo->id, 'TJS', 2.50);
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
                    // Markup-mode product: sale_price is derived from
                    // cost_price * (1 + markup_percent / 100). Exercises the
                    // dynamic-pricing branch in cart UI + reports.
                    'name' => 'Sugar',
                    'code' => 'ALPHA-SUGAR-5KG',
                    'unit' => 'kg',
                    'cost_price' => 6.80,
                    'sale_price' => 8.90,
                    'pricing_mode' => 'markup',
                    'markup_percent' => 30.0,
                    'stock_quantity' => 54,
                    'low_stock_alert' => 10,
                ],
                [
                    // Bulk-pricing product: switches to bulk_price once cart
                    // quantity >= bulk_threshold. Common for wholesale flows.
                    'name' => 'Sunflower Oil',
                    'code' => 'ALPHA-OIL-1L',
                    'unit' => 'liter',
                    'cost_price' => 18.00,
                    'sale_price' => 23.50,
                    'bulk_price' => 21.00,
                    'bulk_threshold' => 6,
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

            // Second alpha shop — smaller catalog, ensures multi-shop owner
            // sees distinct products per shop in the UI.
            $alphaShopTwoProducts = $this->seedProducts($alphaShopTwo, $alphaOwner, [
                [
                    'name' => 'Хлеб лаваш',
                    'code' => 'ALPHA2-BREAD',
                    'unit' => 'piece',
                    'cost_price' => 2.00,
                    'sale_price' => 3.50,
                    'stock_quantity' => 60,
                    'low_stock_alert' => 10,
                ],
                [
                    'name' => 'Молоко 1L',
                    'code' => 'ALPHA2-MILK-1L',
                    'unit' => 'liter',
                    'cost_price' => 7.50,
                    'sale_price' => 9.50,
                    'stock_quantity' => 25,
                    'low_stock_alert' => 8,
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
                'ООО Душанбе Фуд Саплай',
                Carbon::now()->subDays(12),
                [
                    ['product_id' => $alphaProducts['ALPHA-COLA-1L']->id, 'quantity' => 80, 'price' => 8.50],
                    ['product_id' => $alphaProducts['ALPHA-SUGAR-5KG']->id, 'quantity' => 30, 'price' => 6.80],
                ],
            );
            $this->createPurchase(
                $alphaShop->id,
                $alphaSellerOne->id,
                'Ойл Импорт',
                Carbon::now()->subDays(8),
                [
                    ['product_id' => $alphaProducts['ALPHA-OIL-1L']->id, 'quantity' => 24, 'price' => 18.00],
                    ['product_id' => $alphaProducts['ALPHA-POWDER-3KG']->id, 'quantity' => 18, 'price' => 28.00],
                ],
            );

            // Purchase for the second alpha shop — multi-shop owners need
            // to see stock movement scoped per shop, not shared across both.
            $this->createPurchase(
                $alphaShopTwo->id,
                $alphaOwner->id,
                'Хлебзавод №2',
                Carbon::now()->subDays(5),
                [
                    ['product_id' => $alphaShopTwoProducts['ALPHA2-BREAD']->id, 'quantity' => 50, 'price' => 2.00],
                    ['product_id' => $alphaShopTwoProducts['ALPHA2-MILK-1L']->id, 'quantity' => 20, 'price' => 7.50],
                ],
            );

            $this->createPurchase(
                $betaShop->id,
                $betaOwner->id,
                'Худжанд Ритейл Групп',
                Carbon::now()->subDays(11),
                [
                    ['product_id' => $betaProducts['BETA-CHOCO-90G']->id, 'quantity' => 120, 'price' => 4.20],
                    ['product_id' => $betaProducts['BETA-WATER-15']->id, 'quantity' => 100, 'price' => 3.40],
                ],
            );
            $this->createPurchase(
                $betaShop->id,
                $betaSellerOne->id,
                'Северный Зерновой Склад',
                Carbon::now()->subDays(6),
                [
                    ['product_id' => $betaProducts['BETA-RICE-25KG']->id, 'quantity' => 60, 'price' => 7.10],
                    ['product_id' => $betaProducts['BETA-BATTERY-AA']->id, 'quantity' => 50, 'price' => 2.10],
                ],
            );

            $this->createPurchase(
                $gammaShop->id,
                $gammaOwner->id,
                'Школьный Базар',
                Carbon::now()->subDays(10),
                [
                    ['product_id' => $gammaProducts['GAMMA-NOTE-80']->id, 'quantity' => 20, 'price' => 7.00],
                    ['product_id' => $gammaProducts['GAMMA-PEN-BLUE']->id, 'quantity' => 50, 'price' => 1.50],
                ],
            );

            $this->createSale(
                $alphaShop->id,
                $alphaSellerOne->id,
                'Мунира Сафарова',
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
                'Кафе Рудаки',
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
                'Саидмурод Назаров',
                2.00,
                20.00,
                'card',
                Carbon::now()->subDays(1),
                [
                    ['product_id' => $alphaProducts['ALPHA-COLA-1L']->id, 'quantity' => 2, 'price' => 11.00, 'cost_price' => 8.50],
                ],
            );

            // Service-type sale: no products, just a named service line.
            // Exercises the `type=service` branch in SaleResource (renders
            // service_name) and the nullable product_id path on sale_items.
            $this->createSale(
                $alphaShop->id,
                $alphaOwner->id,
                'Чайхона Рохат',
                0.00,
                150.00,
                'cash',
                Carbon::now()->subDays(2),
                [
                    [
                        'name' => 'Ремонт холодильной витрины',
                        'unit' => 'услуга',
                        'quantity' => 1,
                        'price' => 150.00,
                        'cost_price' => 0,
                    ],
                ],
                type: 'service',
            );

            // Sale on the second alpha shop — confirms shop scoping for a
            // multi-shop owner end to end (this sale must NOT show up in
            // alphaShop's list).
            $this->createSale(
                $alphaShopTwo->id,
                $alphaOwner->id,
                'Местный житель',
                0.00,
                15.50,
                'cash',
                Carbon::now()->subHours(20),
                [
                    ['product_id' => $alphaShopTwoProducts['ALPHA2-BREAD']->id, 'quantity' => 2, 'price' => 3.50, 'cost_price' => 2.00],
                    ['product_id' => $alphaShopTwoProducts['ALPHA2-MILK-1L']->id, 'quantity' => 1, 'price' => 9.50, 'cost_price' => 7.50],
                ],
            );

            $this->createSale(
                $betaShop->id,
                $betaSellerOne->id,
                'Азиза Назирова',
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
                'Офис Сугд Трейд',
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
                'Семья Каримовых',
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
                'Школа №15',
                0.00,
                17.50,
                'cash',
                Carbon::now()->subDays(2),
                [
                    ['product_id' => $gammaProducts['GAMMA-NOTE-80']->id, 'quantity' => 1, 'price' => 10.00, 'cost_price' => 7.00],
                    ['product_id' => $gammaProducts['GAMMA-PEN-BLUE']->id, 'quantity' => 3, 'price' => 2.50, 'cost_price' => 1.50],
                ],
            );

            $this->createExpense($alphaShop->id, $alphaOwner->id, 'Доставка', 2, 35.00, 'Еженедельная доставка от поставщика', Carbon::now()->subDays(9));
            $this->createExpense($alphaShop->id, $alphaSellerTwo->id, 'Упаковка', 10, 3.50, 'Пакеты и коробки для покупателей', Carbon::now()->subDays(5));
            $this->createExpense($alphaShop->id, $alphaOwner->id, 'Интернет', 1, 180.00, 'Оплата интернета за месяц', Carbon::now()->subDays(1));
            $this->createExpense($alphaShopTwo->id, $alphaOwner->id, 'Аренда филиала', 1, 800.00, 'Месячная аренда помещения', Carbon::now()->subDays(2));

            $this->createExpense($betaShop->id, $betaOwner->id, 'Доставка на такси', 3, 28.00, 'Городские расходы на доставку', Carbon::now()->subDays(8));
            $this->createExpense($betaShop->id, $betaSellerOne->id, 'Уборка', 4, 12.50, 'Чистящие средства для магазина', Carbon::now()->subDays(4));
            $this->createExpense($betaShop->id, $betaOwner->id, 'Электричество', 1, 240.00, 'Ежемесячный счет за свет', Carbon::now()->subDays(1));

            $this->createExpense($gammaShop->id, $gammaOwner->id, 'Ремонт полки', 1, 90.00, 'Поддержка магазина в период паузы', Carbon::now()->subDays(3));

            $this->createDebt(
                $alphaShop->id,
                $alphaSellerOne->id,
                'Кафе Рудаки',
                Carbon::now()->subDays(6),
                [
                    ['type' => 'give', 'amount' => 120.00, 'note' => 'Товар в долг', 'created_at' => Carbon::now()->subDays(6)],
                    ['type' => 'repay', 'amount' => 45.00, 'note' => 'Частичное погашение', 'created_at' => Carbon::now()->subDays(2)],
                ],
            );
            $this->createDebt(
                $alphaShop->id,
                $alphaOwner->id,
                'Соседний магазин',
                Carbon::now()->subDays(10),
                [
                    ['type' => 'give', 'amount' => 60.00, 'note' => 'Стартовый долг', 'created_at' => Carbon::now()->subDays(10)],
                    ['type' => 'take', 'amount' => 15.00, 'note' => 'Возврат товара', 'created_at' => Carbon::now()->subDays(3)],
                ],
            );

            // Payable debt — we owe the supplier money (the bazaar-style
            // direction='payable' branch). The mobile UI flips colors and
            // verbiage for this case; without a payable debt in seed data
            // that codepath stays unexercised.
            $this->createDebt(
                $alphaShop->id,
                $alphaOwner->id,
                'Поставщик Душанбе Фуд Саплай',
                Carbon::now()->subDays(8),
                [
                    ['type' => 'give', 'amount' => 320.00, 'note' => 'Закупка под отсрочку', 'created_at' => Carbon::now()->subDays(8)],
                    ['type' => 'repay', 'amount' => 100.00, 'note' => 'Частичная оплата', 'created_at' => Carbon::now()->subDays(2)],
                ],
                direction: 'payable',
            );

            $this->createDebt(
                $betaShop->id,
                $betaSellerTwo->id,
                'Семья Каримовых',
                Carbon::now()->subDays(4),
                [
                    ['type' => 'give', 'amount' => 80.00, 'note' => 'Отсроченный платеж', 'created_at' => Carbon::now()->subDays(4)],
                    ['type' => 'repay', 'amount' => 25.00, 'note' => 'Погашение наличными', 'created_at' => Carbon::now()->subDay()],
                ],
            );

            $this->createDebt(
                $gammaShop->id,
                $gammaOwner->id,
                'Школа №15',
                Carbon::now()->subDays(5),
                [
                    ['type' => 'give', 'amount' => 35.00, 'note' => 'Канцтовары в долг', 'created_at' => Carbon::now()->subDays(5)],
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

        $demoUserEmails = $this->demoUserEmails();

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
        Sale::query()->withTrashed()->whereIn('shop_id', $shopIds)->forceDelete();
        Purchase::query()->withTrashed()->whereIn('shop_id', $shopIds)->forceDelete();
        Expense::query()->withTrashed()->whereIn('shop_id', $shopIds)->forceDelete();
        Debt::query()->withTrashed()->whereIn('shop_id', $shopIds)->forceDelete();
        Product::query()->withTrashed()->whereIn('shop_id', $shopIds)->forceDelete();
        ShopSetting::query()->whereIn('shop_id', $shopIds)->delete();
        // forceDelete: User now uses SoftDeletes, but the demo seeder must
        // wipe rows entirely so a re-seed doesn't trip the unique email index
        // on the leftover tombstones.
        User::query()->withTrashed()->whereIn('email', $demoUserEmails)->forceDelete();
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

    private function createDemoUser(string $email, string $name, UserRole $role, ?int $shopId): User
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

    private function createDemoUserByKey(string $key, ?int $shopId): User
    {
        $user = self::DEMO_USERS[$key];

        return $this->createDemoUser(
            $user['email'],
            $user['name'],
            $user['role'],
            $shopId,
        );
    }

    /**
     * @return list<string>
     */
    private function demoUserEmails(): array
    {
        return array_values(array_map(
            static fn (array $user): string => $user['email'],
            self::DEMO_USERS,
        ));
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
     * @param  array<int, array<string, int|float|string|null>>  $products
     * @return array<string, Product>
     */
    private function seedProducts(Shop $shop, User $creator, array $products): array
    {
        $seededProducts = [];

        foreach ($products as $attributes) {
            // Defaults so callers can omit columns they don't care about
            // (`pricing_mode` etc. fall back to the migration defaults).
            $payload = array_merge([
                'pricing_mode' => 'fixed',
                'markup_percent' => null,
                'bulk_price' => null,
                'bulk_threshold' => null,
            ], $attributes);

            $product = Product::query()->create([
                ...$payload,
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
                'product_id' => $item['product_id'],
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
     * Items may be one of two shapes:
     *   • Product line: ['product_id' => int, 'quantity' => float, 'price' => float, 'cost_price' => float]
     *   • Service line: ['name' => string, 'unit' => string, 'quantity' => float, 'price' => float, 'cost_price' => float]
     *
     * Service-type sales must pass `type = 'service'`; their items leave
     * `product_id` null and carry the human-readable `name` / `unit` on
     * the sale_item row (see migration `update_sale_items_for_services`).
     *
     * @param  array<int, array<string, float|int|string|null>>  $items
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
        string $type = 'product',
    ): Sale {
        $subTotal = collect($items)->sum(
            fn (array $item): float => (float) $item['quantity'] * (float) $item['price']
        );
        $total = max($subTotal - $discount, 0);
        $debt = max($total - $paid, 0);

        $sale = Sale::query()->create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'type' => $type,
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
                'product_id' => $item['product_id'] ?? null,
                'name' => $item['name'] ?? null,
                'unit' => $item['unit'] ?? null,
                'quantity' => (float) $item['quantity'],
                'price' => (float) $item['price'],
                'cost_price' => (float) ($item['cost_price'] ?? 0),
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
     * @param  string  $direction  'receivable' (we lent money — default) or 'payable' (we owe money)
     */
    private function createDebt(
        int $shopId,
        int $userId,
        string $personName,
        Carbon $createdAt,
        array $transactions,
        string $direction = 'receivable',
    ): Debt {
        $balance = collect($transactions)->sum(function (array $transaction): float {
            return $transaction['type'] === 'give'
                ? $transaction['amount']
                : -$transaction['amount'];
        });

        $debt = Debt::query()->create([
            'shop_id' => $shopId,
            'user_id' => $userId,
            'person_name' => $personName,
            'direction' => $direction,
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
