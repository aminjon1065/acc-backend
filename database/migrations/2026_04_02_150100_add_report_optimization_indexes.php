<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['shop_id', 'updated_at'], 'products_shop_updated_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index(['shop_id', 'payment_type', 'created_at'], 'sales_shop_payment_created_idx');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->index(['sale_id', 'created_at'], 'sale_items_sale_created_idx');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->index(['shop_id', 'direction', 'balance'], 'debts_shop_direction_balance_idx');
        });

        Schema::table('debt_transactions', function (Blueprint $table) {
            $table->index(['shop_id', 'created_at'], 'debt_transactions_shop_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debt_transactions', function (Blueprint $table) {
            $table->dropIndex('debt_transactions_shop_created_idx');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->dropIndex('debts_shop_direction_balance_idx');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex('sale_items_sale_created_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_shop_payment_created_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_shop_updated_idx');
        });
    }
};
