<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')
                ->constrained('room_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete(); // Cegah hapus room_type jika masih dipakai kamar

            $table->string('room_number')->unique(); // Nomor/nama kamar, misal: 101, 102
            $table->enum('status', ['available', 'occupied', 'dirty', 'maintenance'])
                ->default('available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};