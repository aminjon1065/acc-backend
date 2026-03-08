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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('paid', 14, 2)->default(0);
            $table->decimal('debt', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('payment_type')->default('cash');
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
