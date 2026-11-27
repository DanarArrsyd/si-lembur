# Handoff — status kerja SI-LEMBUR

Untuk agent AI mana pun (Claude Code, Codex, Cursor, dll.) yang melanjutkan pekerjaan. Baca `AGENTS.md`/`CLAUDE.md` dulu, lalu file ini, lalu rencana fase yang aktif.

**Perbarui file ini di akhir setiap task** (status, commit, langkah berikutnya). Bila isi file ini berbeda dengan checkbox di rencana fase atau `docs/ROADMAP.md`, yang benar adalah checkbox + `git log`.

Terakhir diperbarui: 2026-11-27, setelah Fase 3 Task 6.

## Posisi sekarang

| Fase | Status |
|---|---|
| 1 Fondasi | Selesai |
| 2 Logika inti | Selesai |
| 3 Form publik + cek status | **Berjalan**: Task 0–6 selesai, **berikutnya Task 7** |
| 4–7 | Belum mulai |

Rencana aktif: `docs/superpowers/plans/2026-11-27-fase-3-form-publik.md`. Sudah disetujui pemilik proyek pada 2026-11-27, termasuk 8 keputusan di bagian "Keputusan". Jangan menanyakan ulang keputusan itu.

### Fase 3, per task

| Task | Isi | Status | Commit |
|---|---|---|---|
| 0 | Sinkron `ARCHITECTURE.md`, job MySQL 8 di CI | Selesai | `14c8cf4`, `89d075c` |
| 1 | Pisah bundle publik dari panel | Selesai | `15b3e9b` |
| 2 | Endpoint daftar karyawan + `TRUSTED_PROXIES` | Selesai | `c70d38c` |
| 3 | Pencarian nama (fuse.js) + komponen Typeahead | Selesai | `4eba79a` |
| 4 | TimePicker + BottomSheet | Selesai | `d232af5` |
| 5 | Form publik + layar "Periksa lagi" | Selesai | `09ae983` |
| 6 | Kirim + halaman sukses | Selesai | `335145c` |
| 7 | Halaman cek status | **Berikutnya** | – |
| 8 | Verifikasi (360px/412px, Lighthouse), laporan fase | Belum | – |

Verifikasi Task 6: build produksi dan `composer ci:check` lulus lokal (Pest 195 lulus, 2 tes konkurensi khusus MySQL dilewati di SQLite, Vitest 60 lulus). Di browser 360px, 412px, dan 1280px, klik ganda mengirim satu POST, pengajuan tampil dengan nomor dan status Menunggu, dan tombol Back serta “Isi lembur lagi” membuka form kosong. Database uji mencatat tiga pengajuan dan tiga audit. Job `ci` dan `mysql` terakhir di GitHub lulus pada `403df3f`. Task 4–6 masih lokal.

## Hal yang perlu diketahui sebelum Task 7

- **Layout halaman:** `resources/js/app.tsx` tidak memilih layout. Setiap halaman menyatakan sendiri: `Page.layout = [PublicLayout, { title: '…' }]`. Test `resources/js/pages/page-layouts.test.ts` memastikan ini.
- **Bundle publik:** komponen yang dipakai form publik memakai `clsx`, bukan `cn()` dari `@/lib/utils`, supaya `tailwind-merge` tidak ikut termuat. JS awal `/` pada pengukuran Task 5 sekitar 144,6 KB gzip berdasarkan lima aset JS yang dimuat di browser. Ukur Lighthouse mobile Performance ≥ 85 di Task 8.
- **Kirim lembur** memakai payload yang sudah diperiksa, termasuk tanggal absolut, dengan validasi ulang di server. Halaman sukses memakai signed URL 24 jam. Tautan “Cek status” menargetkan `/status?department={id}&employee={id}` dari halaman sukses dan `/status` dari halaman tautan tidak berlaku. Task 7 menyediakan halaman tersebut, dapat memakai `StatusPill` yang sudah ada, dan perlu memakai limiter tersendiri untuk status.
- **Gaya form publik** (DESIGN.md §4): label 15px/600, nilai input 17px, kontrol 52px, baris pilihan ≥ 48px, border `line-control`, invalid `border-danger bg-danger-soft`, error 14px `text-danger`. Shadow melayang memakai `shadow-[var(--shadow-float)]`.
- **Mock Inertia untuk test** (`resources/js/test/inertia-mock.tsx`) harus punya `router.on`, karena `PanelLayout` merender `Toaster`.
- **Endpoint publik:** `GET /api/departments/{department}/employees` → `[{id, nik, name}]`, throttle 60/menit per IP. Proxy tepercaya diatur lewat `TRUSTED_PROXIES` (`App\Http\Middleware\TrustProxies`).

## Menunggu jawaban / persetujuan

- **Teks UI baru** yang belum disetujui pemilik proyek (masukkan ke laporan akhir Fase 3): "Ketik nama, lalu pilih dari daftar.", "Pilih departemen dulu.", "Memuat daftar nama…", "NIK {nik}", "Tautan pengajuan tidak berlaku", "Buka Cek status untuk melihat pengajuan lembur.".
- **Pertanyaan ke HRD** (belum dijawab): hari istirahat mingguan (Minggu saja atau Sabtu dan Minggu), file template Excel export, file data karyawan untuk import.
- **Temuan untuk Fase 4–6** ada di bagian "Dicatat untuk fase berikutnya" pada rencana Fase 3. Angkat di awal fase terkait.

## Jebakan lingkungan

- Pest butuh `npm run build` dulu (Vite manifest).
- Kolom `date` di-query dengan `whereDate` (SQLite menyimpan `Y-m-d 00:00:00`).
- `tests/Concurrency` hanya jalan di MySQL/MariaDB. Untuk menjalankannya lokal: `docker compose up -d`, lalu `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=si_lembur DB_USERNAME=si_lembur DB_PASSWORD=secret php artisan test`.
- `vp check --fix` tidak boleh menyentuh file `*.md` (sudah dikecualikan di `vite.config.ts`).
- `php artisan migrate:fresh` mengosongkan database dev lokal. Tanya pemilik proyek dulu.
- Di Mac pemilik proyek, `composer` kadang segfault; jalankan dengan `php -d disable_functions=curl_multi_exec $(which composer) …`.

## Cara kerja yang disepakati

- Satu task → TDD (test merah dulu) → implement → `composer ci:check` → commit Conventional Commits → centang task di rencana → perbarui file ini.
- Akhir fase: suite MySQL, uji manual 360px & 412px, Lighthouse, review diff, centang `docs/ROADMAP.md`, push, laporan dengan format di `docs/ROADMAP.md`, lalu tunggu konfirmasi pemilik proyek sebelum fase berikutnya.
- Chat dengan pemilik proyek dalam Bahasa Indonesia, singkat. Teks UI, dokumen, dan komentar kode ditulis lengkap.
