<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('id_card_number')->nullable()->unique(); // Nomor KTP, untuk deteksi tamu lama
            $table->string('id_card_photo')->nullable(); // Path file foto KTP (simpan di disk private)
            $table->unsignedInteger('visit_count')->default(0); // Total kunjungan menginap
            $table->enum('rating_status', ['regular', 'vip', 'blacklisted'])->default('regular');
            $table->text('notes')->nullable(); // Catatan khusus perilaku tamu
            $table->timestamps();
            $table->softDeletes(); // Data tamu tidak dihapus permanen demi histori/audit
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};