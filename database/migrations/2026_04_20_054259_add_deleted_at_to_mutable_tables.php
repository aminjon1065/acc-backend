<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds soft-delete support (deleted_at column) to all mutable accounting entities.
     * Deleted records are included in sync list responses so mobile clients can
     * remove them from local SQLite.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->softDeletesTz();
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->softDeletesTz();
        });

        Schema::table('debts', function (Blueprint $table): void {
            $table->softDeletesTz();
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->softDeletesTz();
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->softDeletesTz();
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->softDeletesTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('debts', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
