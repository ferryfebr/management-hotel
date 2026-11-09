<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cegah jenis kamar duplikat di level database. Sebelum index dibuat,
     * duplikat lama (akibat double submit) digabung: kamar milik duplikat
     * dipindahkan ke baris pertama, lalu baris duplikat dihapus.
     */
    public function up(): void
    {
        $seen = [];

        foreach (DB::table('room_types')->orderBy('id')->get(['id', 'name']) as $type) {
            $key = mb_strtolower(trim((string) $type->name));

            if (! isset($seen[$key])) {
                $seen[$key] = $type->id;

                continue;
            }

            $keepId = $seen[$key];

            DB::table('rooms')
                ->where('room_type_id', $type->id)
                ->update(['room_type_id' => $keepId]);

            DB::table('room_types')->where('id', $type->id)->delete();
        }

        Schema::table('room_types', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
