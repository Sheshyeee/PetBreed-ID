<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'scan_verified', 'scan_created', etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Store scan_id, breed, etc.
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};
