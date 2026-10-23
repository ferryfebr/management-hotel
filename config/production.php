<?php

/**
 * Konfigurasi akun production untuk ProductionSeeder.
 *
 * Dibaca lewat config() (bukan env() langsung) supaya tetap benar ketika
 * `php artisan config:cache` dipakai di production — env() akan mengembalikan
 * null di luar file config setelah cache dibuat.
 */
return [
    'super_admin' => [
        'name' => env('PROD_SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('PROD_SUPER_ADMIN_EMAIL'),
        'password' => env('PROD_SUPER_ADMIN_PASSWORD'),
    ],
    'owner' => [
        'name' => env('PROD_OWNER_NAME', 'Owner'),
        'email' => env('PROD_OWNER_EMAIL'),
        'password' => env('PROD_OWNER_PASSWORD'),
    ],
    'resepsionis' => [
        'name' => env('PROD_RESEPSIONIS_NAME', 'Resepsionis'),
        'email' => env('PROD_RESEPSIONIS_EMAIL'),
        'password' => env('PROD_RESEPSIONIS_PASSWORD'),
    ],
    'room_keeper' => [
        'name' => env('PROD_ROOM_KEEPER_NAME', 'Room Keeper'),
        'email' => env('PROD_ROOM_KEEPER_EMAIL'),
        'password' => env('PROD_ROOM_KEEPER_PASSWORD'),
    ],
];