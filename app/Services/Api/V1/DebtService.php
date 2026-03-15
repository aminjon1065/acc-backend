<?php

namespace App\Services\Api\V1;

use App\Models\Debt;
use App\Models\User;
use App\Repositories\Api\V1\DebtRepository;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class DebtService
{
    public function __construct(
        private readonly DebtRepository $debts,
        private readonly AuditLogger $auditLogger,
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

            return $freshDebt;
        });
    }

    public function storeTransaction(Debt $debt, User $actor, string $type, float $amount, ?string $note): Debt
    {
        return DB::transaction(function () use ($debt, $actor, $type, $amount, $note): Debt {
            $delta = $type === 'give' ? $amount : -$amount;

            $debt->transactions()->create([
                'shop_id' => $debt->shop_id,
                'user_id' => $actor->id,
                'type' => $type,
                'amount' => $amount,
                'note' => $note,
            ]);

            $debt->update([
                'balance' => (float) $debt->balance + $delta,
            ]);

            $freshDebt = $debt->fresh('transactions');

            $this->auditLogger->log('debts.transaction_recorded', $actor, $freshDebt, [
                'type' => $type,
                'amount' => $amount,
                'note' => $note,
                'balance' => (float) $freshDebt->balance,
            ], $debt->shop_id);

            return $freshDebt;
        });
    }
}
