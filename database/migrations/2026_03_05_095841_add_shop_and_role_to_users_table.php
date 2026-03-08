<?php

use App\UserRole;
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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->default(UserRole::Seller->value);

            $table->index(['shop_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'role']);
            $table->dropConstrainedForeignId('shop_id');
            $table->dropColumn('role');
        });
    }
};
