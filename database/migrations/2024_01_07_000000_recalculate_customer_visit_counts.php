<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Perbaiki data lama: hitung kunjungan dari transaksi aktif/selesai,
     * bukan dari counter yang bisa bertambah dua kali saat reservasi + check-in.
     */
    public function up(): void
    {
        DB::statement(
            "UPDATE customers
             SET visit_count = (
                 SELECT COUNT(DISTINCT transactions.check_in_date)
                 FROM transactions
                 WHERE transactions.customer_id = customers.id
                   AND transactions.status IN ('checked_in', 'checked_out')
                   AND transactions.deleted_at IS NULL
             )"
        );
    }

    public function down(): void
    {
        // Data kunjungan tidak dikembalikan ke nilai lama karena nilai lama salah.
    }
};
