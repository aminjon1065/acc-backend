<?php

namespace App\Services\Api\V1;

use App\Enums\DebtDirection;
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
        private readonly DashboardCacheVersion $dashboardCacheVersion,
    ) {}

    public function createDebt(User $actor, int $shopId, string $personName, string $direction, float $openingBalance, ?string $id = null): Debt
    {
        return DB::transaction(function () use ($actor, $shopId, $personName, $direction, $openingBalance, $id): Debt {
            $attributes = [
                'shop_id' => $shopId,
                'user_id' => $actor->id,
                'person_name' => $personName,
                'direction' => $direction,
                'balance' => $openingBalance,
            ];

            if ($id) {
                $attributes['id'] = $id;
            }

            $debt = $this->debts->create($attributes);

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

    public function storeTransaction(Debt $debt, User $actor, string $type, float $amount, ?string $note, ?string $transactionId = null): Debt
    {
        return DB::transaction(function () use ($debt, $actor, $type, $amount, $note, $transactionId): Debt {
            // Lock BEFORE reading balance — prevents race condition where
            // concurrent requests both read the same balance and both
            // write. We no longer reject `repay > balance` (the bazaar use
            // case: customer overpays a 1000 debt with 1500 → store now
            // owes 500). Instead we let the balance go negative inside the
            // transaction and flip `direction` below.
            $debt->lockForUpdate();

            $delta = match ($type) {
                'give' => $amount,
                'take', 'repay' => -$amount,
            };

            $txAttributes = [
                'shop_id' => $debt->shop_id,
                'user_id' => $actor->id,
                'type' => $type,
                'amount' => $amount,
                'note' => $note,
            ];

            if ($transactionId) {
                $txAttributes['id'] = $transactionId;
            }

            $debt->transactions()->create($txAttributes);

            // Atomic increment using raw query builder to avoid Eloquent
            // cast collision with DB::raw expressions on decimal columns.
            DB::table('debts')->where('id', $debt->id)->update([
                'balance' => DB::raw("balance + {$delta}"),
            ]);
            $debt->increment('version');
            $debt->refresh();

            // Overpayment / overcollection: balance crossed zero. The row
            // now expresses a debt in the OPPOSITE direction — flip
            // `direction` and store the absolute balance so the field
            // stays unsigned, matching the rest of the codebase's
            // invariant (Debt.balance >= 0, sign comes from direction).
            //
            // Example: receivable, balance 1000. Customer pays 1500.
            //   raw balance after delta: -500
            //   flip: direction → payable, balance → 500
            //   meaning: store now owes the customer 500.
            //
            // The history transaction stays a `repay` of 1500 — that's
            // what actually happened. The current balance / direction
            // reflect the resulting state.
            if ((float) $debt->balance < 0) {
                $flipped = $debt->direction === DebtDirection::Receivable
                    ? DebtDirection::Payable
                    : DebtDirection::Receivable;
                DB::table('debts')->where('id', $debt->id)->update([
                    'balance' => abs((float) $debt->balance),
                    'direction' => $flipped->value,
                ]);
                $debt->refresh();
            }

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

    /**
     * Rename the contact on an existing debt. The balance, direction, and
     * transaction history are untouched — those flow through storeTransaction.
     */
    public function updateDebt(Debt $debt, User $actor, string $personName): Debt
    {
        return DB::transaction(function () use ($debt, $actor, $personName): Debt {
            $previousName = $debt->person_name;
            $debt->update(['person_name' => $personName]);
            $debt->increment('version');

            $this->auditLogger->log('debts.updated', $actor, $debt, [
                'person_name' => $personName,
                'previous_person_name' => $previousName,
            ], (int) $debt->shop_id);

            $this->dashboardCacheVersion->bumpShop((int) $debt->shop_id);

            return $debt->fresh(['user:id,name', 'transactions.user:id,name']);
        });
    }

    public function deleteDebt(Debt $debt, User $actor): void
    {
        DB::transaction(function () use ($debt, $actor): void {
            $shopId = (int) $debt->shop_id;

            // Delete dependent transactions first, then the parent. Wrapping
            // both in one DB transaction means a crash mid-cleanup can't
            // leave orphan debt_transactions referencing a deleted parent
            // (or an undeleted parent with zero transactions reachable).
            $debt->transactions()->delete();
            $debt->delete();

            $this->auditLogger->log('debts.deleted', $actor, $debt, [
                'person_name' => $debt->person_name,
                'balance' => (float) $debt->balance,
            ], $shopId);

            $this->dashboardCacheVersion->bumpShop($shopId);
        });
    }
}
