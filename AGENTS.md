# AGENTS.md — SI-LEMBUR (Sistem Informasi Lembur) PT Kenco

Panduan kerja untuk Codex di repo ini. Baca file ini dulu, lalu dokumen yang relevan di bawah sebelum menulis kode.

## Proyek singkat

Nama produk: **SI-LEMBUR** (Sistem Informasi Lembur). Tulis selalu huruf kapital dengan tanda hubung: `SI-LEMBUR`, bukan "Si Lembur" atau "SILEMBUR". Di kode/slug pakai `si-lembur`.

Web internal untuk mencatat lembur karyawan pabrik PT Kenco Manufaktur Indonesia (150+ karyawan).

- Karyawan mengisi lembur **setelah lembur selesai** lewat link publik di HP, **tanpa login**.
- **SUPERVISOR** (1 orang per departemen) login untuk approve/reject.
- **ADMIN** mengelola master data, kalender libur, akun, dan export Excel per bulan kalender.

## Peta dokumen

| File                     | Isi                                                                         | Baca saat                                  |
| ------------------------ | --------------------------------------------------------------------------- | ------------------------------------------ |
| `docs/PRD.md`            | Role, alur, fitur per halaman, export, di luar cakupan, acceptance criteria | Sebelum mulai fase apa pun                 |
| `docs/BUSINESS_RULES.md` | Aturan tanggal, perhitungan jam, jenis hari, batas lembur, overlap, status  | Sebelum menyentuh logika lembur            |
| `docs/ARCHITECTURE.md`   | Stack, struktur folder, skema DB, service, keamanan                         | Sebelum membuat migrasi, model, controller |
| `DESIGN.md`              | Design system Industrial Blue, token, komponen, aturan mobile, copy         | Sebelum menyentuh UI apa pun               |
| `docs/ROADMAP.md`        | 7 fase pengerjaan + checklist                                               | Awal dan akhir setiap fase                 |
| `docs/DEPLOY.md`         | Deploy ke Hostinger shared hosting                                          | Fase 7                                     |

Bila dua dokumen bertentangan, urutan prioritas: `BUSINESS_RULES.md` > `PRD.md` > `ARCHITECTURE.md` > `DESIGN.md`. Laporkan konfliknya ke pemilik proyek.

## Stack (terkunci)

Laravel (stabil terbaru, minimal 12) · PHP 8.3 · Inertia.js + React + TypeScript · Tailwind CSS · MySQL 8 / MariaDB 10.6+ · PhpSpreadsheet · Pest · Vitest.
Target hosting: **Hostinger shared/cloud** → tidak ada PostgreSQL, Redis, Docker, queue worker, atau Node di server.

Jangan mengganti atau menambah framework/library besar tanpa persetujuan. Library kecil (mis. `fuse.js`, `lucide-react`) boleh bila beralasan, sebutkan di laporan fase.

## Perintah

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
docker compose up -d        # MySQL 8 lokal di port 3307 (hanya dev)
php artisan migrate --seed
composer run dev            # server + vite
php artisan test            # Pest
npm run test                # Vitest
npm run build               # build asset produksi
./vendor/bin/pint           # format PHP
npm run lint && npm run typecheck
composer ci:check           # semua cek sekaligus (sama dengan CI); butuh npm run build dulu
```

Perbarui bagian ini bila perintah berubah.

## Aturan kerja

1. **Audit dulu, baru tulis.** Baca dokumen terkait dan kode yang ada sebelum mengubah apa pun.
2. **Rencana sebelum build.** Di awal tiap fase, tulis rencana singkat (file yang dibuat/diubah, test yang ditambah) dan tunggu persetujuan.
3. **Satu fase sekali jalan.** Selesai fase → jalankan semua test, lint, typecheck → laporkan hasil → tunggu konfirmasi sebelum fase berikutnya.
4. **Tidak ada fitur di luar dokumen.** Ide tambahan ditulis sebagai usulan di laporan, bukan langsung dibuat.
5. **Logika bisnis di server.** Perhitungan jam, jenis hari, batas, overlap, otorisasi dijalankan di service PHP + Form Request + Policy. Frontend hanya preview.
6. **Waktu = server `Asia/Jakarta`.** Jangan memakai jam browser/HP untuk validasi.
7. **Tidak ada hard delete** untuk data lembur. Master yang sudah dipakai → nonaktifkan.
8. **Semua aksi penting masuk audit log** (lihat `docs/ARCHITECTURE.md`).
9. **Mobile-first** untuk semua halaman. Uji di viewport 360px.
10. Bila informasi kurang (template Excel, data karyawan, hari libur mingguan), **tanya**, jangan mengarang.

## Skill yang dipakai

| Kapan                           | Skill                                        |
| ------------------------------- | -------------------------------------------- |
| Menyusun rencana fase           | `superpowers:writing-plans`                  |
| Logika hitung, validasi, policy | `superpowers:test-driven-development`        |
| Semua UI                        | `antislop`, `antislop-ui`                    |
| Layout mobile & responsif       | `antislop-layoutmobile`                      |
| Aksesibilitas                   | `antislop-human`                             |
| Semua teks UI                   | `antislop-copywriting`                       |
| Bug / test gagal                | `superpowers:systematic-debugging`           |
| Sebelum klaim selesai           | `superpowers:verification-before-completion` |

Bila sebuah skill tidak tersedia, sebutkan dan terapkan prinsip yang sama secara manual.

### Hemat token (caveman)

**Aktifkan `caveman` sejak awal sesi.** Semua respons chat ke pemilik proyek ditulis singkat: jawaban dulu, tanpa basa-basi, semua fakta teknis tetap ada.

| Kapan                                                       | Skill                                                          |
| ----------------------------------------------------------- | -------------------------------------------------------------- |
| Semua respons chat, rencana fase, laporan fase              | `caveman`                                                      |
| Mencari file/simbol yang belum diketahui lokasinya          | `caveman-explore` (bukan membaca banyak file di konteks utama) |
| Delegasi tugas kecil: cari kode, edit 1–2 file, review diff | `cavecrew`                                                     |
| Menulis pesan commit                                        | `caveman-commit` (tetap format Conventional Commits)           |
| Review diff sebelum laporan akhir fase                      | `caveman-review`                                               |
| Cek pemakaian token di akhir fase                           | `caveman-stats`                                                |

**Batas caveman — tetap tulis normal & lengkap untuk:**

- Teks UI (label, pesan error, copy form) → ikuti `antislop-copywriting` dan `DESIGN.md` §9.
- Isi dokumen di `docs/`, `DESIGN.md`, `README.md`, `DEPLOY.md`.
- Komentar kode, PHPDoc, dan pesan validasi.
- Pertanyaan klarifikasi ke pemilik proyek yang butuh keputusan (tetap ringkas, tapi kalimat utuh).

Jangan jalankan `caveman-compress` pada file `AGENTS.md` atau dokumen di `docs/` tanpa izin pemilik proyek.

## Konvensi kode

- PHP: PSR-12 via Pint, strict types, service class di `app/Services`, enum PHP untuk status/role/day type.
- Controller tipis: validasi di Form Request, otorisasi di Policy, logika di Service.
- TypeScript strict, komponen React fungsional, props bertipe. Tidak ada `any`.
- Nama tabel/kolom/kode dalam bahasa Inggris; teks UI dalam Bahasa Indonesia.
- Commit kecil per unit kerja, pesan format Conventional Commits (via `caveman-commit`).

## Definition of done (per fitur)

- Test unit/feature lulus, termasuk kasus dari `docs/BUSINESS_RULES.md` §7.
- Lint, typecheck, Pint bersih.
- UI dicek di 360px dan 1280px, sesuai `DESIGN.md`.
- Otorisasi dites lewat manipulasi request (supervisor dept A ke data dept B harus 403).
- Aksi tercatat di audit log bila termasuk daftar audit.
