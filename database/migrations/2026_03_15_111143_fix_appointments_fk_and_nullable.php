<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Drop scan_id FK if it exists ──────────────────────────────────────
        $scanFk = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'appointments'
            AND CONSTRAINT_NAME = 'appointments_scan_id_foreign'
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");

        if ($scanFk[0]->cnt > 0) {
            DB::statement('ALTER TABLE appointments DROP FOREIGN KEY appointments_scan_id_foreign');
        }

        // ── Drop result_id FK if it exists ────────────────────────────────────
        $resultFk = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'appointments'
            AND CONSTRAINT_NAME = 'appointments_result_id_foreign'
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");

        if ($resultFk[0]->cnt > 0) {
            DB::statement('ALTER TABLE appointments DROP FOREIGN KEY appointments_result_id_foreign');
        }

        // ── Make both columns nullable ────────────────────────────────────────
        DB::statement('ALTER TABLE appointments MODIFY scan_id VARCHAR(255) NULL');
        DB::statement('ALTER TABLE appointments MODIFY result_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE appointments MODIFY scan_id VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE appointments MODIFY result_id BIGINT UNSIGNED NOT NULL');
    }
};
