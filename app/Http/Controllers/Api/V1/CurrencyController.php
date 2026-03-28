<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCurrencyRequest;
use App\Http\Resources\Api\V1\CurrencyResource;
use App\Models\Currency;
use App\Repositories\Api\V1\CurrencyRepository;
use App\Services\Api\V1\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyRepository $currencies,
        private readonly CurrencyService $currencyService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Currency::class);

        $currencies = $this->currencies->paginate($request->integer('limit', 20));

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

        $updatedCurrency = $this->currencyService->updateCurrency(
            $request->user(),
            $currency,
            $request->validated()
        );

        return new CurrencyResource($updatedCurrency->fresh());
    }
}
