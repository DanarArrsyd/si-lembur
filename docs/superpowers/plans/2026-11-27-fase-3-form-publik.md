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

## Keputusan yang perlu disetujui

1. **Picker jam = bottom sheet** (bukan `<select>` native). Alasan: PRD minta "saat dibuka langsung scroll ke jam terdekat". `<select>` native di Android selalu membuka dari pilihan terpilih/atas; supaya bisa mulai dari jam sekarang kita harus mengisi jam default yang mungkin salah dan lolos tanpa dicek karyawan. Bottom sheet: daftar 96 jam (baris 52px, mono), terbuka di jam server terdekat tanpa mengisi apa pun. Dipakai juga untuk Istirahat (0–4 jam).
2. **"Periksa lagi" divalidasi server.** Tombol "Lanjut periksa" → `POST /lembur/periksa` (semua aturan R1–R8 + info lewat batas, tanpa menyimpan) → layar ringkasan. "Kirim lembur" → `POST /lembur` (validasi ulang + simpan). Info lewat batas harian/mingguan tampil di layar periksa (butuh total lembur lain hari itu/minggu itu yang hanya ada di server). Total jam di form tetap real-time dari `lib/overtime.ts`.
3. **Cek status bisa melihat pengajuan siapa pun** di departemen terpilih (sesuai PRD: tanpa login, pilih departemen → nama). Yang tampil hanya tanggal, jam, total, status, alasan tolak; kegiatan tidak ditampilkan. Mohon konfirmasi ini memang diterima.
4. **Link halaman sukses berlaku 24 jam** (signed URL). Sesudah itu 403 dengan arahan ke Cek status.
5. **Proxy tepercaya lewat `.env`:** `TRUSTED_PROXIES` (default kosong = tidak percaya header `X-Forwarded-For`). Isi bila Hostinger/Cloudflare dipakai di depan aplikasi, supaya throttle per IP memakai IP HP, bukan IP proxy. Perlu dicek saat deploy (Fase 7).
6. **Honeypot terisi** → 422 dengan pesan umum "Pengajuan tidak bisa diproses. Muat ulang halaman lalu coba lagi." (tanpa menyimpan, dicatat di log aplikasi).

## Review Focus

1. **Form dibuka 11:58, "Kemarin" dikirim 12:01** → error di field Tanggal dengan pesan §1.1, pilihan tanggal di halaman diperbarui (Kemarin hilang), isian lain tetap. Test di Task 5.
2. **Departemen diganti setelah nama dipilih** → nama dikosongkan, daftar karyawan dimuat ulang. Test di Task 3.
3. **Dua karyawan bernama sama** → keduanya tampil dengan NIK, yang terkirim `employee_id` yang dipilih. Test di Task 3.
4. **Tombol Kirim ditekan dua kali / jaringan lambat** → tombol nonaktif bertuliskan "Mengirim…", hanya satu request. Test di Task 6 (server juga sudah aman dari Fase 2).
5. **Tombol Back setelah sukses** → kembali ke form kosong, tidak mengirim ulang (pola redirect setelah POST). Test di Task 6.

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

### Task 1: Pisah bundle publik dari panel

**Files:** `resources/js/app.tsx`, `layouts/*`, `pages/**`.

- [x] Ganti resolver layout global menjadi layout per halaman (`Page.layout = (page) => <PublicLayout>…`). Pindahkan `Toaster` dan `TooltipProvider` ke `PanelLayout`.
- [x] Ukur JavaScript awal yang dimuat `GET /`. Jumlah ukuran gzip untuk ketiga chunk turun dari 151.085 menjadi 112.449 byte (25,6%). Browser mengonfirmasi chunk login dan panel hanya dimuat saat halamannya dibuka. Wordmark memakai kelas statis sehingga halaman publik tidak perlu memuat `tailwind-merge`.
- [ ] Capai target ≤ 90 kB gzip JavaScript awal. Chunk utama yang memuat React dan Inertia sendiri berukuran 110.862 byte gzip. Ukur kembali setelah form publik tersedia.
- [x] Test yang ada tetap lulus. Commit `perf(ui): split public bundle from panel`.

### Task 2: Endpoint daftar karyawan + proxy tepercaya

**Interfaces — Produces:**
- `GET /api/departments/{department}/employees` → `[{id, nik, name}]` karyawan aktif, urut nama. Departemen nonaktif/tidak ada → 404. Throttle `60,1`.
- `TRUSTED_PROXIES` menerima daftar IP atau CIDR yang dipisahkan koma. Middleware bawaan Laravel membacanya dari `config/trustedproxy.php`. Nilai kosong menghasilkan daftar tanpa proxy tepercaya.

- [x] Test: respons hanya memuat tiga field karyawan aktif, urut nama. Departemen nonaktif, tidak tersedia, atau ID tidak valid menghasilkan 404. Request ke-61 dalam semenit dari IP yang sama menghasilkan 429, termasuk saat berganti departemen.
- [x] Test: alamat klien dari header diterima jika koneksi datang dari proxy terkonfigurasi. Tanpa proxy tepercaya, middleware memakai IP koneksi.
- [x] Implementasi, verifikasi, dan commit.

### Task 3: Komponen Typeahead (ARIA combobox, fuse.js)

**Interfaces — Produces:**
- `searchEmployees(list: Employee[], query: string): Employee[]` di `lib/employee-search.ts` — tidak peka huruf besar, cocok di awal kata mana pun, toleran typo 1–2 huruf (fuse.js, `threshold` ditentukan oleh test), maks 8 hasil.
- `<Typeahead departmentId employees value onChange error />` — pola ARIA 1.2 combobox (`role="combobox"`, `aria-expanded`, `aria-controls`, `aria-activedescendant`, listbox `role="option"`), panah atas/bawah, Enter memilih, Escape menutup. Hasil: Nama (body-strong) + NIK (mono, ink-muted). Kosong: "Nama tidak ditemukan. Pastikan departemen sudah benar." Wajib memilih dari daftar (teks bebas tidak dianggap pilihan).

- [x] Test Vitest: pencarian awalan, huruf besar, satu dan dua typo, batas delapan hasil, nama kembar dengan NIK, pemilihan lewat keyboard, ganti departemen, dan hasil kosong. Teks bebas tidak menetapkan ID karyawan.
- [x] Implementasi, verifikasi, dan commit. Gunakan build `fuse.js/basic`. Pratinjau komponen diuji dengan sentuhan di 360 px dan keyboard di 1280 px. Integrasi dengan form dan pemuatan daftar departemen dilakukan di Task 5.

### Task 4: TimePicker + BottomSheet

**Interfaces — Produces:**
- `<BottomSheet open onOpenChange title>` di atas Radix Dialog: `surface`, radius 6px atas saja, `shadow-float`, overlay `rgba(15,30,46,.4)` tanpa blur, tutup lewat Escape/overlay/tombol "Tutup".
- `<TimePicker label value onChange options anchor />` — tombol 52px menampilkan nilai (mono) atau "Pilih jam"; sheet berisi `role="listbox"` baris 52px; saat dibuka scroll ke `value` atau ke `anchor` (jam server terdekat, dibulatkan ke bawah 15 menit). Istirahat memakai komponen yang sama dengan opsi 0–240 menit (label "0 menit" … "4 jam").
- `timeOptions(): string[]` (96 item "00:00"…"23:45"), `breakOptions(): {value: number, label: string}[]` di `lib/time-options.ts`.

- [x] Test Vitest: 96 opsi jam dan 17 opsi istirahat. Saat dibuka, daftar bergulir ke pilihan yang ada atau jam server yang dibulatkan ke bawah. Tes mencakup nama kontrol dan nilainya, pemilihan dari daftar, navigasi keyboard, serta pengembalian fokus.
- [x] Implementasi, verifikasi, dan commit. Pratinjau komponen diuji dengan sentuhan di 360 px dan keyboard di 1280 px. Integrasi ke form publik dilakukan di Task 5.

### Task 5: Form publik + layar Periksa lagi

**Interfaces — Produces:**
- `GET /` → `public/form` dengan props `departments: {id, name}[]`, `dateChoices: {value: 'TODAY'|'YESTERDAY', label: string}[]` (label "Hari ini (20 Nov 2026)"), `serverTime: "HH:MM"` (anchor picker), `maxGrossMinutes`.
- `PublicOvertimeRequest`: `department_id` int wajib, `employee_id` int wajib, `date_choice` in `DateChoice`, `start_time`/`end_time` `H:i` wajib, `break_minutes` int 0–240, `description` string wajib, `website` (honeypot) harus kosong. Pesan Indonesia (mis. "Pilih departemen.", "Pilih nama dari daftar.").
- `OvertimeWorkflow::previewPublic(OvertimeInput, CarbonImmutable $now): array{date: string, start_at: string, end_at: string, break_minutes: int, total_minutes: int, day_type: string, exceeds_daily_limit: bool, exceeds_weekly_limit: bool, employee: {name, nik}, department: string}` — semua validasi `submitPublic` tanpa menyimpan dan tanpa nomor.
- `OvertimeLimitChecker::preview(int $employeeId, CarbonImmutable $date, CarbonImmutable $startAt, int $minutes, DayType $dayType, ?int $ignoreId = null): array{daily: bool, weekly: bool}`.
- `POST /lembur/periksa` (throttle `10,1`) → redirect back dengan `Inertia::flash('review', …)`; error → field errors.
- Form: urutan field PRD §4.1; total real-time di kartu `primary-soft` (mono 28px + "2 jam 30 menit"); "Lanjut periksa" sticky. Layar periksa: ringkasan + info netral lewat batas (teks BUSINESS_RULES §4; versi mingguan: "Total lembur minggu ini melebihi 18 jam. Pengajuan tetap dikirim dan akan diperiksa supervisor.") + tombol "Kirim lembur" (primary) & "Ubah" (secondary).

- [x] Tes Feature mencakup pilihan tanggal sebelum dan sesudah cutoff, hitung lintas tengah malam, validasi aturan bisnis, honeypot (422), throttle per IP (429), dan peringatan sesuai pengaturan. Pemeriksaan tidak membuat pengajuan, audit, atau nomor.
- [x] Tes Vitest mencakup total 150 menit, pesan jam selesai, ringkasan beberapa error, dan pemulihan isian setelah cutoff atau kembali mengubah ringkasan.
- [x] Implementasi, verifikasi, dan commit. Form menyertakan tanggal yang ditampilkan agar server bisa meminta pilihan ulang setelah pergantian hari. Validasi publik memakai jam server untuk memastikan lembur sudah selesai. Tombol "Kirim lembur" diaktifkan pada Task 6.
- Build produksi pada Task 5 memuat 144.625 byte gzip JavaScript awal untuk form lengkap. Target 90 kB pada Task 1 masih terbuka.

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
- `GET /api/status?employee_id=` (throttle `60,1`) → pengajuan karyawan aktif dengan `overtime_date` ≥ hari ini − 60 hari, urut terbaru: `number, date, start, end, total_minutes, status, status_label, reject_reason`. Tanpa `description`.
- Tampilan: daftar kartu (tanggal, jam, total mono, StatusPill, alasan tolak bila ada). Kosong: "Belum ada pengajuan dalam 60 hari terakhir." `StatusPill` dibuat di sini (DESIGN.md §6) dan dipakai ulang di panel.

- [ ] Test Feature: batas 60 hari (hari ke-60 masuk, ke-61 tidak); hanya karyawan itu; field `description` tidak ada; alasan tolak ada; 61× → 429.
- [ ] Test Vitest: StatusPill selalu berisi kata; prefill dari query.
- [ ] Implement → PASS → commit.

### Task 8: Verifikasi & laporan

- [ ] Header publik: aktifkan tautan "Cek status pengajuan" (sekarang halamannya ada).
- [ ] `composer ci:check`, suite MySQL.
- [ ] Uji manual di browser 360px dan 412px: isi form sampai sukses < 1 menit, tanpa scroll horizontal/zoom, keyboard-only, cek status.
- [ ] Lighthouse mobile `/` dan `/status` (build produksi): Accessibility ≥ 95, Performance ≥ 85; kontras AA.
- [ ] `caveman-review`, `caveman-stats`, centang ROADMAP, push, CI hijau, laporan.
