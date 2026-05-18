<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds `original_direction` — an immutable record of what the user
     * picked when they first created the debt. We need this for
     * recomputeDebt: when the user edits or deletes a transaction, we
     * walk the remaining transactions from scratch and have to know
     * which side of zero counts as the canonical positive direction.
     *
     * The existing `direction` column already flips on overpay/overcollect
     * — once it flips, we've lost the original choice. Backfill from
     * the current `direction` for pre-existing rows (best-effort; debts
     * that have already flipped will be assumed to have started in
     * their CURRENT direction, which is wrong for those edge cases but
     * inert for the new edit feature).
     */
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table): void {
            $table->string('original_direction')->nullable()->after('direction');
        });

        DB::table('debts')->update([
            'original_direction' => DB::raw('direction'),
        ]);
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table): void {
            $table->dropColumn('original_direction');
        });
    }
};
