# Area supervisor SI-LEMBUR Implementation Plan

> Gunakan `superpowers:executing-plans` untuk implementasi per task. Pemilik proyek menyetujui rencana ini pada 2026-11-27.

**Tujuan** — Supervisor dan Admin dapat melihat antrean serta riwayat, memproses persetujuan, dan mencatat lembur atas nama karyawan.

**Arsitektur** — Controller Inertia memanggil query panel untuk membaca data dan `OvertimeWorkflow` untuk mengubahnya. Query dan Policy memakai departemen yang tersimpan pada pengajuan. Form Request memvalidasi input, sementara workflow menjaga transisi dan audit dalam transaksi.

**Stack** — Laravel minimal 12, PHP 8.3, Inertia.js, React, TypeScript, Tailwind CSS, MySQL 8/MariaDB 10.6+, Pest, dan Vitest. Gunakan dependensi yang sudah ada.

**Spesifikasi** — [PRD §5 dan §8](../../PRD.md), [Business Rules §1–6](../../BUSINESS_RULES.md), [Architecture §3–5](../../ARCHITECTURE.md), [DESIGN §5–9](../../../DESIGN.md), dan [Roadmap Fase 4](../../ROADMAP.md).

## Batas fase

- Gunakan jam server `Asia/Jakarta`. Perhitungan lembur, otorisasi, dan keputusan status tetap di server.
- Supervisor hanya mengakses departemennya. Admin memakai panel yang sama untuk semua departemen. Aksi edit dan persetujuan memeriksa Policy per pengajuan, termasuk setiap ID dalam bulk.
- Gunakan tabel desktop dan kartu di bawah 640 px. Target sentuh panel minimal 44 px, kontras WCAG AA, dan ring fokus 2 px. Uji browser pada 360 dan 1280 px.
- Fase 5 menangani master data dan pengaturan. Fase 6 menangani Excel dan prosedur koreksi sesudah export. Jawaban HRD tentang hari istirahat, master karyawan, dan template Excel bisa diproses paralel dengan Fase 4.
- Jalankan tes target merah, implementasi, tes hijau, dan `composer ci:check` per task. Buat commit Conventional Commits dan perbarui handoff setelah task lulus.

## Keputusan disetujui pada 2026-11-27

| Keputusan | Rincian |
|---|---|
| Pembatalan persetujuan lama | Samakan jendela pembatalan dengan jendela edit, dari awal bulan sebelumnya sampai hari ini menurut tanggal mulai lembur. Tampilkan batasnya pada riwayat. Di luar jendela, arahkan kasus koreksi ke HRD. Aturan setelah export dibahas pada Fase 6. |
| Arti KPI | Menunggu menghitung seluruh antrean yang terlihat. Disetujui bulan ini dan Total jam bulan ini memakai tanggal mulai lembur pada bulan kalender saat ini, dengan status Disetujui. Lewat batas menghitung pengajuan Menunggu yang memiliki minimal satu flag, satu kali per pengajuan. |
| Koreksi setelah karyawan pindah atau nonaktif | Supervisor departemen yang tersimpan pada pengajuan tetap bisa memperbaiki pengajuan Menunggu yang sah. Karyawan dan departemen pada pengajuan lama tetap sesuai data saat pengajuan dibuat. Input baru memakai karyawan dan departemen yang aktif. |
| Alasan tolak dan batalkan | Pangkas spasi di tepi, lalu validasi 5–500 karakter. Alasan tolak tampil di Cek status publik. Dialog menampilkan arahan untuk memakai Edit bila yang perlu diperbaiki hanya jam atau kegiatan, dan meminta alasan tanpa informasi pribadi. |

Usulan label sumber pengajuan dan jam pengiriman di antrean dicatat pada review Fase 3. Implementasinya menunggu keputusan terpisah.

## Peta file dan kontrak

| Bagian | File utama | Kontrak |
|---|---|---|
| Login dan audit | `app/Actions/Auth/AttemptActiveLogin.php`, `app/Providers/FortifyServiceProvider.php`, `app/Listeners/AuditLogin.php`, `app/Http/Middleware/EnsureRole.php` | Fortify memakai guard web dan throttle yang sudah ada. Rekam `auth.login_succeeded` dan `auth.login_failed` melalui `AuditLogger`. |
| Query panel | `app/Queries/PanelOvertimeQuery.php`, `app/Http/Controllers/Panel/DashboardController.php`, `resources/js/types/panel-overtime.ts` | `visibleTo(User $actor): Builder` mengikat scope ke snapshot `overtimes.department_id`. Payload baris panel memuat ID, nomor, karyawan, departemen, tanggal, waktu, istirahat, total menit, status, kegiatan, dan kedua flag batas. |
| Antrean | `app/Http/Controllers/Panel/ApprovalController.php`, `app/Http/Requests/Panel/ApproveOvertimesRequest.php`, `app/Http/Requests/Panel/OvertimeReasonRequest.php`, `resources/js/pages/panel/approvals.tsx` | `GET /panel/approvals`; `POST /panel/approvals/{overtime}/approve`; `POST /panel/approvals/{overtime}/reject`; `POST /panel/approvals/bulk-approve`. |
| Riwayat | `app/Http/Controllers/Panel/OvertimeHistoryController.php`, `resources/js/pages/panel/overtimes/index.tsx` | `GET /panel/overtimes` dengan `month=YYYY-MM`, `status`, dan `employee_id` opsional. `POST /panel/overtimes/{overtime}/cancel-approval` menerima alasan. |
| Form panel | `app/Http/Controllers/Panel/OvertimeFormController.php`, `app/Http/Requests/Panel/SaveOvertimeRequest.php`, `resources/js/pages/panel/overtimes/form.tsx` | `GET /panel/overtimes/create`, `POST /panel/overtimes`, `GET /panel/overtimes/{overtime}/edit`, dan `PATCH /panel/overtimes/{overtime}`. Create dan edit memakai form yang sama. |
| UI panel bersama | `resources/js/components/panel/overtime-list.tsx`, `resources/js/components/panel/overtime-reason-dialog.tsx`, `resources/js/components/panel/nav-items.ts` | Antrean dan riwayat memakai komponen daftar yang sama dengan aksi berbeda. Dialog alasan memakai Radix yang sudah terpasang. Navigasi diaktifkan saat rutenya tersedia. |

Semua rute panel berada di grup `auth` dan `role:ADMIN,SUPERVISOR` yang sudah ada di `routes/web.php`. Halaman memakai `PanelLayout`. Setelah mutasi berhasil, arahkan kembali ke daftar asal dan pakai flash `toast` yang sudah ada. Untuk respons `InvalidOvertimeTransition`, tampilkan status terbaru dan arahan muat ulang. Iterasi daftar memakai 25 baris per halaman dengan urutan tanggal mulai, jam mulai, lalu ID terbaru. Pertahankan filter saat berpindah halaman.

## Fokus review

| Kasus | Perilaku yang diuji |
|---|---|
| Akun dinonaktifkan saat panel masih terbuka | Middleware mengakhiri sesi dan menampilkan pesan di login. Permintaan berikutnya dari sesi lama tidak dapat mengubah data. Task 1. |
| Lembur tanggal akhir bulan selesai setelah tengah malam | KPI dan riwayat memasukkannya ke bulan tanggal mulai. Task 2 dan 4. |
| Bulk mencampur pengajuan milik sendiri dan departemen lain atau satu status berubah | Seluruh permintaan gagal. Status dan audit pengajuan lain tetap sesuai sebelum permintaan. Task 3. |
| Pengajuan lama tidak bisa diedit setelah pembatalan | Pembatalan pengajuan di luar jendela ditolak sebelum penulisan audit atau perubahan status. Task 4. |
| Karyawan pindah departemen atau nonaktif | Riwayat lama tetap memakai snapshot. Edit hanya melalui departemen snapshot. Input baru memeriksa departemen dan status aktif terkini. Task 5. |

### Task 1 — Login dan audit

- [x] Tambah tes di `tests/Feature/Auth/LoginTest.php` untuk kredensial benar pada akun nonaktif, audit login sukses dan gagal, dan ketiadaan password di audit. Tambah tes pesan sesi berakhir di `tests/Feature/Authorization/RoleMiddlewareTest.php`. Pertahankan tes percobaan keenam yang mendapat 429.
- [x] Jalankan dua file tes tersebut. Tes baru harus gagal pada baseline sebelum perubahan.
- [x] Pada pipeline Fortify, gunakan `AttemptActiveLogin::handle(Request $request, Closure $next): mixed` dengan `SessionGuard::attemptWhen()` untuk menyaring akun aktif setelah pemeriksaan kredensial. Pertahankan kanonisasi username, penyiapan sesi, opsi remember, dan limiter rute yang ada. Gunakan pesan kegagalan umum saat login. Pada sesi yang sudah aktif, middleware menampilkan “Akun ini sudah tidak aktif. Hubungi admin.”
- [x] Hubungkan event guard web `Login` dan `Failed` ke listener `AuditLogin`. Catat jenis aksi serta referensi user saat tersedia, dengan alasan gagal yang umum. Payload kredensial, password, dan hash tidak masuk audit.
- [x] Ulangi dua file tes dan jalankan `composer ci:check`. Commit `2c4e78c`.

Tes tambahan memastikan cookie “Ingat saya” lama untuk akun nonaktif tidak tercatat sebagai login berhasil. Build produksi, `composer ci:check`, dan pemeriksaan browser 360 dan 1280 px lulus.

### Task 2 — Dashboard dan scope pembacaan

- [ ] Buat `tests/Feature/Panel/DashboardTest.php`. Assert KPI supervisor mengecualikan departemen lain, Admin melihat kedua departemen, menit hanya dari pengajuan Disetujui pada bulan berjalan, dan pengajuan dengan dua flag dihitung sekali. Bekukan jam Jakarta pada awal bulan dan masukkan lembur yang mulai akhir bulan lalu dan selesai setelah tengah malam.
- [ ] Jalankan file tes dan lihat assertion KPI gagal pada dashboard placeholder.
- [ ] Sambungkan `DashboardController::index(Request $request): Response` ke `GET /panel` dan pertahankan nama rute `panel.dashboard`. Pakai `PanelOvertimeQuery::visibleTo()` untuk agregat dan baris antrean. Tampilkan empat KPI, hingga lima pengajuan Menunggu terbaru, dan tautan ke antrean setelah Task 3 aktif.
- [ ] Tampilkan kartu angka dengan komponen panel, pakai ulang `StatusPill` serta formatter tanggal dan durasi. Jalankan tes dashboard dan `composer ci:check`. Commit `feat(panel): show scoped overtime dashboard`.

### Task 3 — Antrean, setujui, dan tolak

- [ ] Buat `tests/Feature/Panel/ApprovalTest.php`. Uji daftar berisi Menunggu pada departemen yang benar, setiap aksi lintas departemen mendapat 403, alasan kosong atau lebih dari 500 karakter ditolak, dan bulk dengan satu ID luar scope atau status berubah tidak mengubah pengajuan mana pun. Uji audit satu baris per pengajuan yang disetujui.
- [ ] Jalankan tes sampai gagal pada rute yang belum ada.
- [ ] Tambah rute antrean dan aksi. `ApproveOvertimesRequest` menerima `ids` unik, integer positif, minimal satu dan maksimal 25. Workflow tetap membaca dan mengunci tiap ID serta memeriksa Policy dan status sebelum menyimpan. `OvertimeReasonRequest` memvalidasi `reason` yang telah dipangkas, dengan panjang 5–500. Tegakkan batas yang sama pada `OvertimeWorkflow::validReason()`.
- [ ] Teruskan satu `CarbonImmutable` per aksi saat memanggil `approve`, `approveMany`, dan `reject`. Pertahankan pemanggilan existing dengan parameter terakhir `?CarbonImmutable $now = null` dan fallback jam server di workflow. Konfirmasi tanggal audit dan waktu persetujuan konsisten.
- [ ] Tampilkan tabel dan kartu yang memuat label “Lewat batas harian” dan “Lewat batas mingguan” sesuai flag. Kotak pilih-semua hanya berlaku di halaman aktif. Uji pilihan massal dan dialog alasan di `resources/js/test/panel-approvals.test.tsx`. Tambahkan tautan Edit di dialog setelah rute Task 5 tersedia.
- [ ] Jalankan tes antrean, workflow, dan `composer ci:check`. Commit `feat(panel): process overtime approvals`.

### Task 4 — Riwayat dan batalkan persetujuan

- [ ] Buat `tests/Feature/Panel/OvertimeHistoryTest.php`. Assert filter bulan, status, dan karyawan bekerja bersama, pengajuan dari departemen lain tetap tertutup, Admin berwenang lintas departemen, dan lembur lintas tengah malam memakai tanggal mulai. Uji alasan wajib, audit pembatalan, batas awal bulan sebelumnya inklusif, dan pengajuan yang sehari lebih tua tetap Disetujui.
- [ ] Jalankan tes sampai gagal pada rute dan batas pembatalan yang belum ada.
- [ ] Pakai query panel dan filter secara gabungan. Bulan default adalah bulan server saat ini. Pilihan karyawan pada riwayat berasal dari pengajuan yang terlihat agar riwayat karyawan pindah tetap dapat ditemukan. Pada pembatalan, setelah Policy dan lock, pakai aturan tanggal panel yang sama untuk tanggal mulai lembur. Teruskan jam server dengan kontrak parameter terakhir opsional seperti Task 3. Audit perubahan dan kosongkan informasi persetujuan melalui workflow.
- [ ] Tampilkan riwayat semua status, aksi Batalkan persetujuan untuk pengajuan yang memenuhi jendela, dan alasan jika aksinya tidak tersedia. Uji hasil filter serta pesan konsekuensi koreksi pada browser.
- [ ] Jalankan tes riwayat dan workflow, lalu `composer ci:check`. Commit `feat(panel): show history and undo approvals`.

### Task 5 — Input atas nama dan edit

- [ ] Buat `tests/Feature/Panel/OvertimeFormTest.php`. Uji input sah langsung Disetujui dengan sumber `SUPERVISOR`, tanggal mundur sesuai jendela, tanggal maju ditolak, karyawan beda departemen atau nonaktif ditolak, dan Supervisor mendapat 403 untuk GET/PATCH pengajuan departemen lain. Uji bahwa ID karyawan/departemen historis tidak bisa ditukar serta edit memvalidasi ulang overlap dan menghitung flag minggu lama dan baru.
- [ ] Jalankan tes sampai gagal pada rute yang belum ada.
- [ ] Tambahkan form dan Form Request. Saat create, Supervisor menggunakan departemen akunnya, dan Admin memilih departemen sebelum memilih karyawan aktif. Saat edit, muat pengajuan melalui Policy dan tampilkan identitas historis sebagai baca-saja. Ambil ID historis dari model di server. `SaveOvertimeRequest` menerima tanggal ISO, waktu `HH:mm`, menit istirahat, dan kegiatan. Teruskan ke `submitBySupervisor()` atau `update()` dengan satu jam server.
- [ ] Pakai ulang picker, pemilih karyawan, dan perhitungan preview yang kompatibel. Tambah tes Vitest di `resources/js/test/panel-overtime-form.test.tsx` untuk jam lintas tengah malam dan field historis baca-saja. Aktifkan navigasi dan tautan Edit dari riwayat dan antrean.
- [ ] Jalankan tes form dan workflow, lalu `composer ci:check`. Commit `feat(panel): add proxy entry and overtime edits`.

### Task 6 — Verifikasi dan penutupan

- [ ] Jalankan build produksi, `composer ci:check`, dan suite terpisah pada MySQL 8. Pastikan tidak ada tes gagal dan kedua tes konkurensi berjalan di MySQL.
- [ ] Uji browser pada 360 dan 1280 px untuk login Supervisor dan Admin, dashboard, aksi tunggal dan bulk, riwayat, pembatalan, input atas nama, serta edit. Cek kontrol keyboard, pesan error, kontras, dan kartu mobile. Ukur ulang Lighthouse mobile untuk form publik dan Cek status sebagai cek regresi Fase 3.
- [ ] Review diff fase dengan `caveman-review` dan catat snapshot `caveman-stats`. Cocokkan seluruh acceptance criteria Fase 4 dengan bukti tes. Perbarui Roadmap dan Handoff.
- [ ] Push ke GitHub, verifikasi job SQLite dan MySQL hijau, lalu laporkan hasil fase. Mulai Fase 5 setelah konfirmasi pemilik proyek.
