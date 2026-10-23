<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_index', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40);
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('record_count')->default(0);
            $table->unsignedBigInteger('first_record_id')->nullable();
            $table->unsignedBigInteger('last_record_id')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status', 20)->default('ready');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['category', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_index');
    }
};
