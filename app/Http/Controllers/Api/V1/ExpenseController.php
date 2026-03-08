<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreExpenseRequest;
use App\Http\Requests\Api\V1\UpdateExpenseRequest;
use App\Http\Resources\Api\V1\ExpenseResource;
use App\Models\Expense;
use App\Repositories\Api\V1\ExpenseRepository;
use App\Services\Api\V1\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseRepository $expenses,
        private readonly ExpenseService $expenseService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Expense::class);

        $expenses = $this->expenses->paginateForUser($request->user(), $request->integer('limit', 20));

        return ExpenseResource::collection($expenses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseRequest $request): ExpenseResource
    {
        $this->authorize('create', Expense::class);

        $actor = $request->user();
        $shopId = $actor->isSuperAdmin()
            ? $request->integer('shop_id')
            : $actor->shop_id;

        if ($actor->isSuperAdmin() && ! $shopId) {
            throw ValidationException::withMessages([
                'shop_id' => ['shop_id is required for super admin expense creation.'],
            ]);
        }

        $expense = $this->expenseService->createExpense($actor, (int) $shopId, $request->validated());

        return new ExpenseResource($expense);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Expense $expense): ExpenseResource
    {
        $this->authorize('view', $expense);

        $scoped = $this->expenses->findForUser($request->user(), $expense->id);

        return new ExpenseResource($scoped);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $this->authorize('update', $expense);

        $scoped = $this->expenses->findForUser($request->user(), $expense->id);
        $updated = $this->expenseService->updateExpense($scoped, $request->validated());

        return new ExpenseResource($updated);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        $scoped = $this->expenses->findForUser($request->user(), $expense->id);
        $this->expenseService->deleteExpense($scoped);

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted.',
            'data' => null,
        ]);
    }
}
