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
                'name' => config('production.owner.name', 'Owner'),
                'email' => config('production.owner.email'),
                'password' => config('production.owner.password'),
                'role' => User::ROLE_OWNER,
            ],
            [
                'name' => config('production.resepsionis.name', 'Resepsionis'),
                'email' => config('production.resepsionis.email'),
                'password' => config('production.resepsionis.password'),
                'role' => User::ROLE_RESEPSIONIS,
            ],
            [
                'name' => config('production.room_keeper.name', 'Room Keeper'),
                'email' => config('production.room_keeper.email'),
                'password' => config('production.room_keeper.password'),
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