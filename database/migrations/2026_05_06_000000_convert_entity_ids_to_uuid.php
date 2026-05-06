<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL 8 requires explicitly dropping FK constraints before modifying
        // referenced/referencing columns, even with FOREIGN_KEY_CHECKS=0.
        // Drop all affected FKs, alter columns, then restore FKs.

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropForeign(['sale_return_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('debt_transactions', function (Blueprint $table) {
            $table->dropForeign(['debt_id']);
        });

        // ── Parent PKs ────────────────────────────────────────────────────────
        DB::statement('ALTER TABLE products MODIFY COLUMN id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE expenses MODIFY COLUMN id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE sales MODIFY COLUMN id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE purchases MODIFY COLUMN id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE debts MODIFY COLUMN id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE debt_transactions MODIFY COLUMN id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE sale_items MODIFY COLUMN id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE purchase_items MODIFY COLUMN id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE sale_returns MODIFY COLUMN id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE sale_return_items MODIFY COLUMN id CHAR(36) NOT NULL');

        // ── FK columns ────────────────────────────────────────────────────────
        DB::statement('ALTER TABLE purchase_items MODIFY COLUMN purchase_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE purchase_items MODIFY COLUMN product_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE sale_items MODIFY COLUMN sale_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE sale_items MODIFY COLUMN product_id CHAR(36) NULL');
        DB::statement('ALTER TABLE sale_returns MODIFY COLUMN sale_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE sale_return_items MODIFY COLUMN sale_return_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE sale_return_items MODIFY COLUMN product_id CHAR(36) NULL');
        DB::statement('ALTER TABLE debt_transactions MODIFY COLUMN debt_id CHAR(36) NOT NULL');

        // ── Restore FK constraints ────────────────────────────────────────────
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->foreign('sale_return_id')->references('id')->on('sale_returns')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('debt_transactions', function (Blueprint $table) {
            $table->foreign('debt_id')->references('id')->on('debts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropForeign(['sale_return_id']);
            $table->dropForeign(['product_id']);
        });

        Schema::table('debt_transactions', function (Blueprint $table) {
            $table->dropForeign(['debt_id']);
        });

        // ── Restore integer PKs ───────────────────────────────────────────────
        DB::statement('ALTER TABLE sale_return_items MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE sale_returns MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE purchase_items MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE sale_items MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE debt_transactions MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE debts MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE purchases MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE sales MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE expenses MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE products MODIFY COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // ── Restore integer FK columns ────────────────────────────────────────
        DB::statement('ALTER TABLE purchase_items MODIFY COLUMN purchase_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE purchase_items MODIFY COLUMN product_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE sale_items MODIFY COLUMN sale_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE sale_items MODIFY COLUMN product_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE sale_returns MODIFY COLUMN sale_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE sale_return_items MODIFY COLUMN sale_return_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE sale_return_items MODIFY COLUMN product_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE debt_transactions MODIFY COLUMN debt_id BIGINT UNSIGNED NOT NULL');

        // ── Restore FK constraints ────────────────────────────────────────────
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->foreign('sale_return_id')->references('id')->on('sale_returns')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('debt_transactions', function (Blueprint $table) {
            $table->foreign('debt_id')->references('id')->on('debts')->cascadeOnDelete();
        });
    }
};
