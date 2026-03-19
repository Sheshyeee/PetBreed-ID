<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop scan_id FK if it exists
        if (Schema::hasColumn('appointments', 'scan_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                try {
                    $table->dropForeign(['scan_id']);
                } catch (\Exception $e) {
                    // FK didn't exist, ignore
                }
            });
        }

        // Drop result_id FK if it exists
        if (Schema::hasColumn('appointments', 'result_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                try {
                    $table->dropForeign(['result_id']);
                } catch (\Exception $e) {
                    // FK didn't exist, ignore
                }
            });
        }

        // Make both columns nullable
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('scan_id')->nullable()->change();
            $table->unsignedBigInteger('result_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('scan_id')->nullable(false)->change();
            $table->unsignedBigInteger('result_id')->nullable(false)->change();
        });
    }
};