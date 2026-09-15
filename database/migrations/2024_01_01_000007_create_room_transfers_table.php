<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->foreignId('from_room_id')
                ->constrained('rooms')
                ->restrictOnDelete();

            $table->foreignId('to_room_id')
                ->constrained('rooms')
                ->restrictOnDelete();

            $table->text('reason'); // Alasan pindah kamar, misal: AC tidak dingin
            $table->timestamp('transferred_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_transfers');
    }
};