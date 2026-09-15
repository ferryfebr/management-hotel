<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mencatat siapa (resepsionis) yang melakukan transfer kamar,
     * agar aktivitas "pindah kamar" terbaca di halaman aktivitas pekerja.
     * Nullable karena data transfer lama (yang sudah ada) tidak punya pencatat.
     */
    public function up(): void
    {
        Schema::table('room_transfers', function (Blueprint $table) {
            $table->foreignId('transferred_by')
                ->nullable()
                ->after('reason')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('transferred_by');
        });
    }

    public function down(): void
    {
        Schema::table('room_transfers', function (Blueprint $table) {
            $table->dropForeign(['transferred_by']);
            $table->dropIndex(['transferred_by']);
            $table->dropColumn('transferred_by');
        });
    }
};