# PRD — Sistem Manajemen Hotel (Guest House)

> **v2** — direvisi setelah keputusan stack & hosting final. Baca dulu sebelum menambah/mengubah fitur — jangan menambah scope di luar dokumen ini tanpa konfirmasi ke pemilik project.

## 0. Keputusan Final (Baca Ini Dulu)

Setelah diskusi teknis & pertimbangan biaya, keputusan yang **sudah final** dan tidak perlu dipertanyakan ulang oleh agent:

| Aspek | Keputusan |
|---|---|
| Backend | Laravel (PHP), Blade (bukan React/Inertia) |
| Database | MySQL |
| Local dev environment | Laragon |
| Hosting production | Shared Hosting **Rumahweb Entry** (Rp25.000/bln, **tanpa SSH**) |
| Domain | `.my.id` (dipilih karena renewal stabil & murah, bukan `.xyz`/`.com`) |
| Kepemilikan akun hosting & billing | **Client** (bukan developer) — lihat §7 |
| Budget hosting+domain client | Maksimal Rp400.000/tahun |

Karena hosting **tidak punya SSH**, semua requirement yang butuh queue worker, cron kompleks, atau command line di server **tidak berlaku** untuk versi ini. Lihat `structure.md` §4 dan `security.md` untuk detail teknis implikasi ini.

## 1. Latar Belakang & Tujuan

Aplikasi manajemen operasional penginapan kecil/guest house berbasis web, menggantikan pencatatan manual (buku/Excel). Tujuan utama:

- Mempercepat proses check-in/check-out tamu.
- Mencatat histori pelanggan (termasuk catatan perilaku & blacklist).
- Melacak status kebersihan/kerusakan kamar secara real-time.
- Memberi Owner visibilitas keuangan dan okupansi tanpa rekap manual.

**Skala target:** 1 lokasi, ±10–30 kamar, **<5 user aktif** (1 owner, 1-2 resepsionis, 1 room keeper). Bukan untuk multi-cabang atau OTA. Skala ini **menentukan batas hosting** — jangan desain fitur yang mengasumsikan traffic/user lebih besar dari ini.

## 2. Peran Pengguna (Roles)

| Role | Deskripsi | Akses Utama |
|---|---|---|
| `owner` | Pemilik/manajer, **pemilik akun hosting** | Semua modul + Dashboard + Laporan + kelola Jenis Kamar |
| `resepsionis` | Petugas front desk | Kamar, Reservasi, Check-in/out, Pelanggan, Tamu Aktif |
| `room_keeper` | Petugas kebersihan | Hanya modul Status Kamar (mobile-friendly) |

Akses dibatasi lewat middleware `role`, bukan cuma disembunyikan dari menu (lihat `security.md`).

## 3. Alur Bisnis Inti (User Journeys)

### 3.1 Reservasi (opsional, sebelum tamu datang)
Resepsionis mencatat pemesanan untuk tanggal tertentu → status `reserved`. Belum perlu KTP/DP. Bisa dibatalkan (`cancelled`) atau ditandai `no_show` jika tamu tidak datang.

### 3.2 Check-in
Tamu datang → resepsionis input data tamu, **wajib upload foto KTP** (dikompresi otomatis, lihat §5.1), pilih kamar & tanggal, opsional diskon (nominal/persen), input DP. Sistem menghitung `final_price` dan `remaining_payment` otomatis. Status kamar berubah jadi `occupied`.

### 3.3 Selama Menginap
- **Perpanjang (extend):** ubah tanggal check-out, sistem hitung tambahan biaya otomatis, tercatat di histori (`stay_extensions`).
- **Pindah kamar:** alasan wajib diisi, tercatat di histori (`room_transfers`), kamar lama jadi `dirty`, kamar baru jadi `occupied`.
- **Tambah pembayaran:** cicilan DP tambahan atau pelunasan sebagian, tercatat per transaksi di `payments`.

### 3.4 Check-out
Sistem hitung sisa tagihan otomatis, catat pembayaran pelunasan terakhir, status transaksi jadi `checked_out`, kamar otomatis jadi `dirty` (menunggu dibersihkan).

### 3.5 Housekeeping
Room keeper melihat daftar kamar (prioritas: dirty → maintenance → occupied → available), update status (bersih/kotor/rusak) lewat HP, opsional catatan kerusakan. Setiap perubahan tercatat sebagai log (`room_logs`) untuk audit.

### 3.6 Manajemen Pelanggan
Owner/resepsionis bisa melihat histori kunjungan tamu, memberi status `regular`/`vip`/`blacklisted`, dan menulis catatan khusus.

### 3.7 Laporan
Owner melihat total pendapatan, jumlah check-out, dan total malam terjual dalam rentang tanggal, dengan rincian per pembayaran.

## 4. Functional Requirements (ringkas)

- FR-1: Sistem mencegah kamar dipesan dobel pada tanggal yang overlap.
- FR-2: Setiap transaksi punya kode unik.
- FR-3: Harga per malam di-*snapshot* saat transaksi dibuat.
- FR-4: Foto KTP wajib pada saat check-in, **dan wajib dikompresi otomatis** sebelum disimpan (lihat §5.1 — kritikal karena storage cuma 1GB).
- FR-5: Semua histori (transfer kamar, extend, pembayaran, log kebersihan) tersimpan permanen untuk audit.
- FR-6: Data pelanggan dan transaksi tidak dihapus permanen (soft delete).
- FR-7: Setiap operasi lintas tabel harus atomik (`DB::transaction()`).
- FR-8 *(baru)*: Aplikasi **tidak boleh bergantung pada queue worker atau proses background** (`artisan queue:work`) karena hosting production tidak mendukungnya. Semua proses harus selesai dalam satu request/response biasa.

## 5. Non-Functional Requirements

### 5.1 Storage — KRITIKAL
Hosting production hanya punya **1GB storage total** (kode + database + semua foto KTP). Ini requirement wajib, bukan opsional:
- Foto KTP **wajib** di-resize (maks lebar 1000px) dan dikompresi (kualitas ~70%) sebelum disimpan — target ukuran akhir 100-300KB per foto, bukan 3-5MB asli dari kamera HP.
- Tanpa ini, storage akan habis dalam hitungan bulan, bukan tahun.

### 5.2 Deployment tanpa SSH
Hosting production **tidak menyediakan akses SSH**. Ini bukan keterbatasan sementara — desain deployment harus mengasumsikan ini permanen. Lihat `structure.md` §4 dan `../AGENTS.md` untuk alur deploy yang sesuai.

### 5.3 Mobile-friendly
Modul Room Keeper harus tetap nyaman dipakai di layar HP (sudah dipenuhi di implementasi saat ini).

### 5.4 Keamanan PII
Data KTP adalah PII sensitif — lihat `security.md` untuk requirement wajib.

## 6. Di Luar Scope (Saat Ini)

- Integrasi pembayaran online / payment gateway otomatis.
- Integrasi OTA (booking.com, Agoda, Traveloka).
- Multi-cabang / multi-tenant.
- Aplikasi mobile native (Flutter, dll) — sudah diputuskan cukup web responsif.
- Queue/background job apa pun (tidak didukung hosting).
- Export laporan ke PDF/Excel (bisa jadi fase 2 kalau storage & budget hosting memungkinkan upgrade).

## 7. Kepemilikan Hosting & Tanggung Jawab

Ini bukan requirement teknis, tapi penting untuk konteks kerja agent:

- **Akun hosting, domain, dan billing tahunan adalah milik client** — bukan developer. Developer hanya mendapat akses cPanel untuk keperluan deploy & maintenance.
- Agent **tidak perlu** membuatkan fitur "billing/subscription" apa pun di dalam aplikasi ini — pembayaran hosting terjadi di luar aplikasi (langsung client ke Rumahweb), bukan bagian dari sistem yang dibangun.
- Auto-renewal di akun hosting client sebaiknya diaktifkan client sendiri — di luar tanggung jawab kode aplikasi.

## 8. Metrik Keberhasilan (opsional, isi sesuai kebutuhan bisnis)

- Waktu proses check-in per tamu berkurang dibanding pencatatan manual.
- Owner bisa mendapat laporan pendapatan bulanan tanpa rekap manual.
- Tidak ada lagi kasus kamar dobel booking.
- Storage hosting tidak pernah mendekati limit 1GB dalam pemakaian normal 1 tahun.

---
**Catatan untuk agent:** Jika ada permintaan fitur baru dari user yang tidak tercakup di sini (mis. multi-cabang, payment gateway, queue/background job), tandai sebagai perubahan scope dan konfirmasi dulu sebelum implementasi besar — terutama kalau fitur itu butuh SSH/queue yang tidak didukung hosting saat ini.
