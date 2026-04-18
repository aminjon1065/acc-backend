<?php

namespace App\Services\Api\V1;

use App\Models\Expense;
use App\Models\User;
use App\Repositories\Api\V1\ExpenseRepository;
use App\Services\AuditLogger;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepository $expenses,
        private readonly AuditLogger $auditLogger,
        private readonly DashboardCacheVersion $dashboardCacheVersion,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createExpense(User $actor, int $shopId, array $validated): Expense
    {
        $quantity = (float) $validated['quantity'];
        $price = (float) $validated['price'];

        $expense = $this->expenses->create([
            ...$validated,
            'shop_id' => $shopId,
            'user_id' => $actor->id,
            'total' => $quantity * $price,
        ]);

        $this->auditLogger->log('expenses.created', $actor, $expense, [
            'name' => $expense->name,
            'total' => (float) $expense->total,
        ], $shopId);

        $this->dashboardCacheVersion->bumpShop($shopId);

        return $expense;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateExpense(User $actor, Expense $expense, array $validated): Expense
    {
        $before = $expense->only(['name', 'quantity', 'price', 'total', 'note']);

        $expense->fill($validated);
        $quantity = (float) ($validated['quantity'] ?? $expense->quantity);
        $price = (float) ($validated['price'] ?? $expense->price);
        $expense->total = $quantity * $price;
        $expense->save();

        $this->auditLogger->log('expenses.updated', $actor, $expense, [
            'before' => $before,
            'after' => $expense->only(['name', 'quantity', 'price', 'total', 'note']),
        ]);

        $this->dashboardCacheVersion->bumpShop((int) $expense->shop_id);

        return $expense;
    }

    public function deleteExpense(User $actor, Expense $expense): void
    {
        $shopId = (int) $expense->shop_id;
        $metadata = [
            'name' => $expense->name,
            'total' => (float) $expense->total,
        ];

        $expense->delete();

        $this->auditLogger->log('expenses.deleted', $actor, metadata: $metadata, shopId: $shopId);
        $this->dashboardCacheVersion->bumpShop($shopId);
    }
}
