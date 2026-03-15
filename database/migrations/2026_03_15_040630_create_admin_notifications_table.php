<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type');               // appointment_accepted | appointment_rejected
            $table->string('message');
            $table->string('breed');
            $table->string('scan_id');
            $table->unsignedBigInteger('appointment_id');
            $table->string('appointment_date');
            $table->string('appointment_time');
            $table->string('vet_name');
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->foreign('appointment_id')
                ->references('id')
                ->on('appointments')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
