<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCurrencyRequest;
use App\Http\Resources\Api\V1\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CurrencyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Currency::class);

        $currencies = Currency::query()
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->paginate($request->integer('limit', 20))
            ->withQueryString();

        return CurrencyResource::collection($currencies);
    }

    /**
     * Display the specified resource.
     */
    public function show(Currency $currency): CurrencyResource
    {
        $this->authorize('view', $currency);

        return new CurrencyResource($currency);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCurrencyRequest $request, Currency $currency): CurrencyResource
    {
        $this->authorize('update', $currency);

        DB::transaction(function () use ($request, $currency): void {
            $currency->fill($request->validated());

            if ($request->boolean('is_default')) {
                Currency::query()->whereKeyNot($currency->id)->update(['is_default' => false]);
                $currency->is_default = true;
            }

            $currency->save();
        });

        return new CurrencyResource($currency->fresh());
    }
}
