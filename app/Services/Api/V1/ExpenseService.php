<?php

namespace App\Services\Api\V1;

use App\Models\Expense;
use App\Models\User;
use App\Repositories\Api\V1\ExpenseRepository;

class ExpenseService
{
    public function __construct(private readonly ExpenseRepository $expenses) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createExpense(User $actor, int $shopId, array $validated): Expense
    {
        $quantity = (float) $validated['quantity'];
        $price = (float) $validated['price'];

        return $this->expenses->create([
            ...$validated,
            'shop_id' => $shopId,
            'user_id' => $actor->id,
            'total' => $quantity * $price,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateExpense(Expense $expense, array $validated): Expense
    {
        $expense->fill($validated);
        $quantity = (float) ($validated['quantity'] ?? $expense->quantity);
        $price = (float) ($validated['price'] ?? $expense->price);
        $expense->total = $quantity * $price;
        $expense->save();

        return $expense;
    }

    public function deleteExpense(Expense $expense): void
    {
        $expense->delete();
    }
}
