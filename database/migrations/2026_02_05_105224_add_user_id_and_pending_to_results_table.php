<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            // Add user_id to associate scans with users
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->onDelete('cascade');

            // Add pending column for verification status
            $table->enum('pending', ['pending', 'verified'])->default('pending')->after('confidence');

            // Add index for faster queries
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'pending']);
        });
    }
};
