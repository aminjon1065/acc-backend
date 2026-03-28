<?php

namespace App\Services\Api\V1;

use App\Models\Currency;
use App\Models\User;
use App\Repositories\Api\V1\CurrencyRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CurrencyService
{
    public function __construct(
        private readonly CurrencyRepository $currencies,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCurrency(User $actor, Currency $currency, array $attributes): Currency
    {
        $before = $currency->only(['code', 'rate', 'is_default']);

        return DB::transaction(function () use ($actor, $currency, $attributes, $before): Currency {
            $currency->fill($attributes);

            if (($attributes['is_default'] ?? false)) {
                $this->currencies->unsetDefaultExcept($currency->id);
                $currency->is_default = true;
            }

            $currency->save();

            $this->auditLogger->log('currencies.updated', $actor, $currency, [
                'before' => $before,
                'after' => $currency->only(['code', 'rate', 'is_default']),
            ]);

            return $currency;
        });
    }
}
