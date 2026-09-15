# TASK.md — Alur Pengerjaan Project

> Checklist ini adalah urutan kerja yang harus diikuti agent dari awal sampai project live. Kerjakan **berurutan per fase** — jangan loncat ke fase deployment sebelum fase development selesai dan diverifikasi. Centang (ganti `[ ]` jadi `[x]`) setiap item selesai, supaya progress selalu terlihat jelas kalau sesi kerja terputus.

**Baca dulu sebelum mulai:** `prd.md`, `structure.md`, `../AGENTS.md`, `security.md` — file ini mengasumsikan kamu sudah paham isinya.

---

## Fase 0 — Setup Lingkungan Lokal

- [x] Install Laragon, pastikan versi PHP ≥ 8.2 aktif (cek lewat Laragon menu PHP version).
- [x] Buat database MySQL lokal lewat HeidiSQL/phpMyAdmin bawaan Laragon, beri nama sesuai project (mis. `hotel_management`).
- [x] Clone/copy project Laravel yang sudah ada ke folder `www` Laragon.
- [x] `composer install`.
- [x] Copy `.env.example` ke `.env`, isi `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` sesuai setup lokal.
- [x] `php artisan key:generate`.
- [x] `php artisan migrate` — pastikan semua 9 tabel berhasil dibuat tanpa error foreign key.
- [x] `php artisan db:seed` — pastikan 3 akun contoh (owner, resepsionis, room_keeper) dan data kamar dummy berhasil masuk.
- [x] Daftarkan middleware `role` di `bootstrap/app.php` (Laravel 11+) — lihat `app/Http/Middleware/README_REGISTER_MIDDLEWARE.md`.
- [x] Setup disk `private` di `config/filesystems.php` untuk foto KTP — lihat `config/README_FILESYSTEM_PRIVATE_DISK.md`.
- [x] `php artisan serve` atau akses lewat domain virtual Laragon, pastikan halaman login muncul.

**Verifikasi Fase 0 selesai:** bisa login dengan ketiga akun contoh, masing-masing diarahkan ke halaman default sesuai role-nya.

---

## Fase 1 — Verifikasi Fitur Inti (Manual Testing per Alur)

Jalankan tiap alur ini secara manual di browser, sebagai owner/resepsionis/room_keeper sesuai kebutuhan. Catat kalau ada yang error atau perilaku tidak sesuai `prd.md`.

- [ ] **Reservasi**: buat reservasi baru → cek muncul di daftar reservasi dengan status `reserved`.
- [ ] **Cegah bentrok tanggal**: coba reservasi kamar yang sama di tanggal overlap → harus ditolak dengan pesan error, bukan berhasil dobel.
- [ ] **Check-in langsung** (tanpa reservasi dulu): isi form check-in lengkap dengan foto KTP dummy → transaksi berhasil dibuat, status kamar berubah jadi `occupied`.
- [ ] **Check-in dari reservasi**: buka reservasi yang sudah dibuat → klik "Check-in" → form ter-prefill data reservasi → submit berhasil.
- [ ] **Diskon**: coba check-in dengan diskon persen dan diskon nominal → `final_price` terhitung benar di kedua kasus.
- [ ] **Extend**: dari tamu aktif, perpanjang tanggal check-out → `total_price`, `final_price`, `remaining_payment` ter-update benar, dan muncul entri baru di histori extend.
- [ ] **Transfer kamar**: pindahkan tamu aktif ke kamar lain dengan alasan → kamar lama jadi `dirty`, kamar baru jadi `occupied`, tercatat di histori transfer.
- [ ] **Tambah pembayaran**: tambahkan pembayaran cicilan ke transaksi aktif → `remaining_payment` berkurang sesuai jumlah yang dibayar.
- [ ] **Check-out**: selesaikan check-out dengan sisa pembayaran > 0 → sisa tercatat sebagai payment `pelunasan`, status jadi `checked_out`, kamar jadi `dirty`.
- [ ] **Room Keeper**: login sebagai room_keeper → ubah status kamar dari `dirty` ke bersih → status kamar jadi `available`, tercatat di `room_logs`.
- [ ] **Pelanggan**: cek detail pelanggan menampilkan histori transaksi dengan benar; coba ubah `rating_status` ke `blacklisted` → tersimpan.
- [ ] **Dashboard & Laporan**: sebagai owner, cek angka statistik dashboard dan laporan sesuai data yang sudah dibuat di langkah-langkah sebelumnya.
- [ ] **Proteksi role**: coba akses route owner-only (mis. `/reports`) saat login sebagai resepsionis/room_keeper → harus dapat 403, bukan berhasil masuk.

**Verifikasi Fase 1 selesai:** semua alur di atas berjalan tanpa error, dan tidak ada role yang bisa akses halaman di luar wewenangnya.

---

## Fase 2 — Hardening Sebelum Production (WAJIB, Jangan Skip)

Ini bagian yang paling sering dilewatkan tapi **kritikal** karena target hosting tidak punya SSH dan storage cuma 1GB. Lihat `prd.md` §5 dan `security.md` untuk konteks lengkap tiap poin.

- [x] **Kompresi foto KTP**: `intervention/image:^3.11` terpasang. `TransactionController@createCheckIn` sudah resize (`scaleDown(1000)`) + kompresi (`toJpeg(70)`) lalu simpan ke disk `private`. Terverifikasi di kode & uji encode GD jalan. Test upload asli masih menunggu verifikasi browser Fase 1.
- [x] **Cek ulang semua middleware role** di `routes/web.php` — terverifikasi: owner-only (`/dashboard`, `/reports`, `/room-types`, destroy customers/rooms) terpisah; owner+resepsionis untuk kamar/pelanggan/reservasi/transaksi/aktivitas; room_keeper khusus `/room-keeper`; foto bukti kerja (`/room-logs/{id}/photo`) untuk ketiga role. Route `DeployController` **dinonaktifkan** (di-comment) sehingga tidak ada route tanpa `auth`/`role`.
- [x] **Ganti password seeder default** — buat `database/seeders/ProductionSeeder.php` yang ambil kredensial dari `.env` (`PROD_*`), idempotent, JANGAN pakai `DatabaseSeeder` di production (masih password contoh "password").
- [x] **Siapkan `DeployController`** untuk migration tanpa SSH (lihat `structure.md` §4.2 Cara B) — `POST /deploy/{token}`, token dari config `app.deploy_token` (env `DEPLOY_TOKEN`), `hash_equals` anti timing-attack, opsi `?seed=1` untuk `ProductionSeeder`. **Route-nya DINONAKTIFKAN secara default** di `routes/web.php` (di-comment) untuk mencegah lubang keamanan `security.md` §1.1. Aktifkan sementara hanya saat butuh migrate, lalu NONAKTIFKAN lagi.
- [~] **Set `.env` production**: `APP_DEBUG=false`, `APP_ENV=production`, isi kredensial DB production — **belum selesai**, menunggu Fase 3 (beli hosting & buat DB). Template placeholder `DEPLOY_TOKEN` & `PROD_*` sudah ada di `.env.example`. Template log sudah dibatasi: `LOG_STACK=daily` + `LOG_LEVEL=warning` (cegah `laravel.log` menumpuk habiskan storage 1GB).
- [x] **Cek folder upload foto KTP** — foto KTP & foto bukti kerja disimpan di disk `private` (`storage/app/private`, di luar `public/`), jadi tidak bisa kena *directory-listing* publik. Ditambah lapisan pertahanan: `.htaccess` (`Options -Indexes` + `Require all denied`) & `index.html` di `storage/app/private`, `id-cards/`, dan `room-proofs/`. Tetap verifikasi document root mengarah ke `public/` saat deploy (Fase 4).

**Verifikasi Fase 2 selesai:** checklist keamanan di `security.md` §9 semuanya bisa dicentang.

---

## Fase 3 — Persiapan Hosting & Domain

- [ ] Client mendaftarkan akun Rumahweb **menggunakan data client sendiri** (email, HP, metode pembayaran) — lihat `prd.md` §7 soal kepemilikan akun.
- [ ] Beli paket **Entry Hosting** (1 tahun) + domain **`.my.id`** sesuai nama yang disepakati.
- [ ] Setelah aktif, developer minta akses cPanel dari client (bukan bikinkan akun baru — tetap satu akun milik client).
- [ ] Cek versi PHP di cPanel, set ke PHP 8.2 atau lebih baru kalau belum default.
- [ ] Buat database MySQL production lewat cPanel, catat nama database/user/password untuk `.env`.

**Verifikasi Fase 3 selesai:** cPanel bisa diakses, database production sudah dibuat, domain sudah aktif dan mengarah ke hosting.

---

## Fase 4 — Deploy ke Production

Ikuti detail lengkap di `structure.md` §4.2. Ringkasan urutan:

- [ ] `composer install --no-dev --optimize-autoloader` di lokal.
- [ ] Pastikan `.env` production sudah lengkap terisi (lihat Fase 2).
- [ ] Upload seluruh project (termasuk `vendor/`) lewat File Manager/FTP ke folder di luar `public_html` (mis. `~/app/`).
- [ ] Set document root domain ke `~/app/public/` lewat menu Domains di cPanel.
- [ ] Jalankan migration: import SQL manual lewat phpMyAdmin, **atau** akses route `DeployController` sekali, lalu **langsung hapus/nonaktifkan route tersebut dan re-upload tanpa route itu**.
- [ ] Jalankan seeder minimal untuk akun owner asli client (bukan seeder dummy development).
- [ ] Cek SSL aktif (biasanya otomatis dari Rumahweb, verifikasi `https://` jalan tanpa warning).

**Verifikasi Fase 4 selesai — tes ini WAJIB sebelum bilang "sudah live":**

- [ ] Akses `https://domain.my.id/.env` → harus 404.
- [ ] Akses `https://domain.my.id/` → muncul halaman login, bukan listing folder.
- [ ] Login dengan akun owner asli client → berhasil.
- [ ] Coba satu alur penuh (check-in dummy → check-out) di server production → berjalan tanpa error.
- [ ] Route `DeployController` sudah dipastikan tidak lagi bisa diakses (cek manual, jangan cuma asumsi sudah dihapus).

---

## Fase 5 — Serah Terima ke Client

- [ ] Aktifkan **Daily Backup** addon kalau budget masih cukup; kalau tidak, dokumentasikan proses backup manual (export phpMyAdmin) dan jadwalkan reminder.
- [ ] Aktifkan **auto-renewal** di akun hosting client untuk domain & hosting.
- [ ] Beri training singkat ke resepsionis & room keeper cara pakai aplikasi (alur check-in, extend, transfer, update status kamar).
- [ ] Serahkan kredensial akun owner ke client, minta client segera ganti password default.
- [ ] Setup monitoring uptime gratis (UptimeRobot/Better Uptime) dengan notifikasi ke WhatsApp/Telegram/email developer & client.
- [ ] Sepakati pembagian tanggung jawab pasca-launch (siapa handle bug, siapa bayar renewal, siapa pegang akses cPanel) — tuliskan tertulis, jangan cuma lisan.

**Verifikasi Fase 5 selesai:** client bisa login dan pakai aplikasi sendiri, tahu siapa harus dihubungi kalau ada masalah, dan tahu kapan hosting mereka harus diperpanjang.

---

## Fase 6 — Pasca-Launch (Ongoing, Bukan Sekali Jalan)

Bukan checklist sekali centang, tapi pengingat berkala:

- [ ] Pantau penggunaan storage hosting tiap beberapa bulan (via cPanel) — pastikan tidak mendekati limit 1GB.
- [ ] Cek notifikasi monitoring uptime — kalau ada downtime, investigasi penyebabnya (biasanya bukan salah kode kalau hosting shared, tapi tetap perlu dicek).
- [ ] Reminder renewal domain & hosting ke client menjelang tanggal jatuh tempo (meski auto-renewal aktif, tetap baik untuk konfirmasi kartu/saldo client masih valid).
- [ ] Backlog fitur dari `structure.md` §6 (form edit RoomType/Room, observer visit_count, dll) — kerjakan sesuai prioritas dan permintaan user, bukan sekaligus di awal.

---

## Catatan Umum untuk Agent

- Kalau menemukan sesuatu yang tidak sesuai dengan `prd.md`/`structure.md`/`security.md` di tengah pengerjaan (misal ternyata butuh fitur yang butuh SSH), **berhenti, laporkan ke user**, jangan diam-diam improvisasi keluar dari batasan yang sudah disepakati.
- Fase 2 (Hardening) **tidak boleh dilewati** meski terasa "belum penting" — dua-duanya (kompresi foto & keamanan route migration) baru terasa masalahnya setelah aplikasi sudah dipakai nyata, dan saat itu sudah lebih sulit diperbaiki.
- Update checklist ini (centang item yang selesai) setiap kali menyelesaikan pekerjaan, supaya kalau sesi kerja dengan agent terputus, progress tetap jelas terlihat dari file ini.
