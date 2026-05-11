<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `audit_logs.auditable_id` was originally created via `nullableMorphs`,
     * which stores the id as an unsigned BIGINT. Once we switched Product /
     * Purchase / Sale to UUID primary keys, audit-write attempts for those
     * models truncated the 36-char UUID into the numeric column and MySQL
     * raised the 1265 "Data truncated" warning (in strict mode this is
     * promoted to an error and the write fails).
     *
     * Widen the column to CHAR(36) so it stores both legacy numeric ids
     * (as digit strings) and the new UUIDs. The accompanying morph index
     * (auditable_type, auditable_id) stays intact across an ALTER MODIFY
     * in MySQL — index entries are simply rebuilt against the new type.
     *
     * SQLite (the test runner) treats column types as advisory and never
     * truncates, so the alter is a no-op there. We still skip the raw SQL
     * on non-MySQL connections to keep `php artisan test` portable.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('audit_logs', function () {
            DB::statement('ALTER TABLE `audit_logs` MODIFY `auditable_id` CHAR(36) NULL');
        });
    }

    /**
     * Restoring the original BIGINT type would corrupt any audit rows that
     * already reference UUID auditables. We refuse to roll back rather
     * than silently lose data.
     */
    public function down(): void
    {
        // No-op: see the up() docblock for rationale.
    }
};
