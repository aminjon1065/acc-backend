<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrepareProductionDatabaseCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:db-prepare-production
                            {--force : Skip the interactive confirmation prompt}';

    /**
     * @var string
     */
    protected $description = 'Wipe all tenant + transactional data and leave only the super_admin user. Idempotent.';

    /**
     * Truncated in order; FK checks are disabled around the loop so child→parent
     * order is irrelevant. Reference / lookup tables (currencies) and framework
     * plumbing (sessions, cache, jobs, migrations, personal_access_tokens) are
     * handled separately so we never accidentally drop the schema state row.
     *
     * @var list<string>
     */
    private const TENANT_TABLES = [
        'sale_return_items',
        'sale_returns',
        'sale_items',
        'sales',
        'purchase_items',
        'purchases',
        'debt_transactions',
        'debts',
        'expenses',
        'products',
        'shop_settings',
        'audit_logs',
        'shops',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm(
            'This will DELETE all shops, products, sales, purchases, expenses, debts, audit logs and every non-admin user. Continue?',
            false,
        )) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $this->info('Preparing database for production…');

        /**
         * Intentionally no DB::transaction here: MySQL TRUNCATE is a DDL
         * statement and implicitly commits the surrounding transaction —
         * wrapping it would crash at the outer commit with "no active
         * transaction". Each TRUNCATE is atomic on its own, and the DELETE
         * statements below execute against an already-empty (FK-wise) schema,
         * so partial-failure recovery doesn't add value here.
         */
        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::TENANT_TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)->truncate();
                $this->line("  · truncated {$table}");
            }

            /**
             * Drop every user that isn't a super_admin, plus their auth
             * tokens. We delete tokens explicitly so the orphan-token row
             * for a removed user doesn't outlive its owner — Sanctum's
             * `tokenable_id` is a polymorphic FK without an enforced
             * relation, so there's no cascade to lean on.
             */
            $nonAdminUserIds = DB::table('users')
                ->where('role', '!=', UserRole::SuperAdmin->value)
                ->pluck('id');

            if ($nonAdminUserIds->isNotEmpty()) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $nonAdminUserIds)
                    ->delete();

                DB::table('users')->whereIn('id', $nonAdminUserIds)->delete();
                $this->line("  · deleted {$nonAdminUserIds->count()} non-admin user(s) + their tokens");
            }

            /**
             * Clear super_admin shop bindings so the surviving admin user
             * doesn't reference a shop id we just truncated.
             */
            DB::table('users')
                ->where('role', UserRole::SuperAdmin->value)
                ->update(['shop_id' => null]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('Re-running AdminUserSeeder to guarantee admin@ck.top exists…');
        (new AdminUserSeeder)->setCommand($this)->run();

        $this->call('cache:clear');

        $adminCount = DB::table('users')
            ->where('role', UserRole::SuperAdmin->value)
            ->count();
        $totalUsers = DB::table('users')->count();

        $this->newLine();
        $this->info("Done. Users in DB: {$totalUsers} (super_admin: {$adminCount}).");

        return self::SUCCESS;
    }
}
