<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel tambahan agar setiap perpanjangan masa inap (extendStay)
     * tercatat sebagai histori, bukan menimpa langsung check_out_date.
     */
    public function up(): void
    {
        Schema::create('stay_extensions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();

            $table->date('old_checkout_date');
            $table->date('new_checkout_date');
            $table->unsignedInteger('additional_days');
            $table->decimal('additional_price', 12, 2); // Tambahan tagihan akibat extend

            $table->foreignId('extended_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stay_extensions');
    }
};