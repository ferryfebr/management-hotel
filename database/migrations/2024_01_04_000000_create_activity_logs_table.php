<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log aktivitas manual (kamar, jenis kamar, akun) yang tidak
     * tersimpan di tabel domain mana pun. Dipakai halaman Aktivitas
     * supaya aksi owner & pekerja semuanya terlihat sinkron.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action');      // mis. "Tambah kamar"
            $table->string('category');    // mis. "kamar", "akun", "jenis_kamar"
            $table->text('description')->nullable(); // detail bebas
            $table->timestamps();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};