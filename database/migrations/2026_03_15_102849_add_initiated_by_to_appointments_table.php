<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // 'clinic' = admin/vet created | 'user' = owner requested
            $table->enum('initiated_by', ['clinic', 'user'])->default('clinic')->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('initiated_by');
        });
    }
};