<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateShopSettingRequest;
use App\Http\Resources\Api\V1\ShopSettingResource;
use App\Models\ShopSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShopSettingController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(Request $request): ShopSettingResource
    {
        $shopId = $this->resolveShopId($request);

        $setting = ShopSetting::query()->firstOrCreate(
            ['shop_id' => $shopId],
            ['default_currency' => 'TJS', 'tax_percent' => 0]
        );

        return new ShopSettingResource($setting);
    }

    public function update(UpdateShopSettingRequest $request): ShopSettingResource
    {
        $shopId = $this->resolveShopId($request);

        $setting = ShopSetting::query()->firstOrCreate(
            ['shop_id' => $shopId],
            ['default_currency' => 'TJS', 'tax_percent' => 0]
        );

        $before = $setting->only(['default_currency', 'tax_percent']);

        $setting->fill($request->validated());
        $setting->save();

        $this->auditLogger->log('settings.updated', $request->user(), $setting, [
            'before' => $before,
            'after' => $setting->only(['default_currency', 'tax_percent']),
        ], $shopId);

        return new ShopSettingResource($setting);
    }

    private function resolveShopId(Request $request): int
    {
        $user = $request->user();

        // Owner / super_admin can pick any shop they have access to via the
        // request `shop_id` parameter — required for owners with multiple
        // shops since there's no implicit single shop. Sellers always
        // resolve to their assigned `user.shop_id`.
        $accessibleShopIds = $user->accessibleShopIds();

        if ($accessibleShopIds !== null && count($accessibleShopIds) === 1) {
            // Single accessible shop: seller, or owner with only one shop.
            return (int) $accessibleShopIds[0];
        }

        $shopId = $request->integer('shop_id');
        if (! $shopId) {
            throw ValidationException::withMessages([
                'shop_id' => ['shop_id is required.'],
            ]);
        }

        if ($accessibleShopIds !== null && ! in_array($shopId, $accessibleShopIds, true)) {
            throw ValidationException::withMessages([
                'shop_id' => ['You do not have access to that shop.'],
            ]);
        }

        return $shopId;
    }
}
