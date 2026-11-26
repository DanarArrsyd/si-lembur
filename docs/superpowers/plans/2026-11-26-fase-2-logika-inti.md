# Fase 2 — Logika inti (TDD): Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (native, chosen in Fase 1) and superpowers:test-driven-development for every task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Semua aturan `docs/BUSINESS_RULES.md` berjalan di service PHP yang teruji, plus mirror kalkulator di TypeScript untuk preview. Belum ada controller/halaman (Fase 3–5).

**Architecture:** Service kecil satu tanggung jawab di `app/Services`, dipanggil oleh `OvertimeWorkflow` yang membungkus setiap transisi dalam transaksi DB + audit log. Pelanggaran aturan dilempar sebagai `ValidationException` dengan key field form, supaya Form Request/Inertia di Fase 3–4 bisa langsung menampilkannya di bawah field. Transisi status yang tidak sah dilempar sebagai `InvalidOvertimeTransition`.

**Tech Stack:** Laravel 13, Pest 4 (`Carbon::setTestNow`), Vitest. Test DB: SQLite; test race dan suite lengkap juga dijalankan di MySQL 8.

**Spec:** `docs/BUSINESS_RULES.md` (utama), `docs/PRD.md` §3, `docs/ARCHITECTURE.md` §2, §4, §5.

## Global Constraints

- Waktu: server `Asia/Jakarta` (`now()`), tidak pernah jam browser.
- Menit disimpan sebagai integer. Tanpa float di PHP.
- Query kolom `date` selalu `whereDate(...)` (SQLite menyimpan `Y-m-d 00:00:00`).
- Status yang dihitung untuk overlap & batas: `PENDING` + `APPROVED`. `REJECTED` diabaikan.
- Minggu = Senin–Minggu, berdasarkan `overtime_date` (tanggal mulai).
- Tidak ada hard delete. Setiap transisi = 1 baris `audit_logs`.
- Pesan error persis dari `BUSINESS_RULES.md` §2.1 dan §1.1. Pesan yang belum ada di dokumen: lihat "Teks usulan" di bawah.
- `declare(strict_types=1)`, PHPStan level 7, Pint bersih. TS strict, tanpa `any`.

## Keputusan yang perlu disetujui

1. **Arti flag `exceeds_weekly_limit`.** Dokumen tidak menyebut pengajuan mana yang diberi tanda. Usulan: **kumulatif berurutan** — urutkan pengajuan minggu itu menurut `start_at`; pengajuan diberi tanda bila total berjalan sampai pengajuan itu > 18 jam. Pengajuan awal minggu yang masih di bawah batas tidak ditandai. Alternatif: semua pengajuan di minggu itu ditandai begitu total minggu > 18 jam. Flag harian sama: pengajuan di tanggal yang totalnya > 4 jam ditandai semua (satu tanggal biasanya 1–2 pengajuan).
2. **Tabel baru `overtime_number_counters`** (`date` PK, `last_number`) untuk nomor `LBR-YYYYMMDD-NNNN` yang aman dari race. Tidak ada di `ARCHITECTURE.md` §4. Alternatif `SELECT ... FOR UPDATE` pada `overtimes` rawan deadlock gap-lock di InnoDB saat belum ada baris hari itu.
3. **Teks usulan** untuk pesan yang belum ada di dokumen:
   - Kegiatan > 500 huruf: "Kegiatan lembur maksimal 500 huruf."
   - Publik, tanggal selain hari ini/kemarin: "Tanggal lembur hanya bisa hari ini atau kemarin."
   - Panel, tanggal maju: "Tanggal lembur tidak boleh melewati hari ini."
   - Panel, terlalu mundur: "Tanggal lembur hanya bisa di bulan ini atau bulan sebelumnya."
   - Edit/setujui/tolak status yang salah: "Pengajuan ini sudah {status}. Muat ulang halaman."
   - Alasan tolak/batalkan kosong: "Tulis alasan minimal 5 huruf."
4. **Edit tidak bisa mengganti karyawan.** Yang bisa diubah: tanggal, jam, istirahat, kegiatan. Salah orang → tolak, lalu input ulang.
5. **Batalkan persetujuan mengosongkan `approved_by`/`approved_at`.** Riwayat siapa yang dulu menyetujui tetap ada di audit log.

## Review Focus

1. **Dobel tap / dua HP mengirim lembur yang sama bersamaan** → hanya satu tersimpan, yang lain kena R6. Ditangani dengan `lockForUpdate` baris karyawan di dalam transaksi submit/edit. Test di Task 9 (MySQL, proses paralel).
2. **Edit memindahkan pengajuan ke minggu lain** → flag minggu lama *dan* minggu baru dihitung ulang. Test di Task 9.
3. **Lembur Minggu 23:00–02:00 Senin** → masuk minggu & tanggal Minggu (tanggal mulai), tidak terhitung ke minggu berikutnya. Test di Task 6.
4. **Batas tepat 4 jam / tepat 18 jam** → tidak ditandai (aturan "melebihi"). Test di Task 6.
5. **Kegiatan berisi spasi saja / `"  ab  "`** → dihitung setelah `trim`, ditolak R8. Test di Task 3.

---

## Struktur file

```
app/Exceptions/InvalidOvertimeTransition.php
app/Enums/DateChoice.php                  # TODAY | YESTERDAY (form publik)
app/Services/
  Settings.php                            # akses bertipe + cache, DEFAULTS pindah ke sini
  OvertimeTimes.php                       # value object hasil kalkulator
  OvertimeCalculator.php                  # §2, R1–R4
  OvertimeDateRule.php                    # §1, R7
  DayTypeResolver.php                     # §3
  OvertimeLimitChecker.php                # §4
  OverlapChecker.php                      # §5, R6
  OvertimeNumberGenerator.php             # LBR-YYYYMMDD-NNNN
  AuditLogger.php
  OvertimeInput.php                       # DTO input form (publik & panel)
  OvertimeWorkflow.php                    # §6
database/migrations/2026_11_27_000001_create_overtime_number_counters_table.php
database/seeders/SettingSeeder.php        # pakai Settings::DEFAULTS
resources/js/lib/overtime.ts (+ .test.ts)
tests/Unit/Services/*Test.php, tests/Feature/Workflow/*Test.php
```

---

### Task 1: `Settings`

**Interfaces — Produces** (`App\Services\Settings`, singleton):
- `const DEFAULTS` = nilai `SettingSeeder::DEFAULTS` sekarang (seeder ganti memakai konstanta ini).
- `weeklyRestDays(): list<int>` (ISO 1–7), `yesterdayCutoff(): string` (`"H:i"`), `dailyLimitMinutes(): int`, `weeklyLimitMinutes(): int`, `maxGrossMinutes(): int` (jam × 60).
- `set(string $key, mixed $value): void` → simpan + hapus cache.
- Cache: `Cache::rememberForever('settings.all', ...)`; key yang belum ada di DB jatuh ke `DEFAULTS`.

- [ ] Test: nilai default tanpa baris DB; nilai DB menimpa default; `set()` langsung terbaca (cache dibersihkan); `maxGrossMinutes() === 960` default.
- [ ] Implement → PASS → commit `feat(settings): add typed settings service`.

### Task 2: `OvertimeCalculator` (C1–C7)

**Interfaces — Produces:**
- `OvertimeTimes` (readonly): `CarbonImmutable $startAt`, `CarbonImmutable $endAt`, `int $grossMinutes`, `int $breakMinutes`, `int $totalMinutes`.
- `OvertimeCalculator::calculate(CarbonImmutable $date, string $start, string $end, int $breakMinutes): OvertimeTimes` — `$start`/`$end` format `"H:i"`. Lempar `ValidationException`:
  - R1 → key `start_time` / `end_time` / `break_minutes` (yang salah): "Jam harus kelipatan 15 menit."
  - R2 → `end_time`: "Jam selesai tidak boleh sama dengan jam mulai."
  - R3 → `break_minutes`: "Istirahat harus lebih singkat dari lama lembur."
  - R4 → `end_time`: "Lama lembur melebihi batas wajar. Periksa jam mulai dan jam selesai."
- Format jam salah (`"25:00"`, `"8:00"`) → R1 di field itu. Istirahat > 240 menit → `break_minutes` "Istirahat maksimal 4 jam." (PRD §4.1 picker 0–4 jam).

- [ ] Test dataset C1–C7 persis tabel §7.1 (C1: `endAt` = `2026-11-10 02:00`, total 150; C4 R3; C5 R2; C6 R1 di `start_time`; C7 R4, 17 jam). Tambah: istirahat 10 menit → R1 `break_minutes`.
- [ ] Implement → PASS → commit.

### Task 3: Validasi input dasar — R5, R8

**Interfaces — Produces** (`OvertimeInput`, readonly):
- `int $employeeId`, `int $departmentId`, `?DateChoice $dateChoice` (publik), `?CarbonImmutable $date` (panel), `string $startTime`, `string $endTime`, `int $breakMinutes`, `string $description`.
- `OvertimeInput::description()` mengembalikan teks yang sudah di-`trim`.
- Validasi R5 dan R8 ada di `OvertimeWorkflow` (Task 9) lewat method privat; test-nya di sini lewat workflow agar tidak ada service kosong:
  - R5 → `employee_id`: "Nama tidak ditemukan di departemen ini." (karyawan nonaktif, beda departemen, atau departemen nonaktif).
  - R8 → `description`: < 5 huruf setelah trim → "Tulis kegiatan lembur minimal 5 huruf."; > 500 → teks usulan.

- [ ] Test ditulis di `tests/Feature/Workflow/SubmitValidationTest.php` dan dijalankan bersama Task 9 (dicatat di sini agar Review Focus 5 jelas pemiliknya): `"  ab  "` ditolak, `"Packing"` diterima, 501 huruf ditolak, karyawan nonaktif ditolak, karyawan dept B dipilih di dept A ditolak.

### Task 4: `OvertimeDateRule` (D1–D6)

**Interfaces — Produces:**
- `publicChoices(CarbonImmutable $now): list<array{choice: DateChoice, date: CarbonImmutable}>` — `YESTERDAY` hanya bila `$now` < cutoff hari itu.
- `resolvePublic(DateChoice $choice, CarbonImmutable $now): CarbonImmutable` — lempar `ValidationException` key `overtime_date`: lewat cutoff → "Batas input lembur kemarin sudah lewat ({cutoff}). Hubungi supervisor untuk input lembur kemarin."
- `assertPanelDate(CarbonImmutable $date, CarbonImmutable $now): void` — boleh dari tanggal 1 bulan sebelumnya s.d. hari ini.
- Publik tidak pernah menerima tanggal bebas; tanggal maju (D4) mustahil lewat `DateChoice`, jadi D4 diuji di jalur panel dan lewat `publicChoices` (tidak ada pilihan besok).

- [ ] Test D1–D6 dengan `Carbon::setTestNow` (tahun 2026, Asia/Jakarta). Tambah: cutoff diubah ke `"10:00"` lewat `Settings::set` → 10:00 ditolak; panel 05/01/2027 → 15/12/2026 diterima (lintas tahun).
- [ ] Implement → PASS → commit.

### Task 5: `DayTypeResolver` (H1–H2)

**Interfaces — Produces:**
- `resolve(CarbonImmutable $date): DayType` — `HARI_LIBUR` bila `$date->dayOfWeekIso` ada di `weeklyRestDays()` atau `Holiday::whereDate('date', $date)` ada.

- [ ] Test H1 (Minggu 08/11/2026), H2 (holiday di Selasa), Sabtu = `HARI_KERJA` dengan default, `weekly_rest_days = [6,7]` → Sabtu `HARI_LIBUR`.
- [ ] Implement → PASS → commit.

### Task 6: `OvertimeLimitChecker` (H3–H5)

**Interfaces — Produces:**
- `recalculateWeek(int $employeeId, CarbonImmutable $anyDateInWeek): void` — ambil semua pengajuan `PENDING`/`APPROVED` karyawan itu dengan `overtime_date` Senin–Minggu minggu tersebut; set ulang `exceeds_daily_limit` dan `exceeds_weekly_limit` (`HARI_LIBUR` selalu `false`; `REJECTED` tidak disentuh). Algoritma mingguan sesuai Keputusan 1.
- Simpan dengan `saveQuietly` hanya bila nilai berubah.

- [ ] Test: H3 (4,5 jam satu hari → daily true); tepat 4 jam → false; H4 (Senin–Sabtu total 18,5 jam → pengajuan yang melewati 18 jam true, yang sebelumnya false); tepat 18 jam → false; H1 (Minggu 6 jam HARI_LIBUR → keduanya false, tidak dihitung ke total minggu); lembur Minggu 23:00–02:00 ikut minggu Minggu itu; H5 (pengajuan penyebab ditolak → recalc → flag lain kembali false).
- [ ] Implement → PASS → commit.

### Task 7: `OverlapChecker` (O1–O4)

**Interfaces — Produces:**
- `findConflict(int $employeeId, CarbonImmutable $startAt, CarbonImmutable $endAt, ?int $ignoreId = null): ?Overtime` — kondisi `start_at < :end AND :start < end_at`, status `PENDING`/`APPROVED`.
- `assertNoConflict(...)` → `ValidationException` key `start_time`: "Sudah ada lembur {number} di jam yang sama."

- [ ] Test O1–O4 persis §7.4 + edit diri sendiri (`ignoreId`) tidak bentrok dengan dirinya.
- [ ] Implement → PASS → commit.

### Task 8: `OvertimeNumberGenerator` + `AuditLogger`

**Interfaces — Produces:**
- Migrasi `overtime_number_counters`: `date` DATE primary, `last_number` unsigned int.
- `OvertimeNumberGenerator::next(CarbonImmutable $now): string` — **wajib dipanggil di dalam transaksi**: `insertOrIgnore` baris tanggal, `lockForUpdate`, increment, kembalikan `LBR-{Ymd}-{%04d}`. Tanggal = tanggal dibuat (bukan tanggal lembur).
- `AuditLogger::log(string $action, ?Model $auditable, ?array $before = null, ?array $after = null, ?string $reason = null, ?User $user = null): AuditLog` — isi `ip`/`user_agent` dari `request()` bila ada (dipotong 500 char).
- Nama action: `overtime.submitted`, `overtime.created_by_supervisor`, `overtime.updated`, `overtime.approved`, `overtime.rejected`, `overtime.approval_cancelled`.

- [ ] Test: nomor pertama `LBR-20261109-0001`, kedua `-0002`, tanggal berikutnya mulai `0001` lagi. Test paralel (grup `mysql`, skip di SQLite): 5 proses `pcntl_fork`, masing-masing 10 nomor → 50 nomor unik tanpa celah.
- [ ] Test AuditLogger: menyimpan user, before/after, ip.
- [ ] Implement → PASS → commit.

### Task 9: `OvertimeWorkflow` (§6)

**Interfaces — Produces** (setiap method: `DB::transaction`, audit log, `recalculateWeek` untuk minggu terkait):
- `submitPublic(OvertimeInput $input, CarbonImmutable $now): Overtime` → `PENDING`, `source PUBLIC`, simpan `submitted_ip`/`user_agent`.
- `submitBySupervisor(User $actor, OvertimeInput $input, CarbonImmutable $now): Overtime` → `APPROVED`, `source SUPERVISOR`, `created_by` = `approved_by` = actor, `approved_at` = now. Otorisasi: `canAccessDepartment($input->departmentId)` else 403.
- `update(User $actor, Overtime $o, OvertimeInput $input, CarbonImmutable $now): Overtime` → hanya `PENDING`; validasi ulang semua aturan dengan `ignoreId`; hitung ulang minggu lama & baru.
- `approve(User $actor, Overtime $o): Overtime` → `PENDING` → `APPROVED`.
- `approveMany(User $actor, list<int> $ids): Collection<int, Overtime>` → cek Policy per ID; satu ID tanpa akses → 403 dan tidak ada yang tersimpan; 1 audit log per record.
- `reject(User $actor, Overtime $o, string $reason): Overtime` → `PENDING` → `REJECTED`, alasan wajib (≥ 5 huruf, key `reason`).
- `cancelApproval(User $actor, Overtime $o, string $reason): Overtime` → `APPROVED` → `PENDING`, alasan wajib.
- `recalculateDayTypes(list<CarbonImmutable> $dates): int` → `day_type` pengajuan `PENDING` di tanggal itu dihitung ulang, `APPROVED` tidak; flag minggu terkait dihitung ulang. (Dipanggil dari halaman kalender di Fase 5.)
- Urutan submit/edit: lock baris karyawan (`lockForUpdate`) → R5 → R8 → tanggal (R7) → kalkulator (R1–R4) → overlap (R6) → day type → nomor → simpan → `recalculateWeek` → audit.
- Otorisasi aksi pada pengajuan memakai `Gate::forUser($actor)->authorize(ability, $o)`; status salah → `InvalidOvertimeTransition` (pesan usulan).
- `OvertimePolicy`: tetap scoping departemen (status dicek di workflow, satu sumber).

- [ ] Test per transisi (tabel §6): status, kolom actor/waktu, audit log 1 baris dengan before/after/reason, `source`.
- [ ] Test acceptance PRD §8: #2 (09/11 23:00–02:00 istirahat 30 → 150 menit, tanggal 09/11), #5, #6 (supervisor A ke data B → `AuthorizationException`, termasuk bulk campuran), #7, #8, #9, #10.
- [ ] Test Review Focus 1 (grup `mysql`): 2 proses fork submit lembur sama → 1 tersimpan, 1 kena R6. Review Focus 2: edit pindah minggu → flag kedua minggu benar.
- [ ] Test `SubmitValidationTest` dari Task 3.
- [ ] Implement → PASS → commit.

### Task 10: `resources/js/lib/overtime.ts`

**Interfaces — Produces:**
- `calculateOvertime(start: string, end: string, breakMinutes: number, maxGrossMinutes = 960): { ok: true; grossMinutes: number; totalMinutes: number; crossesMidnight: boolean } | { ok: false; rule: 'R1' | 'R2' | 'R3' | 'R4'; message: string }` — pesan sama persis dengan PHP.
- `formatDuration(minutes: number): string` → `150` → `"2 jam 30 menit"`, `180` → `"3 jam"`, `45` → `"45 menit"`.
- `formatHours(minutes: number): string` → `"2,5 jam"`, `"3 jam"`, `"2,25 jam"` (koma desimal, tanpa nol di belakang).

- [ ] Test Vitest C1–C7 (dataset sama dengan PHP) + format di atas.
- [ ] Implement → PASS → commit.

### Task 11: Verifikasi & laporan

- [ ] `composer ci:check` (SQLite) dan `php artisan test` di MySQL (termasuk grup `mysql`) → semua PASS.
- [ ] `caveman-review` diff fase, `caveman-stats`.
- [ ] Centang ROADMAP Fase 2, push, CI hijau, laporan format ROADMAP.
