<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDebtRequest;
use App\Http\Requests\Api\V1\StoreDebtTransactionRequest;
use App\Http\Resources\Api\V1\DebtResource;
use App\Models\Debt;
use App\Models\DebtTransaction;
use App\Repositories\Api\V1\DebtRepository;
use App\Services\Api\V1\DebtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class DebtController extends Controller
{
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
            ['transactions'],
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
        $shopId = $actor->isSuperAdmin()
            ? $request->integer('shop_id')
            : $actor->shop_id;

        if ($actor->isSuperAdmin() && ! $shopId) {
            throw ValidationException::withMessages([
                'shop_id' => ['shop_id is required for super admin debt creation.'],
            ]);
        }

        $debt = $this->debtService->createDebt(
            $actor,
            (int) $shopId,
            $request->validated('person_name'),
            $request->string('direction')->toString() ?: 'receivable',
            (float) $request->input('opening_balance', 0),
        );

        return new DebtResource($debt);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Debt $debt): DebtResource
    {
        $this->authorize('view', $debt);

        $scoped = $this->debts->findForUser($request->user(), $debt->id, ['transactions']);

        return new DebtResource($scoped);
    }

    public function storeTransaction(StoreDebtTransactionRequest $request, Debt $debt): DebtResource
    {
        $this->authorize('update', $debt);

        $scopedDebt = $this->debts->findForUser($request->user(), $debt->id);
        $updatedDebt = $this->debtService->storeTransaction(
            $scopedDebt,
            $request->user(),
            $request->validated('type'),
            (float) $request->validated('amount'),
            $request->input('note'),
        );

        return new DebtResource($updatedDebt);
    }

    public function transactions(Request $request, Debt $debt): JsonResponse
    {
        $this->authorize('view', $debt);

        $query = DebtTransaction::query()
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
                'created_at' => $tx->created_at?->toISOString(),
            ]),
        ]);
    }
}
