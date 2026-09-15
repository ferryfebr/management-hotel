<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft-deactivate akun resepsionis/room_keeper oleh owner.
     * Akun nonaktif tidak bisa login, tapi histori aktivitasnya tetap utuh
     * (tidak dihapus) agar audit/aktivitas pekerja tetap terbaca.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};