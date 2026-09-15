# SECURITY.md — Requirement Keamanan

> **v2** — direvisi setelah keputusan hosting final (shared hosting tanpa SSH). Wajib dibaca sebelum mengerjakan apa pun yang menyentuh data pelanggan, pembayaran, autentikasi, atau proses deployment.

## 1. Risiko Baru Spesifik: Deploy Tanpa SSH

Ini bagian **paling kritikal** dan tidak ada di versi sebelumnya — baca dulu sebelum deploy apa pun.

### 1.1 Route migration sementara — WAJIB dihapus

Karena hosting tidak punya SSH, salah satu cara migration database adalah lewat route sementara (`DeployController@migrate` yang memanggil `Artisan::call('migrate')`). **Ini adalah lubang keamanan besar kalau dibiarkan aktif** — siapa pun yang menemukan/menebak URL-nya bisa menjalankan command Artisan sembarangan di server production.

**Aturan wajib:**
- Route ini harus dilindungi token rahasia yang panjang & acak (bukan `?key=admin123`).
- **Setelah migration berhasil dijalankan, route ini HARUS dihapus dari kode atau di-comment total**, lalu upload ulang tanpa route tersebut. Jangan "biarkan saja, toh tokennya rahasia" — itu bukan praktik yang aman untuk production.
- Kalau ke depan butuh migration baru lagi, tambahkan route ini lagi sementara, pakai, lalu hapus lagi. Jangan biarkan permanen.

### 1.2 Document root — WAJIB mengarah ke `public/`

Kalau document root di cPanel salah diarahkan ke root folder Laravel (bukan ke `public/`), maka file `.env` — yang berisi password database, `APP_KEY`, dan kredensial lain — **bisa diakses langsung lewat browser** (`namadomain.com/.env`). Ini salah satu kesalahan paling umum & paling fatal di deploy Laravel ke shared hosting.

**Checklist wajib setelah deploy:**
- [ ] Buka `https://domainmu.my.id/.env` di browser — harus muncul error 404, **bukan** isi file `.env`.
- [ ] Buka `https://domainmu.my.id/` — harus muncul halaman login aplikasi, bukan listing folder Laravel.

### 1.3 `APP_DEBUG` harus `false` di production

Kalau `APP_DEBUG=true` di `.env` production dan terjadi error, Laravel akan menampilkan **stack trace lengkap** (path server, query SQL, bahkan sebagian isi `.env`) ke halaman publik. Pastikan `.env` production selalu:
```
APP_DEBUG=false
APP_ENV=production
```

### 1.4 Tidak ada SSH = tidak ada patching manual di level OS

Ini sisi positifnya: karena tidak ada akses shell, kamu (developer) **tidak bertanggung jawab** atas security patching OS/server — itu tanggung jawab Rumahweb sebagai bagian dari layanan shared hosting. Fokus keamanan project ini murni di **level aplikasi** (poin 1.1–1.3 di atas), bukan di level infrastruktur.

## 2. Data Pribadi (PII) — Foto & Nomor KTP

- Foto KTP idealnya disimpan di luar `public_html` kalau memungkinkan di struktur hosting ini; kalau keterbatasan shared hosting memaksa disimpan di dalam, **pastikan folder tersebut tidak bisa di-*directory-listing*** (tambahkan file `index.html` kosong atau `.htaccess` dengan `Options -Indexes` di folder upload).
- Akses foto KTP tetap **wajib** lewat route ber-middleware `auth` (`CustomerController@idCardPhoto`), bukan link langsung ke file.
- `id_card_number` diberi `unique` index — dipakai untuk deteksi tamu lama, bukan ditampilkan di log/URL/query string.
- **Foto KTP wajib dikompresi** (resize + reduce quality) sebelum disimpan — ini requirement ganda: hemat storage (lihat `prd.md` §5.1) **dan** mengurangi risiko kalau file tersebut ter-ekspos (foto resolusi rendah lebih sulit disalahgunakan dibanding foto resolusi asli kamera HP).

## 3. Otorisasi (Role-Based Access Control)

- Semua proteksi akses **wajib** lewat middleware `role` di `routes/web.php` (`EnsureUserHasRole`), bukan hanya menyembunyikan menu di sidebar Blade.
- Setiap route baru ditempatkan di grup middleware yang sesuai (`role:owner`, `role:owner,resepsionis`, `role:room_keeper`).
- Kalau butuh otorisasi lebih granular, gunakan Laravel Policy/Gate, jangan hardcode kondisi role di controller.

## 4. Autentikasi

- Password di-hash otomatis (`'password' => 'hashed'`) — jangan pernah simpan/log password plaintext.
- **Ganti password default dari `DatabaseSeeder`** sebelum aplikasi dipakai di production.
- Session di-regenerate saat login, diinvalidasi saat logout — pertahankan pola ini.
- Pertimbangkan rate limiting (`throttle`) pada route login untuk cegah brute force — penting karena tidak ada firewall level server yang bisa kamu atur sendiri di shared hosting.

## 5. Integritas Data Keuangan

- `transactions.room_price_per_night` adalah snapshot — jangan hitung ulang dari `room_types.price` untuk transaksi lama.
- `remaining_payment` selalu dihitung ulang dari `SUM(payments)` (`Transaction::totalPaid()`).
- Semua operasi lintas tabel wajib `DB::transaction()`.
- `Transaction` dan `Customer` pakai soft delete.

## 6. Validasi Input & Upload File

- Upload foto KTP divalidasi `image` + batas ukuran — pertahankan, dan tambahkan proses kompresi setelah validasi (lihat §2).
- Semua input numerik (harga, diskon, DP) divalidasi `numeric`/`min:0`.
- Validasi bentrok tanggal (`Room::availableBetween()`) wajib dipanggil sebelum membuat reservasi/check-in.

## 7. Mass Assignment

- Semua model pakai `$fillable` eksplisit — field baru selalu ditambahkan ke `$fillable`, jangan pakai `$guarded = []`.
- Field yang **tidak boleh** mass-assignable dari request langsung: `status` transaksi, `role` user, `visit_count` pelanggan.

## 8. Backup — Tanggung Jawab Ganda (Provider + Manual)

Karena tidak ada SSH untuk setup backup script otomatis sendiri:
- **Kalau addon "Daily Backup" Rumahweb diaktifkan** (rekomendasi kalau budget cukup): itu jadi lapisan utama.
- **Kalau tidak diaktifkan**: developer/owner **wajib** melakukan export database manual berkala lewat phpMyAdmin (mis. mingguan) dan simpan salinannya di luar server (Google Drive, laptop lokal). Jangan andalkan "hosting pasti aman" tanpa backup independen sama sekali.

## 9. Checklist Sebelum Deploy ke Production

- [ ] Password seeder default sudah diganti.
- [ ] `APP_DEBUG=false` dan `APP_ENV=production` di `.env`.
- [ ] Document root mengarah ke folder `public/` (tes akses `/.env` harus 404).
- [ ] Route migration sementara (`DeployController`) sudah dihapus/dinonaktifkan setelah dipakai.
- [ ] Foto KTP sudah melalui proses kompresi sebelum disimpan.
- [ ] Folder upload foto KTP tidak bisa di-*directory-listing*.
- [ ] HTTPS/SSL aktif (Free SSL dari Rumahweb sudah terpasang & terverifikasi).
- [ ] Middleware `role` terpasang di semua route sensitif (`php artisan route:list` — cek manual sebelum upload).
- [ ] Backup terjadwal aktif (addon Rumahweb) **atau** proses backup manual sudah didokumentasikan dan dijalankan minimal sekali sebelum go-live.
- [ ] `.env` tidak ikut ter-upload ke folder yang bisa diakses publik, dan tidak pernah di-commit ke repository manapun.

## 10. Kalau Menemukan Kerentanan Saat Coding

Kalau agent menemukan celah keamanan yang tidak sengaja dibuat sebelumnya (misal route migration ternyata masih aktif, atau `.env` ternyata bisa diakses publik), **perbaiki sebagai prioritas dan laporkan eksplisit ke user** — jangan diam-diam dibiarkan atau dianggap "di luar scope task saat ini".
