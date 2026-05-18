<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\EnforcesEntityVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDebtRequest;
use App\Http\Requests\Api\V1\StoreDebtTransactionRequest;
use App\Http\Requests\Api\V1\UpdateDebtRequest;
use App\Http\Requests\Api\V1\UpdateDebtTransactionRequest;
use App\Http\Resources\Api\V1\DebtResource;
use App\Models\Debt;
use App\Models\DebtTransaction;
use App\Repositories\Api\V1\DebtRepository;
use App\Services\Api\V1\DebtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DebtController extends Controller
{
    use EnforcesEntityVersion;

    public function __construct(
        private readonly DebtRepository $debts,
        private readonly DebtService $debtService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Debt::class);

        $debts = $this->debts->paginateForUser(
            $request->user(),
            $request->integer('limit', 20),
            ['user:id,name', 'transactions.user:id,name'],
            $request,
        );

        return DebtResource::collection($debts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDebtRequest $request): DebtResource
    {
        $this->authorize('create', Debt::class);

        $actor = $request->user();
        $shopId = $actor->resolveShopIdForWrite($request->integer('shop_id') ?: null);

        $debt = $this->debtService->createDebt(
            $actor,
            $shopId,
            $request->validated('person_name'),
            $request->string('direction')->toString() ?: 'receivable',
            (float) $request->input('opening_balance', 0),
            $request->validated('id') ?: null,
        );

        return new DebtResource($debt);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Debt $debt): DebtResource
    {
        $this->authorize('view', $debt);

        $scoped = $this->debts->findForUser($request->user(), $debt->id, ['user:id,name', 'transactions.user:id,name']);

        return new DebtResource($scoped);
    }

    /**
     * Rename the contact on a debt. The balance + direction + transactions
     * stay as-is; only the human-readable label changes.
     */
    public function update(UpdateDebtRequest $request, Debt $debt): DebtResource
    {
        $this->authorize('update', $debt);

        $this->enforceVersionMatch(
            $request,
            $debt,
            fn () => new DebtResource($this->debts->findForUser($request->user(), $debt->id, ['user:id,name', 'transactions.user:id,name'])),
            'debt',
        );

        $scoped = $this->debts->findForUser($request->user(), $debt->id);
        $updated = $this->debtService->updateDebt(
            $scoped,
            $request->user(),
            $request->validated('person_name'),
        );

        return new DebtResource($updated);
    }

    public function storeTransaction(StoreDebtTransactionRequest $request, Debt $debt): DebtResource
    {
        $this->authorize('update', $debt);

        $this->enforceVersionMatch(
            $request,
            $debt,
            fn () => new DebtResource($this->debts->findForUser($request->user(), $debt->id, ['user:id,name', 'transactions.user:id,name'])),
            'debt',
        );

        $scopedDebt = $this->debts->findForUser($request->user(), $debt->id);
        $updatedDebt = $this->debtService->storeTransaction(
            $scopedDebt,
            $request->user(),
            $request->validated('type'),
            (float) $request->validated('amount'),
            $request->input('note'),
            $request->validated('id') ?: null,
        );

        return new DebtResource($updatedDebt);
    }

    /**
     * Edit one of a debt's existing transactions. Authorised via the
     * debt's `update` policy — sellers can only touch their own debts,
     * owners can touch any debt in their shop.
     */
    public function updateTransaction(
        UpdateDebtTransactionRequest $request,
        Debt $debt,
        DebtTransaction $transaction,
    ): DebtResource {
        $this->authorize('update', $debt);

        // Tie the transaction to the parent — route binding doesn't
        // verify the relationship.
        abort_unless($transaction->debt_id === $debt->id, 404);

        $this->enforceVersionMatch(
            $request,
            $debt,
            fn () => new DebtResource($this->debts->findForUser($request->user(), $debt->id, ['user:id,name', 'transactions.user:id,name'])),
            'debt',
        );

        $scoped = $this->debts->findForUser($request->user(), $debt->id);
        $updated = $this->debtService->updateDebtTransaction(
            $scoped,
            $transaction,
            $request->user(),
            (string) $request->validated('type'),
            (float) $request->validated('amount'),
            $request->input('note'),
        );

        return new DebtResource($updated);
    }

    /**
     * Remove a single transaction from a debt's history. Balance and
     * direction are recomputed against the remaining transactions.
     */
    public function destroyTransaction(Request $request, Debt $debt, DebtTransaction $transaction): DebtResource
    {
        $this->authorize('update', $debt);

        abort_unless($transaction->debt_id === $debt->id, 404);

        $scoped = $this->debts->findForUser($request->user(), $debt->id);
        $updated = $this->debtService->deleteDebtTransaction($scoped, $transaction, $request->user());

        return new DebtResource($updated);
    }

    public function transactions(Request $request, Debt $debt): JsonResponse
    {
        $this->authorize('view', $debt);

        $query = DebtTransaction::query()
            ->with('user:id,name')
            ->where('debt_id', $debt->id)
            ->orderBy('created_at', 'asc');

        if ($request->filled('created_after')) {
            $query->where('created_at', '>', $request->input('created_after'));
        }

        $transactions = $query->limit(100)->get();

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $transactions->map(fn ($tx) => [
                'id' => $tx->id,
                'debt_id' => $tx->debt_id,
                'type' => $tx->type,
                'amount' => (float) $tx->amount,
                'note' => $tx->note,
                'created_by_name' => $tx->user?->name,
                'created_at' => $tx->created_at?->toISOString(),
            ]),
        ]);
    }

    public function destroy(Request $request, Debt $debt): JsonResponse
    {
        $this->authorize('delete', $debt);

        $this->enforceVersionMatch(
            $request,
            $debt,
            fn () => new DebtResource($this->debts->findForUser($request->user(), $debt->id, ['user:id,name', 'transactions.user:id,name'])),
            'debt',
        );

        $scopedDebt = $this->debts->findForUser($request->user(), $debt->id);
        $this->debtService->deleteDebt($scopedDebt, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Debt deleted successfully.',
            'data' => null,
        ]);
    }
}
