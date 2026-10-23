<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index('paid_at');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['status', 'check_out_date']);
        });

        Schema::table('room_logs', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['status', 'check_out_date']);
        });

        Schema::table('room_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
