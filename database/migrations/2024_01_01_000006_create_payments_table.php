<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel tambahan (di luar skema awal) agar riwayat pembayaran
     * tercatat per transaksi pembayaran, bukan cuma 2 kolom total.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'transfer', 'qris', 'debit', 'kartu_kredit'])
                ->default('cash');
            $table->enum('type', ['dp', 'pelunasan', 'refund'])->default('dp');

            $table->foreignId('received_by')
                ->constrained('users')
                ->restrictOnDelete(); // Petugas yang menerima pembayaran

            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};