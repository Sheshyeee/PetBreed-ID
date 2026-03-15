<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // Links
            $table->string('scan_id');
            $table->unsignedBigInteger('result_id');
            $table->unsignedBigInteger('user_id');      // dog owner
            $table->unsignedBigInteger('created_by');   // admin / vet who made the booking

            // Appointment details
            $table->date('appointment_date');
            $table->string('appointment_time');
            $table->string('vet_name');
            $table->string('reason');
            $table->text('notes')->nullable();

            // Confirmation flow
            // pending → owner has not responded
            // accepted → owner confirmed
            // rejected → owner declined
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->foreign('scan_id')->references('scan_id')->on('results')->onDelete('cascade');
            $table->foreign('result_id')->references('id')->on('results')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};