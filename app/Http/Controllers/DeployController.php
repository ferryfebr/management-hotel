<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * KHUSUS deployment tanpa SSH (Rumahweb Entry).
 *
 * Route ini memungkinkan menjalankan `php artisan migrate` (dan opsi seeder)
 * dari browser saat hosting tidak punya akses shell. Lihat docs/structure.md §4.2
 * Cara B dan docs/security.md.
 *
 * ⚠️ WAJIB HAPUS / NONAKTIFKAN controller + route ini SETELAH dipakai sekali.
 * Yang aktif di production berarti attacker bisa menjalankan migration sesuka hati.
 */
class DeployController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $secret = env('DEPLOY_TOKEN');

        // Tanpa token di .env atau token salah -> tolak.
        if (! $secret || ! hash_equals($secret, $token)) {
            abort(403);
        }

        $output = '';

        // 1) Migration schema (--force agar jalan tanpa konfirmasi di production).
        Artisan::call('migrate', ['--force' => true]);
        $output .= "MIGRATE:\n" . Artisan::output();

        // 2) Opsional: seed akun owner/resepsionis production (?seed=1).
        //    Buat ProductionSeeder memakai kredensial dari .env (PROD_*).
        if ($request->boolean('seed')) {
            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\ProductionSeeder',
                '--force' => true,
            ]);
            $output .= "\nSEED:\n" . Artisan::output();
        }

        return response('<pre>' . e($output) . '</pre>')
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}