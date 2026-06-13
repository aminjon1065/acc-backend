<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill the corrected debt for sales that already had returns.
     *
     * A prior bug subtracted the full return amount from `debt` even for the
     * part refunded in cash (which also reduced `paid`), double-counting it
     * and driving the debt to 0 on paid-up sales. Recompute the invariant the
     * rest of the app now maintains: debt = max(total − all-returns − paid, 0).
     *
     * Idempotent: re-running recomputes from the same stable inputs.
     */
    public function up(): void
    {
        DB::table('sales')
            ->select('id', 'total', 'paid')
            ->orderBy('id')
            ->chunk(500, function ($sales): void {
                foreach ($sales as $sale) {
                    $returned = (float) DB::table('sale_returns')
                        ->where('sale_id', $sale->id)
                        ->sum('total');

                    $debt = max((float) $sale->total - (float) $sale->paid - $returned, 0);

                    DB::table('sales')->where('id', $sale->id)->update(['debt' => $debt]);
                }
            });
    }

    /**
     * No down path — the previous values were incorrect and cannot be
     * meaningfully restored.
     */
    public function down(): void
    {
        // Intentionally irreversible.
    }
};
