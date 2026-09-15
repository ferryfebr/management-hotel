# Struktur Project — Sistem Manajemen Hotel (Laravel)

> **v2** — direvisi setelah keputusan hosting final (shared hosting tanpa SSH). Ikuti pola yang sudah ada — jangan buat struktur baru yang menyimpang tanpa alasan kuat.

## 1. Struktur Folder

```
app/
├── Models/
│   ├── User.php              # role: owner | resepsionis | room_keeper
│   ├── RoomType.php
│   ├── Room.php
│   ├── Customer.php
│   ├── Transaction.php       # inti alur sewa
│   ├── Payment.php           # rincian tiap pembayaran (DP, pelunasan, refund)
│   ├── RoomTransfer.php      # histori pindah kamar
│   ├── StayExtension.php     # histori perpanjangan menginap
│   └── RoomLog.php           # histori laporan kebersihan/kerusakan
│
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── RoomTypeController.php
│   │   ├── RoomController.php
│   │   ├── CustomerController.php
│   │   ├── ReservationController.php
│   │   ├── TransactionController.php # CORE: checkin, extend, transfer, payment, checkout
│   │   ├── RoomKeeperController.php
│   │   ├── ReportController.php
│   │   └── DeployController.php      # KHUSUS deploy tanpa SSH, lihat §4 — HAPUS/nonaktifkan setelah dipakai
│   └── Middleware/
│       └── EnsureUserHasRole.php     # middleware('role:owner,resepsionis')
│
database/
├── migrations/
└── seeders/
    └── DatabaseSeeder.php    # akun contoh per role + data kamar dummy

resources/views/
├── layouts/app.blade.php
├── auth/login.blade.php
├── dashboard/index.blade.php
├── room-types/index.blade.php
├── rooms/index.blade.php
├── customers/{index,show}.blade.php
├── reservations/{index,create}.blade.php
├── transactions/{checkin,active}.blade.php
├── room-keeper/index.blade.php
└── reports/index.blade.php

routes/web.php
```

## 2. Konvensi yang Dipakai

- **Controller**: satu controller per resource utama. `TransactionController` sengaja jadi "fat controller" untuk alur sewa karena logikanya saling terkait erat, dibungkus `DB::transaction()`.
- **Validasi**: inline `$request->validate()` di controller demi keringkasan MVP.
- **Enum status**: selalu pakai constant Model (`Room::STATUS_AVAILABLE`, dst), jangan hardcode string.
- **Snapshot harga**: `transactions.room_price_per_night` adalah salinan harga saat transaksi dibuat.
- **Soft delete**: `Customer` dan `Transaction` pakai `SoftDeletes`.
- **Tidak ada queue/job apa pun** — hosting production tidak mendukungnya (lihat `prd.md` FR-8). Kalau butuh proses "background-like" (misal kirim notifikasi), lakukan sinkron dalam request atau lewat cron cPanel (lihat §4.3), bukan `artisan queue:work`.

## 3. Skema Database (urutan migration penting karena foreign key)

1. `add_role_to_users_table`
2. `create_room_types_table`
3. `create_rooms_table`
4. `create_customers_table`
5. `create_transactions_table`
6. `create_payments_table`
7. `create_room_transfers_table`
8. `create_stay_extensions_table`
9. `create_room_logs_table`

Relasi penting: `Transaction belongsTo Customer, Room, User` dan `hasMany Payment, RoomTransfer, StayExtension`. `Transaction::totalPaid()` = SUM dari `payments`, bukan kolom `down_payment` saja. `Room::availableBetween()` wajib dipakai sebelum membuat reservasi/check-in baru.

## 4. Deployment — Shared Hosting TANPA SSH (Rumahweb Entry)

**Ini bagian paling berbeda dari project Laravel pada umumnya — baca sebelum menyiapkan deployment apa pun.**

### 4.1 Kenapa tidak bisa deploy dengan cara biasa

Paket hosting (Rumahweb Entry) **tidak menyediakan akses SSH**. Artinya `composer install`, `php artisan migrate`, `git pull`, dan semua command Artisan **tidak bisa dijalankan langsung di server**. Semua proses yang butuh command line harus dikerjakan **di komputer lokal**, lalu hasil jadinya di-upload.

### 4.2 Alur deploy yang benar

1. **Build sepenuhnya di lokal** (Laragon):
   ```
   composer install --no-dev --optimize-autoloader
   npm run build   # kalau ada asset build
   ```
2. **Isi `.env` production** sebelum upload — jangan upload `.env.example` lalu edit di server (tidak ada editor server yang nyaman tanpa SSH; File Manager cPanel bisa dipakai tapi rawan salah encoding).
3. **Upload semua file** (termasuk folder `vendor/` hasil composer install) lewat **File Manager cPanel atau FTP/FileZilla** ke folder di luar `public_html`, misal `~/app/`.
4. **Document root harus diarahkan ke `~/app/public/`** (folder `public/` Laravel), **bukan** ke root project. Ini di-setting lewat menu "Domains"/"Addon Domains" di cPanel. **Wajib dicek** — kalau salah, file `.env` bisa ke-expose lewat browser (lihat `security.md`).
5. **Migration database**: karena tidak ada `artisan migrate` via SSH, gunakan salah satu dari dua cara:
   - **Cara A (disarankan)**: import struktur tabel manual lewat **phpMyAdmin** (export `.sql` dari migration lokal, atau tulis SQL manual berdasarkan skema di §3).
   - **Cara B**: buat route sementara `DeployController@migrate` yang memanggil `Artisan::call('migrate')`, dilindungi token rahasia di query string, **lalu WAJIB dihapus/dinonaktifkan setelah dipakai** — lihat `security.md` untuk kenapa ini kritikal.
6. **Setelah live**: cek `.env` punya `APP_DEBUG=false` dan `APP_ENV=production` — kalau tidak, error Laravel akan menampilkan detail sensitif (path server, query SQL) ke publik.

### 4.3 Cron job tanpa SSH

cPanel biasanya tetap menyediakan menu **"Cron Jobs"** terpisah dari akses shell — kalau ke depan butuh scheduler Laravel, bisa daftarkan lewat menu itu:
```
* * * * * /usr/local/bin/php /home/USERNAME/app/artisan schedule:run >> /dev/null 2>&1
```
Saat ini **belum dibutuhkan** karena tidak ada fitur terjadwal di scope MVP.

### 4.4 Update kode setelah live

Setiap ada perubahan kode: ulangi proses build lokal → upload file yang berubah saja (jangan upload ulang `vendor/` kalau dependency tidak berubah, untuk hemat waktu). **Tidak ada CI/CD otomatis** di setup ini — semua manual, ini trade-off yang sudah disepakati demi menekan biaya hosting.

## 5. Batasan Resource Hosting (Rumahweb Entry)

| Resource | Batas | Implikasi untuk kode |
|---|---|---|
| Storage | 1GB total | Foto KTP **wajib** dikompresi (lihat `prd.md` §5.1) |
| SSH | Tidak ada | Semua deploy manual (lihat §4) |
| Queue/background job | Tidak didukung | Jangan pakai `artisan queue:work` |
| Node.js/Redis | Tidak ada | Tidak relevan untuk stack Blade kita |

## 6. Belum Dibuat / TODO Lanjutan

- Form edit UI untuk `RoomType` dan `Room`.
- Observer untuk `Customer` agar `visit_count` ter-update otomatis.
- **Kompresi gambar KTP otomatis** (`Intervention Image` atau setara) — **prioritas tinggi, kerjakan sebelum go-live**, lihat `task.md`.
- `DeployController` sementara untuk migration tanpa SSH — buat, pakai sekali, lalu hapus (lihat §4.2 Cara B).
- Export laporan ke PDF/Excel — fase 2, tunda sampai ada kebutuhan nyata (mengingat storage terbatas).
- Testing otomatis — belum ada sama sekali (lihat `../AGENTS.md` §4).

## 7. File Referensi Lain

- `prd.md` — kebutuhan fungsional & alur bisnis, termasuk keputusan stack final.
- `../AGENTS.md` — cara kerja & batasan untuk AI coding agent di project ini.
- `security.md` — requirement keamanan wajib, termasuk risiko spesifik deploy tanpa SSH.
- `task.md` — alur kerja/tahapan pengerjaan step-by-step.
