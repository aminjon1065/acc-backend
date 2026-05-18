<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds optional `unit` column to expenses so the report export can
     * show "Канцтовары · 10 шт × 50" instead of bare numbers. Matches
     * the unit column already present on products / sale_items.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('unit')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn('unit');
        });
    }
};
