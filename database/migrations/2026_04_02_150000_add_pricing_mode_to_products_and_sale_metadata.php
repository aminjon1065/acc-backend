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
            $table->string('pricing_mode')->default('fixed')->after('sale_price');
            $table->decimal('markup_percent', 8, 2)->nullable()->after('pricing_mode');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('type')->default('product')->after('customer_name');
            $table->text('notes')->nullable()->after('payment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['type', 'notes']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'markup_percent']);
        });
    }
};
