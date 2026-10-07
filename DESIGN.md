# DESIGN.md

Panduan desain UI/UX untuk BengkelOS. Dokumen ini mengikat — kalau ada bagian UI yang dibangun menyimpang dari sini (warna acak, spacing tidak konsisten, tombol tidak jelas fungsinya), itu bug desain, bukan variasi yang sah. Baca ini sebelum membangun/mengubah tampilan apa pun.

---

## 0. Filosofi Desain

Sistem ini dipakai di lantai bengkel yang sibuk — kasir melayani pelanggan sambil ada motor mengantre, mekanik lihat sekilas dari kejauhan, owner cek laporan sambil buru-buru. **Kejelasan mengalahkan keindahan.** Tidak ada ruang untuk elemen dekoratif yang tidak membantu orang menyelesaikan tugasnya lebih cepat.

Identitas visual diambil dari dunia bengkel itu sendiri: **hitam-kuning** adalah warna universal untuk "perhatian, area kerja, alat berat" — dipakai di safety tape, toolbox, rambu bengkel. Ini bukan sekadar preferensi warna, tapi bahasa visual yang sudah dikenali secara insting oleh siapa pun yang pernah ke bengkel. Putih jadi ruang bernapas supaya tetap nyaman dilihat berjam-jam, bukan dominan hitam-kuning di semua tempat.

---

## 1. Palet Warna

| Token | Hex | Peran |
|---|---|---|
| `--ink` | `#0A0A0A` | Teks utama, elemen berat (sidebar, header) |
| `--paper` | `#FFFFFF` | Latar utama, ruang napas |
| `--paper-dim` | `#F2F1ED` | Latar sekunder (card, section pembeda), bukan putih murni supaya ada kedalaman tanpa bayangan berlebihan |
| `--signal` | `#FFC800` | **Satu-satunya warna aksen** — dipakai untuk aksi utama & penanda penting saja, bukan dekorasi |
| `--line` | `#D8D6CE` | Border/garis pembatas halus |
| `--ink-soft` | `#5C5A52` | Teks sekunder/label, bukan abu-abu generik — warna hangat senada dengan `--paper-dim` |

**Pengecualian yang disengaja — 2 warna fungsional:**
Untuk status kritis (stok habis, transaksi gagal, servis selesai/lunas), sistem **tetap butuh 2 warna semantik** di luar hitam-putih-kuning:
| Token | Hex | Kapan Dipakai |
|---|---|---|
| `--danger` | `#C4342B` | HANYA untuk kondisi kritis: stok habis, gagal bayar, peringatan keras |
| `--success` | `#2E7D46` | HANYA untuk konfirmasi selesai: lunas, servis selesai, berhasil tersimpan |

**Kenapa pengecualian ini perlu:** kalau semua status (baik/buruk/netral) sama-sama pakai kuning-hitam-putih, kasir yang buru-buru bisa salah baca — apakah kuning ini artinya "perhatian ada masalah" atau "ini tombol biasa"? Warna semantik universal (merah=masalah, hijau=beres) justru **mengurangi kebingungan**, bukan menambah. Dua warna ini dipakai sangat terbatas — hanya untuk teks status/badge kecil, TIDAK untuk tombol besar atau elemen dekoratif.

**Aturan pemakaian `--signal` (kuning):** kuning cuma boleh muncul di (1) tombol aksi utama/primary CTA, (2) indikator tab/menu aktif, (3) badge angka penting (misal jumlah motor antre). Kalau kuning dipakai di lebih dari itu — background section, border semua card, dll — auditnya gagal, karena kuning kehilangan kekuatan "menarik perhatian" kalau dipakai di mana-mana.

---

## 2. Tipografi

Satu keluarga font: **Barlow** (dan varian **Barlow Condensed** untuk tabel padat). Barlow dipilih bukan default generik — bentuknya terinspirasi dari plat nomor & rambu jalan Amerika, punya karakter "industrial/signage" yang pas untuk konteks bengkel, sekaligus sangat legible di ukuran kecil untuk tabel transaksi.

| Elemen | Font & Ukuran | Weight |
|---|---|---|
| Judul halaman (H1) | Barlow, 28px | 700 (Bold) |
| Judul section (H2) | Barlow, 20px | 600 (SemiBold) |
| Body/teks form | Barlow, 16px | 400 (Regular) |
| Label kecil/caption | Barlow, 13px | 500 (Medium) — **bukan huruf kapital semua**, cukup medium weight untuk beda dari body |
| Angka besar (harga, total, komisi) | Barlow Condensed, 32-40px, tabular numerals | 700 (Bold) |
| Tabel padat (daftar produk, riwayat) | Barlow Condensed, 14px | 400-500 |

**Aturan angka uang:** semua nominal Rupiah pakai **tabular numerals** (angka rata kanan, lebar digit sama) supaya kolom harga di tabel sejajar rapi, gampang dibandingkan sekilas — penting banget untuk laporan omset & komisi.

---

## 3. Spacing & Grid

Skala spacing kelipatan 4px: `4 / 8 / 12 / 16 / 24 / 32 / 48 / 64`. Tidak ada angka di luar skala ini (hindari `padding: 15px` sembarangan).

- **Touch target minimum 44x44px** — ini bukan saran, ini syarat, karena layar kasir dipakai jari langsung sepanjang hari, bukan mouse presisi.
- Card/section pakai radius **6px**, bukan 16-24px yang terlalu "lembut/SaaS generik" — sudut yang lebih tegas cocok sama nuansa industrial, dan beda dari kesan aplikasi konsumen kasual.
- Border halus (`--line`, 1px) lebih diutamakan daripada shadow lembut untuk membedakan card — shadow dipakai sangat minim, cuma untuk elemen yang benar "mengambang" (modal, dropdown).

---

## 4. Komponen

### Tombol
| Jenis | Tampilan | Kapan Dipakai |
|---|---|---|
| **Primary** | Latar `--signal` kuning penuh, teks `--ink` hitam tebal | SATU per layar — aksi utama yang ingin didorong ("Bayar & Cetak Struk", "Simpan Motor Masuk") |
| **Secondary** | Latar putih, border `--ink` 1.5px, teks `--ink` | Aksi kedua ("Batal", "Kembali") |
| **Text/ghost** | Tanpa latar, teks `--ink-soft` | Aksi minor (link, "Lihat Detail") |
| **Danger** | Border/teks `--danger`, latar putih | Sangat jarang — hanya untuk aksi yang benar-benar butuh perhatian ekstra (bukan tombol hapus data, karena sistem ini append-only) |

**Aturan penting:** jangan ada 2 tombol kuning bersebelahan dalam satu layar — itu bikin bingung mana yang benar-benar "aksi utama". Kalau ada beberapa aksi, cuma satu yang paling penting yang kuning, sisanya secondary/text.

### Status Badge
Selalu kombinasi **warna + ikon + teks** — tidak pernah warna saja (supaya tidak bergantung ke penglihatan warna orang):
```
🟡 Antre     🔧 Dikerjakan     ✅ Selesai
```
```
⚠️ Stok Menipis     ⛔ Stok Habis     ✅ Lunas     ⏳ Belum Bayar
```

### Tabel
- Header tabel: latar `--paper-dim`, teks `--ink` bold, TIDAK pakai huruf kapital semua
- Baris selang-seling: putih polos (tidak perlu warna beda per baris — cukup border tipis antar baris)
- Kolom angka (harga, stok, komisi) rata kanan, tabular numerals
- Baris bisa di-tap seluruhnya (bukan cuma ikon kecil) untuk buka detail — target sentuh besar

### Kartu Produk/Jasa (POS)
- Kotak persegi dengan border 1px `--line`, radius 6px
- Nama produk bold, harga di bawahnya pakai Barlow Condensed
- State "sudah ada di keranjang": border berubah jadi `--signal` kuning 2px — bukan seluruh background diwarnai (supaya teks tetap gampang dibaca)

---

## 5. Layout per Layar Kunci

### Login
```
┌─────────────────────────────┐
│                               │
│      [Logo BengkelOS]        │
│                               │
│    ┌───────────────────┐    │
│    │ Username            │    │
│    ├───────────────────┤    │
│    │ Password            │    │
│    └───────────────────┘    │
│    ┌───────────────────┐    │
│    │   MASUK (kuning)    │    │
│    └───────────────────┘    │
│                               │
└─────────────────────────────┘
```
Center-aligned, latar putih polos, satu aksen garis kuning tipis di atas card login — bukan gradient atau ilustrasi berlebihan.

### POS Kasir (layar utama, paling sering dipakai)
```
┌──────────────────────────────┬─────────────────┐
│ [Tab: Produk | Jasa]  🔍 cari  │ KERANJANG         │
│ ┌────┐ ┌────┐ ┌────┐ ┌────┐  │ Oli 1L      x1     │
│ │item│ │item│ │item│ │item│  │ Servis      x1     │
│ └────┘ └────┘ └────┴────┘   │ ─────────────────  │
│ ┌────┐ ┌────┐ ┌────┐ ┌────┐  │ Total: Rp150.000    │
│ │item│ │item│ │item│ │item│  │                    │
│ └────┘ └────┘ └────┴────┘   │ [Tunai] [QRIS]      │
│                                │ ┌─────────────────┐│
│                                │ │BAYAR & CETAK      ││
│                                │ │(kuning, besar)    ││
│                                │ └─────────────────┘│
└──────────────────────────────┴─────────────────┘
```
Split 70/30 kiri-kanan. **Satu tombol kuning** di pojok kanan bawah — selalu di posisi yang sama supaya jempol kasir hafal lokasinya tanpa lihat.

### Dashboard Owner
```
┌────────────────────────────────────────────┐
│ Omset Kotor │ Omset Bersih │ Transaksi │ Antre│
│  (4 kartu angka besar berjajar, netral B&W)  │
├────────────────────────────────────────────┤
│ Grafik Omset 7 Hari (garis hitam+kuning)      │
├────────────────────────────────────────────┤
│ Performa Mekanik (bar chart horizontal)       │
└────────────────────────────────────────────┘
```
Kuning cuma dipakai di satu garis grafik (pembanding), bukan di semua elemen — supaya data yang penting yang menonjol, bukan chrome-nya.

### Layar Antrean Mekanik (TV/tablet, dilihat dari jarak)
```
┌──────────────────────────────────────────┐
│ BengkelOS                          14:32   │
├───────────┬────────────┬─────────────────┤
│  ANTRE    │  DIKERJAKAN │  SELESAI HARI INI│
│           │             │                 │
│ AB 1234 XY│ AB 5678 CD  │ AB 9999 ZZ       │
│ Budi      │ Andi        │ Citra            │
│           │ (mekanik)   │                 │
└───────────┴────────────┴─────────────────┘
```
Latar `--ink` HITAM PENUH (bukan putih) — kebalikan dari layar lain, karena ini dilihat dari jarak beberapa meter, kontras tinggi teks putih/kuning di atas hitam paling gampang terbaca sekilas. Ini SATU-SATUNYA layar yang latar belakangnya gelap; semua layar lain latar putih.

### Komisi Mekanik (mobile)
```
┌─────────────────┐
│ 👤 Andi           │
│                   │
│ Komisi Bulan Ini  │
│ Rp 2.400.000      │
│ (angka besar,     │
│  Barlow Condensed)│
│                   │
│ [Harian|Mingguan|Bulanan]│
│                   │
│ AB 5678 CD  Rp45rb │
│ AB 1111 AA  Rp60rb │
└─────────────────┘
```

---

## 6. Prinsip Supaya Orang Tidak Bingung

1. **Satu aksi utama per layar** — kalau ada 5 tombol sama besar sama pentingnya, pengguna bingung mana yang harus diklik duluan. Selalu ada 1 tombol kuning yang paling jelas.
2. **Posisi konsisten** — tombol utama selalu di tempat yang sama tiap layar sejenis (kanan bawah untuk POS, misalnya). Jangan pindah-pindah posisi supaya orang tidak perlu mencari ulang tiap kali.
3. **Bahasa manusia, bukan bahasa sistem** — tombol bilang "Simpan Motor Masuk", bukan "Submit" atau "Create Record". Status bilang "Menunggu Dikerjakan", bukan "Pending".
4. **Konfirmasi jelas, bukan diam-diam** — setelah aksi penting (bayar, simpan), selalu ada tanda jelas berhasil (bukan cuma layar berpindah tanpa penjelasan).
5. **Kosong = ajakan, bukan kebingungan** — kalau antrean lagi kosong, jangan cuma layar putih kosong, tampilkan pesan "Belum ada motor masuk hari ini" supaya jelas itu bukan error.
6. **Warna selalu dibarengi teks/ikon** — jangan pernah cuma mengandalkan warna untuk menyampaikan status (lihat bagian Status Badge).

---

## 7. Yang Harus Dihindari

- ❌ Kuning dipakai sebagai warna latar section/background besar — kuning cuma untuk aksen kecil yang disengaja
- ❌ Card dengan shadow lembut abu-abu di semua tempat (kesan "template SaaS generik") — pakai border tipis, bukan shadow
- ❌ Label huruf kapital semua (`STATUS TRANSAKSI`) — cukup medium weight, sentence case
- ❌ Radius sudut besar (16px+) di semua elemen — pakai 6px, lebih tegas
- ❌ Lebih dari 1 tombol kuning besar dalam satu layar
- ❌ Animasi hover/fade di semua kartu — gerakan cuma untuk hal yang benar butuh perhatian (konfirmasi, perubahan status), bukan dekorasi di semua elemen
- ❌ Ikon/emoji berlebihan yang tidak fungsional — tiap ikon harus mewakili arti spesifik (🔧 = sedang dikerjakan), bukan hiasan acak

---

## 8. Aksesibilitas (Wajib, Bukan Opsional)

- Kontras teks hitam (`#0A0A0A`) di atas putih/kuning — sudah otomatis tinggi, aman.
- **Kuning + teks putih tidak boleh dipakai** — kontrasnya buruk, selalu kuning + teks hitam.
- Semua elemen interaktif (tombol, tab, baris tabel) harus punya focus state yang terlihat jelas (border/outline `--signal`) untuk navigasi keyboard.
- Ukuran font body minimal 16px, jangan lebih kecil dari itu untuk teks yang dibaca terus-menerus (bukan cuma label).
- Target sentuh minimal 44x44px (sudah disebut di bagian Spacing) — krusial karena ini dipakai di touchscreen kasir.
