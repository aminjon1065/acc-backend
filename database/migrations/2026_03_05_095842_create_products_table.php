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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('unit')->default('piece');
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('sale_price', 14, 2)->default(0);
            $table->decimal('stock_quantity', 14, 3)->default(0);
            $table->decimal('low_stock_alert', 14, 3)->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'name']);
            $table->unique(['shop_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
