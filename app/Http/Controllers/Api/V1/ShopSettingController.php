<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateShopSettingRequest;
use App\Http\Resources\Api\V1\ShopSettingResource;
use App\Models\ShopSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShopSettingController extends Controller
{
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

        $setting->fill($request->validated());
        $setting->save();

        return new ShopSettingResource($setting);
    }

    private function resolveShopId(Request $request): int
    {
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            return (int) $user->shop_id;
        }

        $shopId = $request->integer('shop_id');

        if (! $shopId) {
            throw ValidationException::withMessages([
                'shop_id' => ['shop_id is required for super admin settings access.'],
            ]);
        }

        return $shopId;
    }
}
