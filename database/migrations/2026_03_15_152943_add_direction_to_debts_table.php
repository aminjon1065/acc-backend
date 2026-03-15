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
        Schema::table('debts', function (Blueprint $table): void {
            $table->string('direction')->default('receivable')->after('person_name');
            $table->index(['shop_id', 'direction']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table): void {
            $table->dropIndex(['shop_id', 'direction']);
            $table->dropColumn('direction');
        });
    }
};
