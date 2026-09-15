<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto bukti kerja room keeper (kebersihan/kerusakan kamar).
     * Disimpan di disk private, diakses lewat route ber-middleware auth.
     */
    public function up(): void
    {
        Schema::table('room_logs', function (Blueprint $table) {
            $table->string('proof_photo')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('room_logs', function (Blueprint $table) {
            $table->dropColumn('proof_photo');
        });
    }
};