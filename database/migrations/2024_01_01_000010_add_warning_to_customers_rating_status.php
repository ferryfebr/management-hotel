<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan opsi status 'warning' pada rating pelanggan.
     * Nilai enum: regular, warning, vip, blacklisted.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('rating_status', ['regular', 'warning', 'vip', 'blacklisted'])
                ->default('regular')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('rating_status', ['regular', 'vip', 'blacklisted'])
                ->default('regular')
                ->change();
        });
    }
};