<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop scan_id foreign key if exists
        DB::statement("
            ALTER TABLE appointments 
            DROP FOREIGN KEY IF EXISTS appointments_scan_id_foreign
        ");

        // Drop result_id foreign key if exists  
        DB::statement("
            ALTER TABLE appointments 
            DROP FOREIGN KEY IF EXISTS appointments_result_id_foreign
        ");

        // Make both columns nullable
        DB::statement("
            ALTER TABLE appointments 
            MODIFY scan_id VARCHAR(255) NULL,
            MODIFY result_id BIGINT UNSIGNED NULL
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE appointments MODIFY scan_id VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE appointments MODIFY result_id BIGINT UNSIGNED NOT NULL");
    }
};