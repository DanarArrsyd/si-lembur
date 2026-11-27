# Architecture — SI-LEMBUR

## 1. Stack

| Lapisan | Pilihan | Alasan |
|---|---|---|
| Backend | Laravel stabil terbaru (min. 12), PHP 8.3 | Auth, policy, validasi, Excel matang; jalan di shared hosting |
| Frontend | Inertia.js + React + TypeScript (Vite) | SPA-feel tanpa API terpisah; picker & typeahead mulus di HP |
| Styling | Tailwind CSS, token dari `DESIGN.md` dipetakan ke CSS variables | Konsisten dengan Industrial Blue |
| Auth | Starter kit resmi Laravel React, registrasi dimatikan | Standar, aman |
| DB | MySQL 8 / MariaDB 10.6+ | Yang tersedia di Hostinger shared. Hindari fitur yang hanya ada di salah satunya |
| Excel | PhpSpreadsheet | Bisa load template `.xlsx` + pertahankan format |
| Fuzzy search | `fuse.js` di client | 150 nama, cukup di browser |
| Ikon | `lucide-react` | Sesuai design system |
| Cache/session/queue | `database` / `file`, queue `sync` | Tidak ada Redis & worker di shared hosting |
| Test | Pest (PHP), Vitest (TS) | – |
| Dev lokal | Opsional Docker Compose (PHP + MySQL) | Hanya lokal |

`.env`: `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`.

## 2. Struktur folder (inti)

```
app/
  Enums/            OvertimeStatus, OvertimeSource, DayType, UserRole
  Http/
    Controllers/
      Public/       OvertimeFormController, StatusCheckController, EmployeeLookupController
      Panel/        DashboardController, ApprovalController, OvertimeController,
                    ExportController, EmployeeController, DepartmentController,
                    UserController, HolidayController, SettingController, AuditLogController
    Requests/       StorePublicOvertimeRequest, StorePanelOvertimeRequest, RejectRequest, ...
    Middleware/     EnsureRole
  Models/           Department, Employee, User, Overtime, Holiday, Setting, AuditLog
  Policies/         OvertimePolicy, EmployeePolicy, ...
  Services/
    OvertimeCalculator.php     # start_at, end_at, gross, total (BUSINESS_RULES §2)
    OvertimeDateRule.php       # aturan hari ini / kemarin / mundur (§1)
    DayTypeResolver.php        # §3
    OvertimeLimitChecker.php   # §4, termasuk hitung ulang flag
    OverlapChecker.php         # §5
    OvertimeNumberGenerator.php
    OvertimeWorkflow.php       # submit, approve, reject, cancelApproval, edit (§6)
    OvertimeExporter.php       # template Excel
    EmployeeImporter.php
    AuditLogger.php
    Settings.php               # akses typed ke tabel settings (cached)
resources/js/
  pages/public/     Form.tsx, Success.tsx, Status.tsx
  pages/panel/      Dashboard.tsx, Approvals.tsx, Overtimes/*, Employees/*, ...
  components/ui/    Button, Field, Select, TimePicker, Typeahead, StatusPill, KpiTile,
                    DataTable, MobileCardList, BottomSheet, ConfirmDialog
  lib/              overtime.ts (preview hitung, mirror OvertimeCalculator), format.ts
  styles/           tokens.css (dari DESIGN.md)
tests/
  Unit/             Enum dan logika murni tanpa database
  Feature/
    Services/       Calculator, DateRule, DayType, Limit, Overlap, NumberGenerator, Settings, AuditLogger
    Workflow/       Submit publik & supervisor, transisi status, edit + hitung ulang
    Authorization/  Policy dan middleware role
    Public/, Panel/ Controller per halaman (Public mulai Fase 3, Panel mulai Fase 4)
  Concurrency/      Uji race dengan proses paralel; hanya jalan di MySQL/MariaDB (skip di SQLite)
```

## 3. Rute

| Method | Path | Akses | Fungsi |
|---|---|---|---|
| GET | `/` | Publik | Form lembur |
| POST | `/lembur/periksa` | Publik, throttle | Validasi dan ringkasan tanpa menyimpan |
| POST | `/lembur` | Publik, throttle | Submit |
| GET | `/lembur/sukses/{number}` | Publik (signed URL) | Halaman sukses |
| GET | `/status` | Publik | Cek status |
| GET | `/api/departments/{id}/employees` | Publik, throttle | Daftar karyawan aktif (`id`, `nik`, `name` saja) |
| GET | `/api/status` | Publik, throttle | Pengajuan 60 hari terakhir per karyawan |
| GET/POST | `/login`, `/logout` | – | Auth |
| * | `/panel/...` | `auth` + role | Area supervisor & admin |

## 4. Skema database

```
departments
  id, code (unique), name, is_active, timestamps

employees
  id, nik (unique), name, department_id FK, is_active, timestamps
  index (department_id, is_active)

users
  id, name, username (unique), email nullable, password,
  role ENUM('ADMIN','SUPERVISOR'), department_id FK nullable,
  is_active, last_login_at, remember_token, timestamps
  -- SUPERVISOR wajib punya department_id; 1 dept maks 1 SUPERVISOR aktif.
  -- Karena MySQL/MariaDB tidak punya partial unique index yang sama,
  -- tegakkan di aplikasi (Form Request + lock transaksi) DAN dengan kolom
  -- generated `active_supervisor_department_id` (= department_id bila role=SUPERVISOR
  -- dan is_active=1, selain itu NULL) + UNIQUE index di kolom itu.

overtimes
  id, number (unique), employee_id FK, department_id FK (snapshot),
  overtime_date DATE, start_at DATETIME, end_at DATETIME,
  break_minutes SMALLINT, total_minutes SMALLINT,
  day_type ENUM('HARI_KERJA','HARI_LIBUR'),
  exceeds_daily_limit BOOL, exceeds_weekly_limit BOOL,
  description VARCHAR(500),
  status ENUM('PENDING','APPROVED','REJECTED'),
  source ENUM('PUBLIC','SUPERVISOR'),
  submitted_ip, submitted_user_agent,
  created_by FK nullable, approved_by FK nullable, approved_at nullable,
  rejected_by FK nullable, rejected_at nullable, reject_reason nullable,
  timestamps
  index (employee_id, start_at, end_at)
  index (department_id, status, overtime_date)

overtime_number_counters
  date DATE (PK), last_number INT UNSIGNED
  -- Penghitung nomor LBR-YYYYMMDD-NNNN per tanggal dibuat. Satu UPSERT atomik di dalam
  -- transaksi yang sama dengan penyimpanan pengajuan: baris tanggal itu terkunci sampai commit,
  -- dan nomor ikut batal bila penyimpanan gagal. Dipakai karena pola INSERT IGNORE lalu
  -- SELECT ... FOR UPDATE deadlock di InnoDB.

holidays
  id, date (unique), name, timestamps

settings
  key (PK), value JSON
  -- weekly_rest_days [7], yesterday_cutoff_time "12:00",
  -- daily_limit_hours 4, weekly_limit_hours 18, max_gross_hours 16

audit_logs
  id, user_id FK nullable, action, auditable_type, auditable_id,
  before JSON nullable, after JSON nullable, reason nullable,
  ip, user_agent, created_at
  index (auditable_type, auditable_id), index (created_at)
```

Simpan datetime sebagai waktu lokal `Asia/Jakarta` (konsisten dengan `APP_TIMEZONE`). Tidak ada multi-timezone.

## 5. Keamanan

**Form publik**
- Periksa dan kirim masing-masing memakai kuota 10/menit per IP. Lookup karyawan dan cek status masing-masing memakai kuota tersendiri 60/menit per IP. Beberapa HP dapat berbagi IP pada Wi-Fi pabrik.
- Honeypot field + CSRF.
- Endpoint karyawan hanya mengembalikan `id`, `nik`, `name` karyawan aktif. Cek status mengembalikan riwayat karyawan aktif dalam rentang sampai 60 hari ke belakang, tanpa kegiatan lembur.
- Halaman sukses memakai signed URL agar nomor pengajuan orang lain tidak bisa ditebak-buka.

**Area login**
- Throttle login 5/menit per username + IP. Akun nonaktif ditolak.
- Session cookie `secure`, `httponly`, `samesite=lax`. HTTPS wajib di produksi.
- Middleware role + **Policy di setiap aksi**. Query supervisor selalu discoping `department_id = auth()->user()->department_id` (global scope atau query builder khusus), dan Policy tetap dicek walau scope ada.
- Bulk approve: validasi setiap ID lewat Policy, bukan hanya filter di client.

**Data**
- Semua aturan bisnis di Form Request + Service (lihat `BUSINESS_RULES.md`).
- Tidak ada hard delete untuk overtimes; master yang dipakai → nonaktifkan.
- Mass assignment: `$fillable` eksplisit.
- Input teks di-escape oleh React; jangan pakai `dangerouslySetInnerHTML`.

**Audit log — wajib dicatat**
submit publik, input atas nama, edit, setujui, setujui terpilih (1 log per record), tolak, batalkan persetujuan, CRUD/import karyawan, CRUD departemen, CRUD/reset password/nonaktifkan user, perubahan kalender libur & pengaturan, login berhasil & gagal, export.

## 6. Performa

- 150 karyawan, puluhan pengajuan/hari → tidak butuh cache khusus. Fokus pada bundle kecil form publik.
- Form publik: code-split dari area panel; jangan memuat komponen tabel/admin.
- Font di-preload, `font-display: swap`, hanya weight yang dipakai.
- Target Lighthouse mobile: Performance ≥ 85, Accessibility ≥ 95.
