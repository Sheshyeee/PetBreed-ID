<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if column doesn't exist before adding
        if (!Schema::hasColumn('results', 'age_simulation')) {
            Schema::table('results', function (Blueprint $table) {
                $table->text('age_simulation')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn('age_simulation');
        });
    }
};
