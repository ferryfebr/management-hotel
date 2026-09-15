<?php

namespace Database\Seeders;

use App\Models\RoomType;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun awal per role — DEVELOPMENT ONLY. Password di sini ('password') tidak aman.
        // Untuk production pakai ProductionSeeder yang ambil kredensial dari .env (PROD_*).
        User::create([
            'name' => 'Owner',
            'email' => 'owner@hotel.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_OWNER,
        ]);

        User::create([
            'name' => 'Resepsionis',
            'email' => 'resepsionis@hotel.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RESEPSIONIS,
        ]);

        User::create([
            'name' => 'Room Keeper',
            'email' => 'roomkeeper@hotel.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ROOM_KEEPER,
        ]);

        // Contoh data jenis kamar & kamar supaya aplikasi langsung bisa dicoba.
        $standard = RoomType::create([
            'name' => 'Standard',
            'price' => 150000,
            'description' => 'Kamar standar dengan kipas angin.',
        ]);

        $deluxe = RoomType::create([
            'name' => 'Deluxe',
            'price' => 250000,
            'description' => 'Kamar dengan AC dan TV.',
        ]);

        foreach (range(101, 105) as $number) {
            Room::create([
                'room_type_id' => $standard->id,
                'room_number' => (string) $number,
                'status' => Room::STATUS_AVAILABLE,
            ]);
        }

        foreach (range(201, 203) as $number) {
            Room::create([
                'room_type_id' => $deluxe->id,
                'room_number' => (string) $number,
                'status' => Room::STATUS_AVAILABLE,
            ]);
        }
    }
}