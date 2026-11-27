# Fase 3 — Form publik + cek status: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans (native) + superpowers:test-driven-development. UI: `antislop`, `antislop-ui`, `antislop-layoutmobile`, `antislop-human`, `antislop-copywriting`. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Karyawan bisa mengisi lembur dari HP 360px tanpa login (form → periksa → kirim → sukses) dan mengecek status pengajuan 60 hari terakhir, memakai `OvertimeWorkflow` dari Fase 2.

**Architecture:** Halaman Inertia + Form Request tipis di `app/Http/Controllers/Public`. Endpoint JSON kecil untuk daftar karyawan per departemen dan status. Layar "Periksa lagi" dihitung server (validasi penuh tanpa menyimpan), jadi error aturan bisnis dan info lewat batas muncul sebelum tombol "Kirim lembur". Bundle publik dipisah dari panel.

**Tech Stack:** Laravel 13, Inertia 3 (flash), React 19, `fuse.js` (sudah disetujui di ARCHITECTURE.md), Radix Dialog (sudah terpasang) untuk bottom sheet.

**Spec:** `docs/PRD.md` §4, §7, §8 #1 #3 #4 #12; `DESIGN.md` §4, §6, §7, §9; `docs/ARCHITECTURE.md` §3, §5, §6; `docs/BUSINESS_RULES.md` §1.1, §2.1, §4.

## Global Constraints

- Form publik: teks & nilai input 17px, label 15px/600, helper/error 14px, kontrol 52px, baris pilihan ≥ 48px, tombol utama lebar penuh 52px sticky bawah dengan safe-area inset, konten maks 480px padding 16px (DESIGN.md §4).
- Satu tombol primary per layar. Error di bawah field + ringkasan di atas tombol bila > 1 error.
- Semua jam/angka/NIK/nomor: JetBrains Mono tabular. Format: `09 Nov 2026`, `23:00`, `2 jam 30 menit (2,5 jam)`.
- Tanggal "Hari ini"/"Kemarin" dan labelnya dihitung server, bukan jam HP.
- Throttle: submit/periksa 10/menit per IP, lookup & status 60/menit per IP (ARCHITECTURE.md §5).
- Endpoint karyawan hanya mengembalikan `id`, `nik`, `name` karyawan aktif.
- Tanpa `dangerouslySetInnerHTML`, tanpa `any`.
- Lighthouse mobile form publik: Accessibility ≥ 95, Performance ≥ 85.

## Keputusan (disetujui pemilik proyek 2026-11-27)

1. **Picker jam = bottom sheet** (bukan `<select>` native). Alasan: PRD minta "saat dibuka langsung scroll ke jam terdekat". `<select>` native di Android selalu membuka dari pilihan terpilih/atas; supaya bisa mulai dari jam sekarang kita harus mengisi jam default yang mungkin salah dan lolos tanpa dicek karyawan. Bottom sheet: daftar 96 jam (baris 52px, mono), terbuka di jam server terdekat tanpa mengisi apa pun. Dipakai juga untuk Istirahat (0–4 jam).
2. **"Periksa lagi" divalidasi server.** Tombol "Lanjut periksa" → `POST /lembur/periksa` (semua aturan R1–R8 + info lewat batas, tanpa menyimpan) → layar ringkasan. "Kirim lembur" → `POST /lembur` (validasi ulang + simpan). Info lewat batas harian/mingguan tampil di layar periksa (butuh total lembur lain hari itu/minggu itu yang hanya ada di server). Total jam di form tetap real-time dari `lib/overtime.ts`.
3. **Privasi Cek status.** Tanpa login, siapa pun bisa membuka riwayat lembur rekan kerja (jam, total, status, alasan tolak). Kegiatan tidak pernah ditampilkan.
   Dipilih opsi (a), sesuai PRD apa adanya: pilih departemen → nama → daftar tampil. Opsi (b), mengetik 4 digit terakhir NIK, tidak dibuat.
4. **Link halaman sukses berlaku 24 jam** (signed URL). Sesudah itu 403 dengan arahan ke Cek status.
5. **Proxy tepercaya lewat `.env`:** `TRUSTED_PROXIES` (default kosong = tidak percaya header `X-Forwarded-For`). Isi bila Hostinger/Cloudflare dipakai di depan aplikasi, supaya throttle per IP memakai IP HP, bukan IP proxy. Perlu dicek saat deploy (Fase 7).
6. **Honeypot terisi** → 422 dengan pesan umum "Pengajuan tidak bisa diproses. Muat ulang halaman lalu coba lagi." (tanpa menyimpan, dicatat di log aplikasi).
7. **Arahan setelah ditolak di Cek status.** PRD bilang karyawan boleh mengajukan ulang, tapi form publik hanya menerima "Hari ini" dan "Kemarin" (sebelum jam batas). Pengajuan yang ditolak 2 hari setelah lembur tidak bisa diajukan ulang oleh karyawan. Kartu DITOLAK di Cek status menampilkan arahan yang dihitung server:
   - tanggal lembur masih ada di pilihan form saat ini → "Ajukan ulang lewat form lembur." + tautan ke `/`;
   - selain itu → "Hubungi supervisor untuk mengajukan ulang lembur ini."
   Teks ini tambahan copy di luar PRD, disetujui bersama rencana ini.
8. **Pesan yang belum disetujui dari Fase 2** ikut dipakai di form publik: "Pilih tanggal lembur." (tanggal kosong), disetujui bersama rencana ini. Pesan "masih menunggu persetujuan" (`InvalidOvertimeTransition`) baru tampil di panel Fase 4.

## Pertanyaan ke HRD yang sebaiknya dikirim sekarang

Belum memblok Fase 3, tapi jawabannya menentukan data sejak hari pertama dipakai. Lebih murah diketahui sebelum UI panel dibangun.

1. **Hari istirahat mingguan:** Minggu saja, atau Sabtu dan Minggu? Menentukan jenis hari (`HARI_LIBUR`) dan peringatan batas 4/18 jam. Sekarang default Minggu.
2. **File template Excel export** (Fase 6). Bila template butuh kolom yang belum ada (mis. shift, uang makan), skema berubah.
3. **File data karyawan** untuk import awal (Fase 5).

## Review Focus

1. **Form dibuka 11:58, "Kemarin" dikirim 12:01** → error di field Tanggal dengan pesan §1.1, pilihan tanggal di halaman diperbarui (Kemarin hilang), isian lain tetap. Test di Task 5.
2. **Departemen diganti setelah nama dipilih** → nama dikosongkan, daftar karyawan dimuat ulang. Test di Task 3.
3. **Dua karyawan bernama sama** → keduanya tampil dengan NIK, yang terkirim `employee_id` yang dipilih. Test di Task 3.
4. **Tombol Kirim ditekan dua kali / jaringan lambat** → tombol nonaktif bertuliskan "Mengirim…", hanya satu request. Test di Task 6 (server juga sudah aman dari Fase 2).
5. **Tombol Back setelah sukses** → kembali ke form kosong, tidak mengirim ulang (pola redirect setelah POST). Test di Task 6.
6. **Pengajuan ditolak lalu dicek** → arahan ajukan ulang sesuai jam server (Keputusan 7). Test di Task 7.

---

## Struktur file

```
app/Http/Controllers/Public/{OvertimeFormController,EmployeeLookupController,StatusCheckController}.php
app/Http/Requests/PublicOvertimeRequest.php        # dipakai periksa & kirim
app/Services/OvertimeWorkflow.php                   # + previewPublic()
app/Services/OvertimeLimitChecker.php               # + preview()
bootstrap/app.php, config/trustedproxy (env)        # TRUSTED_PROXIES
routes/web.php
resources/js/app.tsx                                # layout per halaman, tanpa sonner/tooltip global
resources/js/pages/public/{form,success,status}.tsx
resources/js/components/public/{field,date-choice,time-picker,bottom-sheet,typeahead,total-card,error-summary,review-card}.tsx
resources/js/lib/{employee-search,format,time-options}.ts
tests/Feature/Public/*Test.php, resources/js/**/*.test.tsx
```

---

### Task 0: Rapikan dokumen + MySQL di CI

**Files:** `docs/ARCHITECTURE.md`, `.github/workflows/tests.yml`.

- [x] `docs/ARCHITECTURE.md` §4: tambah tabel `overtime_number_counters (date PK, last_number)` beserta alasannya (penghitung nomor per hari, dikunci di transaksi yang sama). Keputusan ini sudah disetujui di Fase 2.
- [x] `docs/ARCHITECTURE.md` §2: sesuaikan struktur `tests/` dengan yang dipakai (`tests/Feature/Services`, `tests/Feature/Workflow`, `tests/Concurrency`).
- [x] CI: tambah job dengan service `mysql:8` yang menjalankan seluruh suite termasuk `tests/Concurrency`, supaya uji race generator nomor dan submit ganda tidak hanya jalan di laptop. Job SQLite yang ada tetap.
- [x] CI hijau. Commit `docs: sync architecture with phase 2` dan `ci: run test suite on mysql 8`.

### Task 1: Pisah bundle publik dari panel

**Files:** `resources/js/app.tsx`, `layouts/*`, `pages/**`.

- [x] Ganti resolver layout global menjadi layout per halaman (`Page.layout = (page) => <PublicLayout>…`). Pindahkan `Toaster` + `TooltipProvider` ke `PanelLayout`.
- [x] Ukur: chunk yang dimuat `GET /` (gzip). Target ≤ 90 KB gzip total JS awal untuk form publik. Catat angka sebelum (147 KB `app.js`) dan sesudah di laporan.
- [x] Test yang ada tetap lulus. Commit `perf(ui): split public bundle from panel`.
- Hasil: JS awal `GET /` 147,2 → **109,8 KB gzip**. Target 90 KB tidak tercapai: `app.js` 108 KB adalah React 19 + Inertia 3 (react-dom, @inertiajs/core + es-toolkit, laravel-precognition), batas bawah stack terkunci. Penentu akhir tetap Lighthouse Performance ≥ 85 di Task 8.
- Aturan untuk komponen publik berikutnya: pakai `clsx`, bukan `cn()`, supaya tailwind-merge (~9 KB gzip) tidak ikut bundle publik.

### Task 2: Endpoint daftar karyawan + proxy tepercaya

**Interfaces — Produces:**
- `GET /api/departments/{department}/employees` → `[{id, nik, name}]` karyawan aktif, urut nama. Departemen nonaktif/tidak ada → 404. Throttle `60,1`.
- `TRUSTED_PROXIES` env → `$middleware->trustProxies(at: ...)` (kosong = tidak ada).

- [x] Test: hanya 3 field; karyawan nonaktif dan departemen lain tidak ikut; departemen nonaktif 404; request ke-61 dalam semenit → 429.
- [x] Implement → PASS → commit.
- Hasil: `App\Http\Middleware\TrustProxies` membaca `config('app.trusted_proxies')` (`TRUSTED_PROXIES`, koma atau `*`); cara cek di produksi dicatat di `docs/DEPLOY.md` §3 dan checklist go-live.

### Task 3: Komponen Typeahead (ARIA combobox, fuse.js)

**Interfaces — Produces:**
- `searchEmployees(list: Employee[], query: string): Employee[]` di `lib/employee-search.ts` — tidak peka huruf besar, cocok di awal kata mana pun, toleran typo 1–2 huruf (fuse.js, `threshold` ditentukan oleh test), maks 8 hasil.
- `<Typeahead departmentId employees value onChange error />` — pola ARIA 1.2 combobox (`role="combobox"`, `aria-expanded`, `aria-controls`, `aria-activedescendant`, listbox `role="option"`), panah atas/bawah, Enter memilih, Escape menutup. Hasil: Nama (body-strong) + NIK (mono, ink-muted). Kosong: "Nama tidak ditemukan. Pastikan departemen sudah benar." Wajib memilih dari daftar (teks bebas tidak dianggap pilihan).

- [x] Test Vitest: "budi", "santo", "santosoo" menemukan Budi Santoso (PRD §8 #4); "BUDI" juga; dua "Agus Setiawan" tampil dengan NIK berbeda dan pilihan mengirim id yang benar; keyboard ↓↓Enter memilih item kedua; ganti departemen → nilai dikosongkan; empty state.
- [x] Implement → PASS → commit.
- Hasil: kata ≥ 3 huruf memakai fuse.js (`fuse.js/basic`, ~7 KB gzip) yang dijangkar di awal kata (`location 0`, `distance 2`, `threshold 0.34`); kata 1–2 huruf hanya cocok persis di awal kata. Hasil persis selalu di atas hasil salah ketik. Typeahead belum dipasang di halaman; dicek di browser 360px pada Task 5.
- Copy baru (perlu persetujuan, dilaporkan di akhir fase): helper "Ketik nama, lalu pilih dari daftar.", "Pilih departemen dulu.", "Memuat daftar nama…", dan "NIK {nik}" setelah nama dipilih.

### Task 4: TimePicker + BottomSheet

**Interfaces — Produces:**
- `<BottomSheet open onOpenChange title>` di atas Radix Dialog: `surface`, radius 6px atas saja, `shadow-float`, overlay `rgba(15,30,46,.4)` tanpa blur, tutup lewat Escape/overlay/tombol "Tutup".
- `<TimePicker label value onChange options anchor />` — tombol 52px menampilkan nilai (mono) atau "Pilih jam"; sheet berisi `role="listbox"` baris 52px; saat dibuka scroll ke `value` atau ke `anchor` (jam server terdekat, dibulatkan ke bawah 15 menit). Istirahat memakai komponen yang sama dengan opsi 0–240 menit (label "0 menit" … "4 jam").
- `timeOptions(): string[]` (96 item "00:00"…"23:45"), `breakOptions(): {value: number, label: string}[]` di `lib/time-options.ts`.

- [ ] Test Vitest: 96 opsi; sheet terbuka memanggil `scrollIntoView` pada opsi anchor bila belum ada nilai, dan pada nilai bila ada; memilih menutup sheet dan memanggil `onChange`; tombol punya label yang menyebut nilai saat ini.
- [ ] Implement → PASS → commit.

### Task 5: Form publik + layar Periksa lagi

**Interfaces — Produces:**
- `GET /` → `public/form` dengan props `departments: {id, name}[]`, `dateChoices: {value: 'TODAY'|'YESTERDAY', label: string}[]` (label "Hari ini (20 Nov 2026)"), `serverTime: "HH:MM"` (anchor picker), `maxGrossMinutes`.
- `PublicOvertimeRequest`: `department_id` int wajib, `employee_id` int wajib, `date_choice` in `DateChoice`, `start_time`/`end_time` `H:i` wajib, `break_minutes` int 0–240, `description` string wajib, `website` (honeypot) harus kosong. Pesan Indonesia (mis. "Pilih departemen.", "Pilih nama dari daftar.").
- `OvertimeWorkflow::previewPublic(OvertimeInput, CarbonImmutable $now): array{date: string, start_at: string, end_at: string, break_minutes: int, total_minutes: int, day_type: string, exceeds_daily_limit: bool, exceeds_weekly_limit: bool, employee: {name, nik}, department: string}` — semua validasi `submitPublic` tanpa menyimpan dan tanpa nomor.
- `OvertimeLimitChecker::preview(int $employeeId, CarbonImmutable $date, CarbonImmutable $startAt, int $minutes, DayType $dayType, ?int $ignoreId = null): array{daily: bool, weekly: bool}`.
- `POST /lembur/periksa` (throttle `10,1`) → redirect back dengan `Inertia::flash('review', …)`; error → field errors.
- Form: urutan field PRD §4.1; total real-time di kartu `primary-soft` (mono 28px + "2 jam 30 menit"); "Lanjut periksa" sticky. Layar periksa: ringkasan + info netral lewat batas (teks BUSINESS_RULES §4; versi mingguan: "Total lembur minggu ini melebihi 18 jam. Pengajuan tetap dikirim dan akan diperiksa supervisor.") + tombol "Kirim lembur" (primary) & "Ubah" (secondary).

- [ ] Test Feature: props form benar pada 11:59 (2 pilihan) dan 12:00 (1 pilihan); periksa 09/11 23:00–02:00 istirahat 30 → review 150 menit, tanggal 09/11; periksa tidak menyimpan apa pun; periksa dengan R6/R7/R8 → error field; honeypot → 422; 11× periksa → 429; info lewat batas harian muncul.
- [ ] Test Vitest: total real-time "2 jam 30 menit (2,5 jam)" untuk 23:00–02:00/30; R2 tampil di bawah Jam selesai tanpa request; ringkasan error muncul bila > 1 error; Review Focus 1 (error `overtime_date` dari server ditampilkan di field Tanggal).
- [ ] Implement → PASS → commit.

### Task 6: Kirim + halaman sukses

**Interfaces — Produces:**
- `POST /lembur` (throttle `10,1`) → `submitPublic` → redirect 303 ke `URL::temporarySignedRoute('public.success', now()->addDay(), ['number' => …])`.
- `GET /lembur/sukses/{number}` (middleware `signed`) → `public/success`: nomor (mono), ringkasan, StatusPill "Menunggu", teks "Lembur terkirim. Menunggu persetujuan supervisor.", tombol "Isi lembur lagi" (ke `/`, form kosong) dan "Cek status" (ke `/status?department=…&employee=…`).

- [ ] Test Feature: kirim → 1 baris PENDING + redirect ke URL bertanda tangan; URL tanpa tanda tangan → 403; nomor tidak ada → 404; kirim dengan data ditolak tidak menyimpan.
- [ ] Test Vitest: tombol Kirim nonaktif + "Mengirim…" selama proses (Review Focus 4).
- [ ] Implement → PASS → commit.

### Task 7: Cek status

**Interfaces — Produces:**
- `GET /status` → `public/status` (props `departments`, query `department`/`employee` opsional untuk prefill).
- `GET /api/status?employee_id=` (throttle `60,1`) → pengajuan karyawan aktif dengan `overtime_date` ≥ hari ini − 60 hari, urut terbaru: `number, date, start, end, total_minutes, status, status_label, reject_reason, can_resubmit`. Tanpa `description`. `can_resubmit` = status DITOLAK dan `overtime_date` ada di `OvertimeDateRule::publicChoices($now)` (Keputusan 7).
- Tampilan: daftar kartu (tanggal, jam, total mono, StatusPill, alasan tolak bila ada, arahan ajukan ulang untuk DITOLAK). Kosong: "Belum ada pengajuan dalam 60 hari terakhir." `StatusPill` dibuat di sini (DESIGN.md §6) dan dipakai ulang di panel.

- [ ] Test Feature: batas 60 hari (hari ke-60 masuk, ke-61 tidak); hanya karyawan itu; field `description` tidak ada; alasan tolak ada; 61× → 429.
- [ ] Test Feature `can_resubmit`: ditolak untuk lembur hari ini → true; lembur kemarin pada 11:59 → true, pada 12:00 → false; lembur 3 hari lalu → false; status selain DITOLAK → false.
- [ ] Test Vitest: StatusPill selalu berisi kata; prefill dari query.
- [ ] Implement → PASS → commit.

### Task 8: Verifikasi & laporan

- [ ] Header publik: aktifkan tautan "Cek status pengajuan" (sekarang halamannya ada).
- [ ] `composer ci:check`, suite MySQL.
- [ ] Uji manual di browser 360px dan 412px: isi form sampai sukses < 1 menit, tanpa scroll horizontal/zoom, keyboard-only, cek status.
- [ ] Lighthouse mobile `/` dan `/status` (build produksi): Accessibility ≥ 95, Performance ≥ 85; kontras AA.
- [ ] `caveman-review`, `caveman-stats`, centang ROADMAP, push, CI hijau, laporan.

---

## Dicatat untuk fase berikutnya (tidak dikerjakan di Fase 3)

Temuan review 2026-11-27. Masuk ke rencana fase terkait; yang butuh keputusan ditanyakan di awal fase itu.

**Fase 4 — Area supervisor**
- **Edit setelah batalkan persetujuan bisa buntu.** `OvertimeWorkflow::update()` memvalidasi tanggal dengan aturan panel (bulan ini + bulan lalu), sedangkan `cancelApproval()` tidak punya batas waktu. Pengajuan 2 bulan lalu yang dibatalkan tidak bisa diedit. Keputusan: (a) batasi batalkan persetujuan ke jendela yang sama, atau (b) saat edit, izinkan tanggal lama bila tanggalnya tidak diubah. Rekomendasi (a), karena koreksi di luar 2 bulan juga mengubah data yang sudah dibayar.
- **Arahan Edit vs Tolak.** Di dialog Tolak, tampilkan petunjuk: bila hanya salah jam/kegiatan, pakai Edit, karena karyawan mungkin tidak bisa mengajukan ulang (Keputusan 7).
- Alasan tolak/batalkan > 500 huruf ditolak dengan pesan, bukan dipotong diam-diam oleh `validReason()`.
- `approve`, `approveMany`, `reject`, `cancelApproval` menerima `CarbonImmutable $now` seperti `submitPublic`, supaya waktu persetujuan bisa dites dengan `setTestNow` secara konsisten.
- Akun nonaktif yang sedang login ditolak dengan pesan (follow-up Fase 2).
- Usulan (butuh persetujuan): daftar persetujuan menampilkan sumber "Dikirim dari form publik" + jam kirim, membantu supervisor mengenali pengajuan atas nama orang lain.

**Fase 5 — Area admin**
- Mengubah hari istirahat mingguan harus memanggil `recalculateDayTypes()` dengan **semua** tanggal pengajuan MENUNGGU, bukan satu tanggal. Tambah test khusus.
- Validasi `HH:MM` di halaman Pengaturan (follow-up Fase 2).

**Fase 6 — Export**
- Belum ada kunci periode. Usulan minimal: saat membatalkan persetujuan di bulan yang sudah diekspor (cek audit log `export`), tampilkan peringatan "Data bulan ini sudah diekspor pada {tanggal}. Perubahan tidak masuk file yang sudah dikirim." Tidak memblok.
