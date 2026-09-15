<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder KHUSUS production — bikin akun dengan password/kredensial asli dari .env.
 *
 * JANGAN pakai DatabaseSeeder (default) di production; seeder itu pakai password
 * contoh "password" dan data dummy yang cuma untuk development.
 *
 * Cara pakai di production (tanpa SSH):
 *   1. Isi dulu variabel PROD_* di .env production.
 *   2. Import SQL schema manual / akses DeployController lalu jalankan command ini
 *      lewat cara yang tersedia (mis. route deploy khusus), ATAU insert user via phpMyAdmin.
 *
 * Akun hanya dibuat bila email belum ada (idempotent), aman dijalankan berulang.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => env('PROD_OWNER_NAME', 'Owner'),
                'email' => env('PROD_OWNER_EMAIL'),
                'password' => env('PROD_OWNER_PASSWORD'),
                'role' => User::ROLE_OWNER,
            ],
            [
                'name' => env('PROD_RESEPSIONIS_NAME', 'Resepsionis'),
                'email' => env('PROD_RESEPSIONIS_EMAIL'),
                'password' => env('PROD_RESEPSIONIS_PASSWORD'),
                'role' => User::ROLE_RESEPSIONIS,
            ],
            [
                'name' => env('PROD_ROOM_KEEPER_NAME', 'Room Keeper'),
                'email' => env('PROD_ROOM_KEEPER_EMAIL'),
                'password' => env('PROD_ROOM_KEEPER_PASSWORD'),
                'role' => User::ROLE_ROOM_KEEPER,
            ],
        ];

        foreach ($accounts as $account) {
            // Lewati akun yang email/password belum diisi di .env.
            if (empty($account['email']) || empty($account['password'])) {
                continue;
            }

            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'role' => $account['role'],
                ]
            );
        }
    }
}