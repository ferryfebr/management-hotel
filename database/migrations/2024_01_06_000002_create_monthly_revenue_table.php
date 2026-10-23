<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_revenue', function (Blueprint $table) {
            $table->id();
            $table->char('month', 7)->unique(); // YYYY-MM
            $table->decimal('revenue', 14, 2)->default(0);
            $table->unsignedInteger('check_outs')->default(0);
            $table->unsignedInteger('room_nights')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_revenue');
    }
};
