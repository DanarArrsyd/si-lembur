# Handoff — status kerja SI-LEMBUR

Untuk agent AI mana pun (Claude Code, Codex, Cursor, dll.) yang melanjutkan pekerjaan. Baca `AGENTS.md`/`CLAUDE.md` dulu, lalu file ini, lalu rencana fase yang aktif.

**Perbarui file ini di akhir setiap task** (status, commit, langkah berikutnya). Bila isi file ini berbeda dengan checkbox di rencana fase atau `docs/ROADMAP.md`, yang benar adalah checkbox + `git log`.

Terakhir diperbarui: 2026-11-28, setelah penutupan Fase 4.

## Posisi sekarang

| Fase | Status |
|---|---|
| 1 Fondasi | Selesai |
| 2 Logika inti | Selesai |
| 3 Form publik + cek status | Selesai |
| 4 Area supervisor | Selesai. Task 1–6 lulus, sudah di-push ke `main`. |
| 5 Area admin | Belum mulai. Rencana fase menunggu persetujuan pemilik proyek. |
| 6–7 | Belum mulai |

Rencana Fase 3: `docs/superpowers/plans/2026-11-27-fase-3-form-publik.md`. Disetujui pemilik proyek pada 2026-11-27, termasuk delapan keputusan di bagian "Keputusan". Rencana Fase 4 disetujui pada 2026-11-27 dan tersedia di `docs/superpowers/plans/2026-11-27-fase-4-area-supervisor.md`.

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
| 7 | Halaman cek status | Selesai | `d1cabe9` |
| 8 | Verifikasi MySQL, browser, Lighthouse, review, dan push | Selesai | `48390aa` |

Verifikasi Task 8: build produksi dan `composer ci:check` lulus lokal. Pest lulus 203 tes di MySQL 8.0.46 (termasuk 2 tes konkurensi) dan 201 di SQLite (2 dilewati). Vitest lulus 68 tes. Alur pengisian sampai sukses lalu cek status lulus di Chromium 360px dan 412px dengan emulasi sentuh dan hanya keyboard. Tidak ada scroll horizontal atau zoom otomatis. Lighthouse mobile (Performance/Accessibility): `/` 98/100, `/status` 100/100, status dengan riwayat 98/100. [CI GitHub pada commit kode Fase 3](https://github.com/DanarArrsyd/si-lembur/actions/runs/46030508425) lulus di job `ci` dan `mysql`.

## Fase 4, per task

| Task | Isi | Status | Commit |
|---|---|---|---|
| 1 | Login dan audit | Selesai | `2c4e78c` |
| 2 | Dashboard dan scope pembacaan | Selesai | `7f44c82` |
| 3 | Antrean, setujui, dan tolak | Selesai | `dc548e7` |
| 4 | Riwayat dan batalkan persetujuan | Selesai | `0acce31` |
| 5 | Input atas nama dan edit | Selesai | `bdb697e` |
| 6 | Verifikasi dan penutupan | Selesai | `4f91596` |

Verifikasi Task 1: build produksi dan `composer ci:check` lulus lokal. Pest 206 lulus dan dua tes konkurensi dilewati di SQLite. Vitest 68 lulus. Browser 360 dan 1280 px menampilkan pemberitahuan pada sesi yang berakhir setelah penonaktifan. Login aktif berhasil, login ulang untuk akun nonaktif ditolak, dan data audit uji mencatat satu keberhasilan serta dua kegagalan.

Verifikasi Task 2: build produksi dan `composer ci:check` lulus lokal. Pest 209 lulus dan dua tes konkurensi khusus MySQL dilewati di SQLite. Vitest 68 lulus. Tiga tes dashboard juga lulus di MySQL 8. Uji browser 360 px sebagai Supervisor dan 1280 px sebagai Admin mengonfirmasi scope, KPI, dan layout.

Verifikasi Task 3: build produksi dan `composer ci:check` lulus lokal. Pest 223 lulus, dua tes konkurensi khusus MySQL dilewati di SQLite, dan Vitest 72 lulus. Di MySQL 8, 55 tes antrean dan workflow lulus. Browser 360 px dan 1280 px mengonfirmasi setujui tunggal, setujui massal, tolak, reset pilihan saat pindah halaman, dan toast setelah kembali dari halaman terakhir yang kosong.

Verifikasi Task 4: build produksi dan `composer ci:check` lulus lokal. Pest 229 lulus, dua tes konkurensi khusus MySQL dilewati di SQLite, dan Vitest 73 lulus. Di MySQL 8, 47 tes riwayat dan workflow lulus. Browser 360 px dan 1280 px mengonfirmasi filter gabungan, pembatalan dengan alasan, status kembali Menunggu dengan filter tetap terpasang, serta batas pembatalan.

Verifikasi Task 5: build produksi dan `composer ci:check` lulus lokal. Pest 238 lulus dan dua tes konkurensi khusus MySQL dilewati di SQLite. Vitest 76 lulus. Di MySQL 8, 50 tes form dan workflow lulus. Browser 360 px mengonfirmasi validasi, input lintas tengah malam, dan edit dari antrean. Browser 1280 px mengonfirmasi pemilihan departemen Admin dan hasil edit yang tersimpan.

Verifikasi Task 6: build produksi dan `composer ci:check` lulus. Pest lulus 240 tes di MySQL 8.0.46, termasuk dua tes konkurensi, dan 238 di SQLite. Vitest lulus 76 tes. Browser mengonfirmasi dashboard, scope departemen, persetujuan tunggal dan massal, riwayat dan filter, pembatalan, edit, penolakan, dan input atas nama. Lebar yang diperiksa 360, 412, dan 1280 px. Audit axe tidak menemukan pelanggaran pada dashboard Supervisor, dialog penolakan, form Admin, dan riwayat Admin. Lighthouse mobile Performance dan Accessibility masing-masing 100 untuk form publik, Cek status, dan Cek status berisi riwayat. [GitHub Actions commit kode Fase 4](https://github.com/DanarArrsyd/si-lembur/actions/runs/46108947447) lulus untuk job `ci` dan `mysql`.

Review akhir menambahkan tautan Edit dari dialog penolakan dan memperbaiki kontras tampilan total menit. Setelah pengajuan baru dicatat atau pengajuan Menunggu diedit, panel menampilkan hasil pada riwayat bulan dan status terkait.

## Hal yang perlu diketahui sebelum Fase 5

- **Input atas nama** aktif di `/panel/overtimes/create`. Supervisor memakai departemen akun dan Admin memilih departemen. Pengajuan langsung Disetujui. Tautan **Edit** ada di antrean dan riwayat untuk pengajuan Menunggu. Nama, NIK, dan departemen pengajuan tampil baca-saja.

- **Riwayat** aktif di `/panel/overtimes`. Filter memakai tanggal mulai lembur dan departemen tersimpan saat pengajuan. Form edit dapat memakai `OvertimeDateRule::panelDateBounds()` untuk batas tanggal. Riwayat dan antrean memakai ulang `OvertimeList`, `PanelPagination`, dan `OvertimeReasonDialog`.

- **Persetujuan** aktif di `/panel/approvals` dari dashboard dan sidebar. Pilihan massal berlaku per halaman, maksimal 25 ID. Workflow memeriksa Policy untuk semua ID sebelum memeriksa status, dengan transaksi untuk seluruh batch. `OvertimeReasonRequest` dan `AuditLogger` mendukung validasi alasan dan audit untuk penolakan dan pembatalan. Tautan Edit dari antrean membuka form pengajuan Menunggu.

- **Dashboard** memakai `PanelOvertimeQuery::visibleTo()` untuk scope snapshot departemen dan `row()` untuk payload `PanelOvertimeRow`. `OvertimeList` menampilkan kartu di bawah 640 px dan tabel pada layar lebih lebar. Rute antrean bernama `panel.approvals.index`.

- **Layout halaman:** `resources/js/app.tsx` tidak memilih layout. Setiap halaman menyatakan sendiri: `Page.layout = [PublicLayout, { title: '…' }]`. Test `resources/js/pages/page-layouts.test.ts` memastikan ini.
- **Bundle publik:** komponen yang dipakai form publik memakai `clsx`, bukan `cn()` dari `@/lib/utils`, supaya `tailwind-merge` tidak ikut termuat. JS awal pada pengukuran akhir sekitar 147,0 KB gzip di `/` dan 126,9 KB di `/status`. Anggaran awal 90 KB untuk form belum tercapai. Kedua halaman memenuhi ambang Lighthouse mobile.
- **Cek status** aktif dari header dan halaman sukses. `/api/status` memakai limiter tersendiri 60/menit per IP dan hanya mengirim riwayat karyawan aktif, tanpa kegiatan lembur. Tautan dari halaman sukses memilih departemen karyawan saat ini. Ringkasan di halaman sukses tetap menunjukkan departemen saat lembur dicatat.
- **Gaya form publik** (DESIGN.md §4): label 15px/600, nilai input 17px, kontrol 52px, baris pilihan ≥ 48px, border `line-control`, invalid `border-danger bg-danger-soft`, error 14px `text-danger`. Shadow melayang memakai `shadow-[var(--shadow-float)]`.
- **Mock Inertia untuk test** (`resources/js/test/inertia-mock.tsx`) harus punya `router.on`, karena `PanelLayout` merender `Toaster`.
- **Endpoint publik:** `GET /api/departments/{department}/employees` → `[{id, nik, name}]`, throttle 60/menit per IP. Proxy tepercaya diatur lewat `TRUSTED_PROXIES` (`App\Http\Middleware\TrustProxies`).

## Menunggu jawaban / persetujuan

- **Teks UI baru** yang belum disetujui pemilik proyek (masukkan ke laporan akhir Fase 3): "Ketik nama, lalu pilih dari daftar.", "Pilih departemen dulu.", "Memuat daftar nama…", "NIK {nik}", "Tautan pengajuan tidak berlaku", "Buka Cek status untuk melihat pengajuan lembur.", "Isi lembur" (tautan kembali dari Cek status), "Pengajuan 60 hari terakhir", "Memuat status pengajuan…", "Status pengajuan gagal dimuat.", dan "Muat ulang status".
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
