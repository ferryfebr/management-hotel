<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Kode transaksi/booking unik

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->foreignId('room_id')
                ->constrained('rooms')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete(); // Resepsionis yang melayani

            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedInteger('total_days');

            $table->decimal('room_price_per_night', 12, 2); // Snapshot harga saat transaksi dibuat
            $table->decimal('total_price', 12, 2); // Total biaya kamar kotor

            $table->enum('discount_type', ['fixed', 'percentage'])->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('final_price', 12, 2); // Total bersih setelah diskon

            $table->decimal('down_payment', 12, 2)->default(0);
            $table->decimal('remaining_payment', 12, 2)->default(0);

            $table->enum('status', ['reserved', 'checked_in', 'checked_out', 'cancelled', 'no_show'])
                ->default('reserved');

            $table->timestamps();
            $table->softDeletes(); // Jangan hapus permanen data transaksi keuangan

            // Index untuk mempercepat query cek bentrok reservasi & laporan
            $table->index(['room_id', 'check_in_date', 'check_out_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};