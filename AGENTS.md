# AGENTS.md

> File ini dibaca otomatis oleh OpenCode (dan agent lain yang ikut konvensi AGENTS.md) setiap masuk ke project ini. Detail lengkap sengaja dipisah ke folder `docs/` supaya file ini tetap ringkas — buka file yang relevan di `docs/` sesuai kebutuhan task yang sedang dikerjakan.

## Tentang Project

Aplikasi **manajemen hotel/guest house** — Laravel (PHP) + MySQL + Blade/Tailwind, dikembangkan di Laragon, deploy ke shared hosting Rumahweb Entry **tanpa SSH**. Skala target: <5 user (owner, resepsionis, room keeper), budget hosting client Rp300-400rb/tahun.

**Stack ini sudah final** — jangan sarankan pindah ke React/Postgres/VPS kecuali user sendiri yang membuka topik itu lagi.

## Baca Dokumen Ini Sesuai Kebutuhan

| Kalau kamu sedang... | Baca |
|---|---|
| Mau paham requirement bisnis & alur user | `docs/prd.md` |
| Mau paham struktur folder, model, migration, cara deploy tanpa SSH | `docs/structure.md` |
| Menyentuh apa pun soal data pelanggan/KTP/pembayaran/deploy | `docs/security.md` |
| Mau lihat kode lengkap yang sudah ada (referensi, bukan sumber utama) | `docs/mvc.md` |
| Mulai kerja atau lanjut dari sesi sebelumnya | `docs/task.md` — ini checklist bertahap, **mulai dari sini** |

## Aturan Paling Kritikal (Ringkasan)

Detail lengkap ada di `docs/security.md` dan `docs/structure.md`, tapi ini yang paling sering terlewat kalau tidak diingatkan:

1. **Tidak ada SSH di hosting production.** Semua `composer install`/`artisan migrate` dikerjakan di lokal, bukan di server. Lihat `docs/structure.md` §4 untuk alur deploy yang benar.
2. **Storage hosting cuma 1GB.** Foto KTP wajib dikompresi (resize + reduce quality) sebelum disimpan — jangan simpan file asli dari upload.
3. **Tidak boleh ada fitur berbasis queue/background job** (`artisan queue:work`) — tidak didukung hosting ini.
4. Kalau ada route sementara untuk migration tanpa SSH (`DeployController`), **wajib dihapus setelah dipakai** — jangan biarkan aktif di production.
5. Document root production harus mengarah ke folder `public/`, bukan root project — kalau salah, `.env` bisa ke-expose publik.

## Cara Kerja

- Ikuti urutan fase di `docs/task.md`, jangan loncat ke deployment sebelum development & testing manual selesai.
- Kalau menemukan sesuatu di luar scope dokumen-dokumen ini (fitur baru besar, perubahan stack, dll), **berhenti dan konfirmasi ke user** dulu sebelum improvisasi.
- Update checklist di `docs/task.md` (centang item selesai) supaya progress tetap terlihat kalau sesi kerja terputus.
