<?php

namespace App\Services\Api\V1;

use App\Models\Debt;
use App\Models\User;
use App\Repositories\Api\V1\DebtRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DebtService
{
    public function __construct(
        private readonly DebtRepository $debts,
        private readonly AuditLogger $auditLogger,
        private readonly DashboardCacheVersion $dashboardCacheVersion,
    ) {}

    public function createDebt(User $actor, int $shopId, string $personName, string $direction, float $openingBalance): Debt
    {
        return DB::transaction(function () use ($actor, $shopId, $personName, $direction, $openingBalance): Debt {
            $debt = $this->debts->create([
                'shop_id' => $shopId,
                'user_id' => $actor->id,
                'person_name' => $personName,
                'direction' => $direction,
                'balance' => $openingBalance,
            ]);

            if ($openingBalance > 0) {
                $debt->transactions()->create([
                    'shop_id' => $shopId,
                    'user_id' => $actor->id,
                    'type' => 'give',
                    'amount' => $openingBalance,
                    'note' => 'Opening balance',
                ]);
            }

            $freshDebt = $debt->fresh('transactions');

            $this->auditLogger->log('debts.created', $actor, $freshDebt, [
                'person_name' => $personName,
                'direction' => $direction,
                'opening_balance' => $openingBalance,
                'balance' => (float) $freshDebt->balance,
            ], $shopId);

            $this->dashboardCacheVersion->bumpShop($shopId);

            return $freshDebt;
        });
    }

    public function storeTransaction(Debt $debt, User $actor, string $type, float $amount, ?string $note): Debt
    {
        return DB::transaction(function () use ($debt, $actor, $type, $amount, $note): Debt {
            // Lock BEFORE reading balance — prevents race condition where concurrent
            // requests both read the same balance, both pass validation, then both write
            $debt->lockForUpdate();

            if (in_array($type, ['take', 'repay'], true)) {
                $maxAmount = (float) $debt->balance;
                if ($amount > $maxAmount) {
                    throw ValidationException::withMessages([
                        'amount' => ["Amount ({$amount}) cannot exceed current balance ({$maxAmount})."],
                    ]);
                }
            }

            $delta = match ($type) {
                'give' => $amount,
                'take', 'repay' => -$amount,
            };

            $debt->transactions()->create([
                'shop_id' => $debt->shop_id,
                'user_id' => $actor->id,
                'type' => $type,
                'amount' => $amount,
                'note' => $note,
            ]);

            // Atomic increment using DB::raw to prevent race condition
            $debt->update([
                'balance' => DB::raw("balance + {$delta}"),
            ]);

            $freshDebt = $debt->fresh('transactions');

            $this->auditLogger->log('debts.transaction_recorded', $actor, $freshDebt, [
                'type' => $type,
                'amount' => $amount,
                'note' => $note,
                'balance' => (float) $freshDebt->balance,
            ], $debt->shop_id);

            $this->dashboardCacheVersion->bumpShop((int) $debt->shop_id);

            return $freshDebt;
        });
    }
}
