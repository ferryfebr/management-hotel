<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biaya keterlambatan tidak lagi dihitung otomatis.
     * Keterlambatan check-out kini diinput manual oleh owner/resepsionis
     * lewat charge atau perpanjangan masa inap.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('late_fee');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('late_fee', 12, 2)->default(0)->after('remaining_payment');
        });
    }
};