<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom catatan/keterangan pembayaran (mis. charge tambah jam)
     * plus type "charge" untuk biaya tambahan di luar tarif kamar.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('type');
        });

        DB::statement("ALTER TABLE payments MODIFY COLUMN type ENUM('dp', 'pelunasan', 'refund', 'charge') NOT NULL DEFAULT 'dp'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN type ENUM('dp', 'pelunasan', 'refund') NOT NULL DEFAULT 'dp'");

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};