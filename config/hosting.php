<?php

return [
    // Kuota total paket hosting dalam MB. Angka ini dipakai sebagai pembanding
    // estimasi pemakaian aplikasi, bukan pembacaan resmi dari cPanel.
    'quota_mb' => (int) env('HOSTING_QUOTA_MB', 2048),

    // Ruang aman untuk MySQL temp, log server, dan overhead hosting.
    'reserve_mb' => (int) env('HOSTING_RESERVE_MB', 300),
];
