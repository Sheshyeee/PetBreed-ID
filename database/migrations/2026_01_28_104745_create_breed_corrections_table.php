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
        Schema::create('breed_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('scan_id'); // Link to original scan
            $table->string('image_path'); // Store path for easy display
            $table->string('original_breed'); // What the AI thought
            $table->string('corrected_breed'); // What the Human said
            $table->float('confidence')->default(100.00);
            $table->string('status')->default('Added to Dataset'); // Added to Dataset, Retrained, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('breed_corrections');
    }
};
