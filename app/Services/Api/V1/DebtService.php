<?php

namespace App\Services\Api\V1;

use App\Enums\DebtDirection;
use App\Models\Debt;
use App\Models\DebtTransaction;
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
                // Immutable record of the operator's original choice.
                // `direction` may flip on overpayment; `original_direction`
                // stays as the seed for recomputeDebt during edits.
                'original_direction' => $direction,
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

    /**
     * Edit one of a debt's existing transactions. Walks the full
     * transaction history afterwards to recompute balance + direction
     * — see recomputeDebt for the math.
     */
    public function updateDebtTransaction(
        Debt $debt,
        DebtTransaction $transaction,
        User $actor,
        string $type,
        float $amount,
        ?string $note,
    ): Debt {
        return DB::transaction(function () use ($debt, $transaction, $actor, $type, $amount, $note): Debt {
            $before = [
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'note' => $transaction->note,
            ];

            $transaction->update([
                'type' => $type,
                'amount' => $amount,
                'note' => $note,
            ]);

            $this->recomputeDebt($debt);
            $debt->increment('version');

            $fresh = $debt->fresh(['user:id,name', 'transactions.user:id,name']);

            $this->auditLogger->log('debt_transactions.updated', $actor, $fresh, [
                'transaction_id' => $transaction->id,
                'before' => $before,
                'after' => ['type' => $type, 'amount' => $amount, 'note' => $note],
                'balance' => (float) $fresh->balance,
                'direction' => $fresh->direction?->value,
            ], (int) $debt->shop_id);

            $this->dashboardCacheVersion->bumpShop((int) $debt->shop_id);

            return $fresh;
        });
    }

    /**
     * Delete one transaction from a debt's history and recompute the
     * parent balance / direction. Opening transactions can be deleted
     * just like any other — recomputeDebt handles the resulting state.
     */
    public function deleteDebtTransaction(
        Debt $debt,
        DebtTransaction $transaction,
        User $actor,
    ): Debt {
        return DB::transaction(function () use ($debt, $transaction, $actor): Debt {
            $snapshot = [
                'transaction_id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => (float) $transaction->amount,
                'note' => $transaction->note,
            ];

            $transaction->delete();

            $this->recomputeDebt($debt);
            $debt->increment('version');

            $fresh = $debt->fresh(['user:id,name', 'transactions.user:id,name']);

            $this->auditLogger->log('debt_transactions.deleted', $actor, $fresh, [
                ...$snapshot,
                'balance' => (float) $fresh->balance,
                'direction' => $fresh->direction?->value,
            ], (int) $debt->shop_id);

            $this->dashboardCacheVersion->bumpShop((int) $debt->shop_id);

            return $fresh;
        });
    }

    /**
     * Recompute Debt.balance and Debt.direction from the transaction
     * history. Used after editing or deleting individual transactions.
     *
     * Algorithm: walk transactions in chronological order, applying
     * delta = +amount for `give`, −amount for `take`/`repay`. The
     * resulting signed sum is interpreted against `original_direction`
     * — positive sums stay in the original direction; a negative sum
     * means the debt has flipped (overpayment / overcollection) so the
     * sign is normalised and `direction` is set to the opposite of the
     * original.
     */
    private function recomputeDebt(Debt $debt): void
    {
        $signedSum = 0.0;
        foreach ($debt->transactions()->orderBy('created_at')->orderBy('id')->get() as $tx) {
            // `type` may be cast as an enum (DebtTransactionType) — unwrap
            // via `->value` so the match works on raw strings.
            $typeValue = $tx->type instanceof \BackedEnum ? $tx->type->value : (string) $tx->type;
            $delta = match ($typeValue) {
                'give' => (float) $tx->amount,
                'take', 'repay' => -(float) $tx->amount,
                default => 0.0,
            };
            $signedSum += $delta;
        }

        $original = $debt->original_direction ?? $debt->direction;
        if ($signedSum >= 0) {
            $newBalance = $signedSum;
            $newDirection = $original;
        } else {
            $newBalance = abs($signedSum);
            $newDirection = $original === DebtDirection::Receivable
                ? DebtDirection::Payable
                : DebtDirection::Receivable;
        }

        $debt->update([
            'balance' => $newBalance,
            'direction' => $newDirection,
        ]);
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
