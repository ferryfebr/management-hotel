<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biaya keterlambatan check-out (late checkout fee).
     * Diisi 1x room_price_per_night saat tamu check-out lebih dari
     * 3 jam setelah waktu check-out terjadwal. Default 0 (tidak telat).
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('late_fee', 12, 2)->default(0)->after('remaining_payment');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('late_fee');
        });
    }
};