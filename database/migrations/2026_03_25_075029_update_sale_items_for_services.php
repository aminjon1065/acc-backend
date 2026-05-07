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
        Schema::table('sale_items', function (Blueprint $table) {
            // product_id was bigint when this migration was first authored;
            // since the v24 UUID conversion both products.id and the FK
            // are CHAR(36). Re-declaring the column with the wrong type on
            // MySQL would trip the FK-incompatibility check (SQLITE doesn't
            // enforce this, which is why the test suite missed it).
            $table->uuid('product_id')->nullable()->change();
            $table->string('name')->nullable()->after('product_id');
            $table->string('unit')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['name', 'unit']);
            $table->uuid('product_id')->nullable(false)->change();
        });
    }
};
