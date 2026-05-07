<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the multi-shop ownership FK. An owner may have many shops; a
     * shop has at most one owner. Sellers stay tied to a single shop via
     * `users.shop_id`. NULL `owner_id` means the shop has not yet been
     * assigned to any owner — admins assign it explicitly via the shops
     * admin UI.
     *
     * `nullOnDelete` on the FK so deleting an owner doesn't cascade-delete
     * their shops; the shop simply becomes unassigned and the admin can
     * re-assign it.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->foreignId('owner_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropIndex(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
