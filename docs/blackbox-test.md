# Black Box Test Plan — Management Hotel (Ringkas)

Uji manual lewat browser. Fokus pada alur utama, batas penting, keamanan role, retensi, dan regresi bug.

## Prasyarat

- Environment staging/test, timezone `Asia/Jayapura` (WIT).
- Akun: super_admin, owner, resepsionis, room_keeper, staff nonaktif.
- Data: Standard 101/102 available, 103 dirty, 104 maintenance; Deluxe 201.
- Customer lama: dengan foto KTP, tanpa foto KTP, blacklist.
- Retensi test memakai limit rendah:
  `RETENTION_TRANSACTIONS_MAX=5`, `RETENTION_ROOM_LOGS_MAX=5`, `RETENTION_ACTIVITY_LOGS_MAX=5`, `RETENTION_CUSTOMERS_MAX=5`, `RETENTION_CUSTOMER_EXPORT_REMINDER=3`.

Format status: **PASS / FAIL / BLOCKED**.

---

## 1. Autentikasi & Role

| ID | Langkah | Hasil |
|---|---|---|
| AUTH-01 | Buka `/` tanpa login | Redirect login |
| AUTH-02 | Login super_admin, owner, resepsionis, roomkeeper | Masing-masing masuk `/admin`, `/dashboard`, `/transactions/active`, `/room-keeper` |
| AUTH-03 | Login password salah dan akun nonaktif | Ditolak, toast gagal |
| AUTH-04 | Logout | Redirect login, toast sukses |
| AUTH-05 | Guest buka halaman protected | Redirect login |
| AUTH-06 | Resepsionis buka dashboard/reports/users/room-types | 403 |
| AUTH-07 | Roomkeeper buka halaman front office/owner | 403 |
| AUTH-08 | Owner buka `/admin`; superadmin buka halaman owner + admin | Owner 403; superadmin berhasil |

## 2. Dashboard & Laporan

| ID | Langkah | Hasil |
|---|---|---|
| DASH-01 | Buka dashboard | Statistik kamar, check-in/out hari ini, transaksi terbaru sesuai data |
| DASH-02 | Ganti preset Hari/Minggu/Bulan dan custom date | Nominal revenue berubah sesuai periode |
| DASH-03 | Tambah payment dp/pelunasan vs charge/refund | dp/pelunasan masuk revenue; charge/refund tidak |
| DASH-04 | Buka laporan default/custom | Total pendapatan, check-out, malam terjual sesuai; data arsip tetap masuk ringkasan |

## 3. Jenis Kamar & Kamar

| ID | Langkah | Hasil |
|---|---|---|
| ROOM-01 | Tambah/edit jenis kamar valid | Tersimpan, toast sukses |
| ROOM-02 | Jenis kamar kosong/harga negatif; hapus jenis yang masih punya kamar | Validasi/ditolak |
| ROOM-03 | Tambah/edit kamar valid | Tersimpan, status available |
| ROOM-04 | Nomor kamar duplikat/tipe tidak valid | Validasi error |
| ROOM-05 | Ubah status kamar ke available/oc cupied/dirty/maintenance | Tersimpan + tercatat aktivitas |
| ROOM-06 | Roomkeeper buka halaman Kamar | 403 |
| ROOM-07 | Hapus kamar dengan transaksi/transfer vs tanpa histori | Ditolak vs berhasil |

## 4. Pelanggan

| ID | Langkah | Hasil |
|---|---|---|
| CUST-01 | Buka daftar pelanggan, cari nama/telepon/NIK | Data sesuai, urut kunjungan |
| CUST-02 | Buka detail pelanggan | Data + histori transaksi tampil |
| CUST-03 | Ubah rating/notes valid dan tidak valid | Valid tersimpan; invalid ditolak |
| CUST-04 | Owner hapus pelanggan | Soft delete, hilang dari daftar aktif |
| CUST-05 | Buka foto KTP; pelanggan tanpa foto | Foto tampil lewat login; tanpa foto 404 |
| CUST-06 | Akses file KTP lewat URL storage publik | Ditolak |
| CUST-07 | Autocomplete <2 karakter, valid; cek NIK valid/invalid | Hasil sesuai, maksimal 6, info has_photo |
| CUST-08 | Pengingat export 3 customer; export ZIP; resepsionis export | Zip CSV+KTP; resepsionis 403; export tanpa data baru error |

## 5. Reservasi

| ID | Langkah | Hasil |
|---|---|---|
| RSV-01 | Buat reservasi valid | Berhasil, status reserved, kode `INV-` |
| RSV-02 | Reservasi hari ini pukul 00.17 WIT | Tidak berlabel terlambat |
| RSV-03 | Reservasi kemarin / checkout sebelum check-in / telepon <10 digit | Validasi error |
| RSV-04 | Reservasi overlap vs tanggal bersambung | Overlap ditolak; bersambung diizinkan |
| RSV-05 | Cancel/no-show/edit reservasi | Status dan tampilan sesuai; selain reserved 404 |
| RSV-06 | Konversi reservasi ke check-in | Satu transaksi yang sama, bukan duplikat; kunjungan tetap 1 |

## 6. Check-in

| ID | Langkah | Hasil |
|---|---|---|
| CHK-01 | Check-in valid dengan foto KTP + DP | Transaksi checked_in, kamar occupied |
| CHK-02 | DP 0, diskon nominal/persen, diskon > total | Sisa/final price sesuai; minimum 0 |
| CHK-03 | Customer lama dengan foto KTP, tanpa upload ulang | Berhasil, foto lama dipakai |
| CHK-04 | Customer lama tanpa foto / customer baru tanpa foto | Validasi error foto wajib |
| CHK-05 | Upload foto baru untuk customer lama | Foto baru tersimpan, foto lama dihapus |
| CHK-06 | Nama/telepon/NIK/file/dates/metode tidak valid | Validasi error sesuai field |
| CHK-07 | Kamar unavailable, overlap, reservation_id invalid | Ditolak |
| CHK-08 | Check-in kamera AJAX via HTTPS/Cloudflare | Berhasil; error 419/403/413/500 tampil sesuai, bukan “periksa koneksi” |

## 7. Extend, Transfer, Payment

| ID | Langkah | Hasil |
|---|---|---|
| STAY-01 | Extend tanggal checkout | Tanggal, total, final price, sisa terupdate |
| STAY-02 | Extend bentrok reservasi lain vs batas bersambung | Bentrok ditolak; batas diizinkan |
| STAY-03 | Transfer kamar valid | Kamar lama dirty, baru occupied, histori tercatat |
| STAY-04 | Transfer kamar sama/tidak tersedia/reservasi overlap | Ditolak |
| STAY-05 | Tambah payment DP/pelunasan | Sisa berkurang |
| STAY-06 | Tambah charge dengan catatan | Tagihan/sisa bertambah, notes tersimpan |
| STAY-07 | Payment melebihi sisa / nominal 0 / metode invalid / transaksi selesai | Sisa 0 atau validasi/404 |

## 8. Checkout, Riwayat, Export

| ID | Langkah | Hasil |
|---|---|---|
| OUT-01 | Checkout dengan sisa tagihan + metode | Pelunasan otomatis, status checked_out, kamar dirty |
| OUT-02 | Checkout lunas / dengan charge | Status checked_out, sisa 0, pelunasan dihitung benar |
| OUT-03 | Checkout sisa tagihan tanpa metode / metode invalid / non-active | Validasi/404 |
| OUT-04 | Riwayat transaksi + filter tanggal + detail timeline | Hanya checked_out; timeline lengkap |
| OUT-05 | Export CSV riwayat | CSV terdownload, BOM, filter tanggal bekerja |

## 9. Roomkeeper

| ID | Langkah | Hasil |
|---|---|---|
| KEEP-01 | Login roomkeeper, buka status kamar, cari kamar | Urutan dirty→maintenance→occupied→available; search bekerja |
| KEEP-02 | Set Kotor tanpa foto | Berhasil, toast sukses |
| KEEP-03 | Set Bersih tanpa foto / dengan foto | Tanpa foto error jelas; dengan foto berhasil |
| KEEP-04 | Set Rusak tanpa foto / dengan foto | Tanpa foto error jelas; dengan foto berhasil |
| KEEP-05 | Kirim status occupied | Ditolak |
| KEEP-06 | Buka foto bukti sebagai role berhak / tanpa foto | Tampil / 404 |
| KEEP-07 | Halaman dibiarkan terbuka dan kamera error | Tidak auto-refresh; error sesuai status |

## 10. User, Profil, Aktivitas

| ID | Langkah | Hasil |
|---|---|---|
| USER-01 | Tambah staff valid, duplikat email, role owner, password lemah | Validasi sesuai |
| USER-02 | Edit staff, nonaktifkan, aktifkan, hapus | Perubahan tersimpan; nonaktif tidak bisa login |
| USER-03 | Owner ubah/hapus owner/superadmin | 403 |
| USER-04 | Owner/resepsionis/superadmin lihat aktivitas | Owner & superadmin lihat semua; resepsionis melihat operasional saja |
| USER-05 | Profil: ubah nama/email/password, password salah, email duplikat | Validasi dan penyimpanan sesuai |

## 11. Panel Superadmin, Retensi, Arsip

| ID | Langkah | Hasil |
|---|---|---|
| ADM-01 | Superadmin buka panel | Kuota hosting satu card, rincian pemakaian, limit data tampil |
| ADM-02 | Klik Refresh Pemakaian | Angka update, tidak ada data berubah |
| ADM-03 | Jalankan retensi dengan data melewati limit | Data tertua diarsipkan; data aktif berkurang |
| ADM-04 | Retensi dengan transaksi/customer aktif | Data aktif tidak diarsipkan |
| ADM-05 | Retensi room log/customer/customer export | ZIP berisi CSV + foto terkait; data lama dihapus dari aktif |
| ADM-06 | Cek laporan setelah retensi transaksi | Omzet lama tetap masuk ringkasan bulanan |
| ADM-07 | Arsip: list, download, file hilang, akses owner | Download berhasil; file hilang 404; owner 403 |
| ADM-08 | Backup database | ZIP CSV+manifest terdownload, hanya superadmin |
| ADM-09 | Retensi bersamaan / archive budget kecil / mode darurat | Tidak ada duplikasi; file tertua dirotasi; write diblokir 503, admin cleanup tetap jalan |
| ADM-10 | Zip/folder arsip tidak siap | Toast error spesifik, bukan 500 |

## 12. Keamanan, Cloudflare, UX

| ID | Langkah | Hasil |
|---|---|---|
| SEC-01 | Akses `/.env`, root domain, storage publik | 404/login, tidak ada listing |
| SEC-02 | Kirim POST tanpa CSRF; login 6x gagal | 419; rate limit aktif |
| SEC-03 | Kamera roomkeeper via Cloudflare HTTPS | Fetch tidak mixed content, data tersimpan |
| SEC-04 | Cek setiap aksi sukses/gagal | Toast sukses/gagal tampil |
| SEC-05 | Buka halaman operasional lama | Tidak reload otomatis; draft tidak hilang |

## 13. Regresi Bug

| ID | Langkah | Hasil |
|---|---|---|
| REG-01 | Reservasi hari ini pukul 00.17 WIT | Tidak berlabel terlambat |
| REG-02 | Reservasi + check-in + checkout satu kali | Kunjungan customer = 1 |
| REG-03 | Customer lama dengan/tanpa foto KTP | Punya foto: tidak perlu upload; tanpa foto: wajib upload |
| REG-04 | Checkout sisa tagihan dengan/tanpa metode | Dengan metode: pelunasan otomatis; tanpa metode: validasi error |
| REG-05 | Extend/transfer bentrok reservasi | Ditolak |
| REG-06 | Kode baru, superadmin, roomkeeper status | Prefix `INV-`; superadmin masuk panel; validasi foto jelas |

---

## Ringkasan Eksekusi

| Modul | Jumlah |
|---|---:|
| Autentikasi & Role | 8 |
| Dashboard & Laporan | 4 |
| Jenis Kamar & Kamar | 7 |
| Pelanggan | 8 |
| Reservasi | 6 |
| Check-in | 8 |
| Extend/Transfer/Payment | 7 |
| Checkout/Riwayat/Export | 5 |
| Roomkeeper | 7 |
| User/Profil/Aktivitas | 5 |
| Panel Superadmin/Retensi/Arsip | 10 |
| Keamanan/Cloudflare/UX | 5 |
| Regresi Bug | 6 |
| **Total** | **86** |

Isi kolom PASS/FAIL/BLOCKED saat eksekusi. Setiap FAIL simpan screenshot, URL, role, waktu, dan isi toast/error.
