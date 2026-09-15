<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('room_id')
                ->constrained('rooms')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete(); // Room keeper yang melapor

            $table->enum('status_reported', ['dirty', 'clean', 'maintenance']);
            $table->text('notes')->nullable(); // Catatan kerusakan fasilitas jika ada
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_logs');
    }
};