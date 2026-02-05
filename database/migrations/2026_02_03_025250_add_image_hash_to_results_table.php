<?php

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
        Schema::table('results', function (Blueprint $table) {
            // Add image_hash column for exact image matching
            $table->string('image_hash', 64)->nullable()->after('image');

            // Add index for faster lookups
            $table->index('image_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex(['image_hash']);
            $table->dropColumn('image_hash');
        });
    }
};
