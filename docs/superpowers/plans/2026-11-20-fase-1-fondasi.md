# Fase 1 — Fondasi: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Kerangka SI-LEMBUR yang bisa dijalankan: Laravel 13 + starter kit React resmi (dipangkas), token Industrial Blue, enum, seluruh skema DB, role middleware + policy dasar, layout panel & publik, seeder.

**Architecture:** Starter kit React resmi (Inertia 3, React 19, TS, Tailwind 4, Fortify) dipasang lalu dipangkas sesuai PRD: login username, tanpa registrasi/reset email/2FA/passkey/dark mode/halaman settings. Skema DB mengikuti `docs/ARCHITECTURE.md` §4 apa adanya. Belum ada logika lembur (Fase 2).

**Tech Stack:** Laravel 13.43 (PHP ^8.3) · Inertia 3 · React 19 · TypeScript strict · Tailwind 4 · Fortify · Pest · Vitest · MySQL 8 (lokal via Docker) · SQLite `:memory:` untuk test.

**Spec:** `docs/PRD.md`, `docs/BUSINESS_RULES.md`, `docs/ARCHITECTURE.md`, `DESIGN.md`, `docs/ROADMAP.md` (Fase 1).

## Global Constraints

- Nama produk selalu `SI-LEMBUR`; slug `si-lembur`.
- PHP target produksi **8.3** → `composer.json` `config.platform.php = "8.3.0"`. Lokal saat ini PHP 8.5.4: jangan pakai fitur 8.4+.
- `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`.
- Tanpa Redis, queue worker, SSR Inertia, atau Node di server (Hostinger).
- DB: hanya fitur yang jalan di MySQL 8 **dan** MariaDB 10.6+ (dan SQLite untuk test).
- Nama tabel/kolom/kode bahasa Inggris; teks UI Bahasa Indonesia, sentence case, tanpa emoji.
- PHP: `declare(strict_types=1);`, Pint. TS strict, tanpa `any`.
- Tidak ada dark mode, gradient, shadow pada kartu biasa, `#000` (`DESIGN.md` §10).
- Satu tombol primary per layar. Target sentuh ≥48px publik, ≥44px panel mobile.
- Tidak ada hard delete data lembur.

## Review Focus

1. **Supervisor aktif kedua di departemen yang sama lewat update** (reaktivasi atau ganti role), bukan hanya insert → harus ditolak oleh UNIQUE generated column. Test di Task 4.
2. **`POST /register` dan rute reset password/2FA** masih terdaftar walau halaman dihapus → harus 404. Test di Task 2.
3. **OS/browser dalam mode gelap** → UI tetap terang (tidak ada class `dark`, tidak ada skrip appearance). Test di Task 7.
4. **`now()` dan datetime tersimpan** harus Asia/Jakarta tanpa konversi UTC (`09/11 23:00` tetap `23:00` setelah dibaca ulang). Test di Task 1.
5. **Login memakai field `email`** (sisa default Fortify) → gagal; hanya `username` yang diterima. Test di Task 2.

---

## Struktur file

```
composer.json, package.json, vite.config.ts, tsconfig.json   # dari starter kit, diubah
.env.example, docker-compose.yml                              # baru (MySQL 8 lokal)
config/app.php, config/fortify.php                            # diubah
app/Enums/{OvertimeStatus,OvertimeSource,DayType,UserRole}.php
app/Models/{Department,Employee,User,Overtime,Holiday,Setting,AuditLog}.php
app/Http/Middleware/EnsureRole.php
app/Policies/{OvertimePolicy,EmployeePolicy}.php
app/Http/Controllers/Panel/DashboardController.php           # placeholder
app/Http/Controllers/Public/OvertimeFormController.php        # placeholder
database/migrations/*                                          # 7 tabel + sessions/cache/jobs bawaan
database/factories/*, database/seeders/*
resources/js/styles/tokens.css, resources/css/app.css
resources/js/layouts/{panel-layout,public-layout}.tsx
resources/js/components/panel/{sidebar-nav,top-bar}.tsx
resources/js/components/wordmark.tsx
resources/js/pages/public/form.tsx, resources/js/pages/panel/dashboard.tsx  # placeholder
public/favicon.svg
tests/Feature/*, tests/Unit/*, resources/js/**/*.test.tsx
```

Penamaan file React mengikuti konvensi starter kit (kebab-case). `ARCHITECTURE.md` §2 menulis `Form.tsx` (PascalCase); pakai kebab-case agar konsisten dengan kit.

---

### Task 1: Scaffold Laravel + konfigurasi dasar

**Files:**
- Create: seluruh skeleton Laravel di root repo, `docker-compose.yml`, `.env.example`
- Modify: `composer.json` (platform, scripts), `package.json` (scripts), `config/app.php`
- Test: `tests/Feature/ConfigTest.php`

**Interfaces:**
- Produces: `composer run dev`, `php artisan test`, `npm run test|lint|typecheck|build`.

- [ ] **Step 1:** Cek flag installer: `laravel new --help`. Scaffold di scratch dir (root repo tidak kosong): `laravel new si-lembur-app --react --pest --npm --no-interaction --database=sqlite` (sesuaikan flag dengan output `--help`). Pindahkan isinya ke root repo tanpa menimpa `docs/`, `DESIGN.md`, `CLAUDE.md`.
- [ ] **Step 2:** `composer.json`: `"config": {"platform": {"php": "8.3.0"}}`, lalu `composer update --lock`. Hasil: lock tetap resolve tanpa error.
- [ ] **Step 3:** `package.json` scripts tambahan agar cocok dengan `CLAUDE.md`: `"lint": "vp check"`, `"typecheck": "tsc --noEmit"`, `"test": "vitest run"`. Pasang `vitest`, `@testing-library/react`, `@testing-library/jest-dom`, `jsdom` (dev) bila `vp test` tidak tersedia; konfigurasi `environment: 'jsdom'`.
- [ ] **Step 4:** `config/app.php`: `'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta')`, `'locale' => env('APP_LOCALE', 'id')`, `'fallback_locale' => 'en'`, `'faker_locale' => 'id_ID'`.
- [ ] **Step 5:** `.env.example`: nilai Global Constraints + `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=si_lembur`, `DB_USERNAME=si_lembur`, `DB_PASSWORD=secret`, `APP_NAME=SI-LEMBUR`. `phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`.
- [ ] **Step 6:** `docker-compose.yml`: satu service `mysql:8.0`, port 3306, kredensial sama dengan `.env.example`, volume bernama. (Hanya untuk dev lokal.)
- [ ] **Step 7: Tulis test** `tests/Feature/ConfigTest.php`:

```php
it('uses Asia/Jakarta', fn () => expect(config('app.timezone'))->toBe('Asia/Jakarta')
    ->and(now()->getTimezone()->getName())->toBe('Asia/Jakarta'));
it('uses locale id and sync queue', fn () => expect(config('app.locale'))->toBe('id')
    ->and(config('queue.default'))->toBe('sync'));
it('serves the public root', fn () => get('/')->assertOk());
```

(Round-trip datetime diuji di Task 4 lewat model `Overtime`. Driver session/cache `database` dicek lewat `.env.example`, bukan test, karena `phpunit.xml` memakai `array`.)
- [ ] **Step 8:** Run `php artisan test` → PASS. `npm run build` → sukses.
- [ ] **Step 9:** Commit `chore: scaffold laravel 13 react starter kit`.

### Task 2: Pangkas auth starter kit

**Files:**
- Modify: `config/fortify.php`, `app/Providers/FortifyServiceProvider.php`, `routes/web.php`, `routes/settings.php` (hapus), `resources/js/pages/auth/*`, `resources/js/pages/settings/*` (hapus), `composer.json`/`package.json` (hapus passkeys bila tidak dipakai)
- Test: `tests/Feature/Auth/LoginTest.php`, hapus test bawaan untuk fitur yang dihapus

**Interfaces:**
- Produces: `POST /login` dengan field `username` + `password`; `POST /logout`.

- [ ] **Step 1: Tulis test gagal:**

```php
it('logs in with username', function () {
    $u = User::factory()->create(['username' => 'admin', 'password' => 'rahasia123']);
    post('/login', ['username' => 'admin', 'password' => 'rahasia123'])->assertRedirect('/panel');
    expect(auth()->id())->toBe($u->id);
});
it('rejects the email field', function () {
    User::factory()->create(['username' => 'admin', 'email' => 'a@x.id', 'password' => 'rahasia123']);
    post('/login', ['email' => 'a@x.id', 'password' => 'rahasia123'])->assertSessionHasErrors('username');
});
it('has no registration or self-service recovery routes', function (string $method, string $uri) {
    $this->call($method, $uri)->assertNotFound();
})->with([['GET','/register'],['POST','/register'],['GET','/forgot-password'],
          ['POST','/forgot-password'],['GET','/two-factor-challenge'],['GET','/settings/profile']]);
```

- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** `config/fortify.php`: `'username' => 'username'`, `'home' => '/panel'`, `'features' => []`. Hapus rute/halaman settings, register, forgot/reset password, verify email, 2FA, passkeys. Limiter `login`: 5/menit per `username|ip`. Halaman `auth/login.tsx` field "Nama pengguna" + "Kata sandi".
- [ ] **Step 4:** Run → PASS. Commit `refactor(auth): username login, drop registration and recovery`.

### Task 3: Enum

**Files:** Create `app/Enums/{OvertimeStatus,OvertimeSource,DayType,UserRole}.php`; Test `tests/Unit/EnumsTest.php`

**Interfaces — Produces** (string-backed, `label(): string`):
- `OvertimeStatus`: `PENDING`→"Menunggu", `APPROVED`→"Disetujui", `REJECTED`→"Ditolak"
- `OvertimeSource`: `PUBLIC`→"Karyawan", `SUPERVISOR`→"Supervisor"
- `DayType`: `HARI_KERJA`→"Hari kerja", `HARI_LIBUR`→"Hari libur"
- `UserRole`: `ADMIN`→"Admin", `SUPERVISOR`→"Supervisor"
- Case name = value (`PENDING = 'PENDING'`), sesuai ENUM DB.

- [ ] **Step 1:** Test: setiap enum punya tepat case di atas (`cases()` → values) dan `label()` sesuai teks di atas.
- [ ] **Step 2:** FAIL → implement → PASS. Commit `feat(enums): add status, source, day type, role`.

### Task 4: Migrasi + model

**Files:**
- Create: migrasi `departments`, `employees`, `holidays`, `settings`, `overtimes`, `audit_logs`; Modify migrasi `users` bawaan
- Create/Modify: model di Struktur file
- Test: `tests/Feature/Schema/ActiveSupervisorUniqueTest.php`, `tests/Feature/Schema/OvertimeModelTest.php`

**Interfaces — Produces:**
- Kolom persis `ARCHITECTURE.md` §4. ENUM pakai `->enum(..., array_column(X::cases(), 'value'))`.
- `users.active_supervisor_department_id`: `->unsignedBigInteger()->nullable()->storedAs("CASE WHEN role = 'SUPERVISOR' AND is_active = 1 THEN department_id ELSE NULL END")` + `->unique()`.
- Model: `$fillable` eksplisit; casts enum (`status`, `source`, `day_type`, `role`), `start_at`/`end_at`/`approved_at`/`rejected_at` → `datetime`, `overtime_date` → `date`, `is_active`/`exceeds_*` → `bool`, `audit_logs.before/after` & `settings.value` → `array`.
- Relasi: `Department hasMany Employee|User|Overtime`; `Employee belongsTo Department, hasMany Overtime`; `User belongsTo Department`; `Overtime belongsTo Employee|Department`, `approver()`/`rejecter()`/`creator()` → `User`. `AuditLog` `$timestamps = false`, `created_at` diisi `useCurrent()`.
- `Setting` PK `key` string, `$incrementing = false`, `$keyType = 'string'`.
- Tidak ada `SoftDeletes`; tidak ada method delete khusus.

- [ ] **Step 1: Tulis test:**

```php
it('rejects a second active supervisor in one department', function () {
    $d = Department::factory()->create();
    User::factory()->supervisor($d)->create();
    expect(fn () => User::factory()->supervisor($d)->create())->toThrow(QueryException::class);
});
it('allows an inactive second supervisor, then blocks reactivating it', function () {
    $d = Department::factory()->create();
    User::factory()->supervisor($d)->create();
    $b = User::factory()->supervisor($d)->create(['is_active' => false]);
    expect(fn () => $b->update(['is_active' => true]))->toThrow(QueryException::class);
});
it('allows many admins with or without department', function () {
    $d = Department::factory()->create();
    User::factory()->admin()->count(2)->create(['department_id' => $d->id]);
    expect(User::count())->toBe(2);
});
it('stores overtime datetimes as Jakarta wall-clock', function () {
    $o = Overtime::factory()->create(['start_at' => '2026-11-09 23:00:00', 'end_at' => '2026-11-10 02:00:00']);
    expect($o->fresh()->start_at->format('Y-m-d H:i'))->toBe('2026-11-09 23:00')
        ->and($o->fresh()->status)->toBe(OvertimeStatus::PENDING);
});
```

- [ ] **Step 2:** FAIL → buat migrasi + model + factory (`supervisor(Department)`, `admin()` state) → PASS di SQLite.
- [ ] **Step 3:** `docker compose up -d` lalu `php artisan migrate:fresh` dengan `.env` MySQL → sukses tanpa error (cek generated column di MySQL nyata).
- [ ] **Step 4:** Commit `feat(db): add schema and models`.

### Task 5: Seeder

**Files:** Create `database/seeders/{SettingSeeder,DepartmentSeeder,EmployeeSeeder,UserSeeder,HolidaySeeder}.php`, Modify `DatabaseSeeder.php`; Test `tests/Feature/SeederTest.php`

**Interfaces — Produces:**
- Settings default: `weekly_rest_days` = `[7]` (ISO-8601, 7 = Minggu), `yesterday_cutoff_time` = `"12:00"`, `daily_limit_hours` = `4`, `weekly_limit_hours` = `18`, `max_gross_hours` = `16`.
- 3 departemen contoh (`PRD`, `QC`, `WH`), 20 karyawan tersebar, termasuk `Budi Santoso` (dipakai AC4 Fase 3).
- 1 admin (`admin`), 2 supervisor (dept 1 & 2; dept 3 tanpa supervisor). Password dev dari `.env` `SEED_DEFAULT_PASSWORD`, fallback `password` hanya di env `local`/`testing`.
- 2–3 contoh tanggal libur 2026 bertanda contoh (data asli ditanyakan Fase 5).

- [ ] **Step 1:** Test: setelah `seed()`: `Department::count() === 3`, `Employee::count() === 20`, admin 1, supervisor 2, `Holiday::count() >= 1`, `Setting::find('weekly_rest_days')->value === [7]`, dst. untuk 5 key; `Employee::where('name','Budi Santoso')->exists()`.
- [ ] **Step 2:** FAIL → implement → PASS. `php artisan migrate:fresh --seed` di MySQL → sukses. Commit `feat(db): add seeders`.

### Task 6: Middleware role + policy dasar + rute panel

**Files:** Create `app/Http/Middleware/EnsureRole.php`, `app/Policies/{OvertimePolicy,EmployeePolicy}.php`, `app/Http/Controllers/Panel/DashboardController.php`; Modify `bootstrap/app.php` (alias `role`), `routes/web.php`; Test `tests/Feature/Authorization/{RoleMiddlewareTest,OvertimePolicyTest}.php`

**Interfaces — Produces:**
- Middleware alias `role`: `->middleware('role:ADMIN')` / `role:ADMIN,SUPERVISOR`. Bukan role → 403. User nonaktif → logout + redirect `/login` (penegakan saat login di Fase 4).
- `User::isAdmin(): bool`, `User::isSupervisor(): bool`, `User::canAccessDepartment(int $departmentId): bool` (admin → true; supervisor → `department_id === $departmentId`).
- `OvertimePolicy::view|approve|reject|cancelApproval|update(User, Overtime): bool` → `canAccessDepartment($overtime->department_id)`. Cek status ditambah Fase 2/4.
- `EmployeePolicy::viewAny(User): bool` → semua role panel; `create|update|deactivate(User, ...)` → admin saja.
- Rute: `GET /panel` (`auth`, `role:ADMIN,SUPERVISOR`) → Inertia `panel/dashboard` (placeholder). Test middleware mendaftarkan rute sementara di dalam test, bukan rute khusus di `routes/web.php`.

- [ ] **Step 1: Tulis test:**

```php
it('redirects guests from panel to login', fn () => get('/panel')->assertRedirect('/login'));
it('forbids supervisor on admin-only route', function () {
    Route::middleware(['web','auth','role:ADMIN'])->get('/_t', fn () => 'ok');
    actingAs(User::factory()->supervisor(Department::factory()->create())->create())->get('/_t')->assertForbidden();
});
it('scopes overtime policy by department', function () {
    [$a, $b] = Department::factory()->count(2)->create();
    $spv = User::factory()->supervisor($a)->create();
    $o = Overtime::factory()->for($b)->create();
    expect($spv->can('approve', $o))->toBeFalse()
        ->and(User::factory()->admin()->create()->can('approve', $o))->toBeTrue();
});
```

- [ ] **Step 2:** FAIL → implement → PASS. Commit `feat(auth): add role middleware and base policies`.

### Task 7: Token, font, layout panel & publik

**Files:** Create `resources/js/styles/tokens.css`, `components/wordmark.tsx`, `components/panel/{sidebar-nav,top-bar}.tsx`, `layouts/{panel-layout,public-layout}.tsx`, `pages/public/form.tsx` (placeholder), `public/favicon.svg`, `Public/OvertimeFormController.php`; Modify `resources/css/app.css`, `resources/views/app.blade.php`, `resources/js/app.tsx`; hapus komponen/hook appearance & dark-mode kit; Test `resources/js/layouts/*.test.tsx`, `tests/Feature/LayoutTest.php`

**Interfaces — Produces:**
- `tokens.css` = blok `:root` `DESIGN.md` §2 apa adanya. `app.css` `@theme` memetakan token ke Tailwind (`--color-canvas: var(--canvas)` dst., `--font-sans`, `--font-mono`, radius, shadow). Hapus semua varian `dark:` dan `@custom-variant dark`.
- Font self-host: `@fontsource/plus-jakarta-sans` (400/500/600/700) + `@fontsource/jetbrains-mono` (500/600), `font-display: swap`. Library kecil → disebut di laporan.
- `<Wordmark variant="public" | "sidebar" | "login" />` — ukuran/warna sesuai `DESIGN.md` §8.1.
- `<PublicLayout title>`: header wordmark + "Input Lembur · PT Kenco", link "Cek status pengajuan", konten maks 480px, padding 16px.
- `<PanelLayout title>`: sidebar 240px `--sidebar` (≥1024px), drawer + tombol menu ber-`aria-label="Buka menu"` (<1024px), top bar 56px. Item nav admin-only (Karyawan, Departemen, Pengguna, Kalender libur, Pengaturan, Audit log) hanya bila `auth.user.role === 'ADMIN'` (prop Inertia shared). Ikon lucide 20px.
- `<title>`: `{judul} — SI-LEMBUR` via `createInertiaApp({ title })`.
- `GET /` → Inertia `public/form` (placeholder, isi form Fase 3).
- Favicon SVG "SL" putih di kotak `#1e6fd9` radius 6.

- [ ] **Step 1: Tulis test** (Vitest): PublicLayout memuat teks `SI-LEMBUR` dan `Input Lembur · PT Kenco`; PanelLayout dengan role `SUPERVISOR` tidak memuat "Audit log", dengan `ADMIN` memuat. Feature: `get('/')->assertInertia(fn ($p) => $p->component('public/form'))`; `app.blade.php` tidak mengandung `appearance` atau class `dark`.
- [ ] **Step 2:** FAIL → implement → PASS.
- [ ] **Step 3:** Cek manual di browser 360px & 1280px: `/`, `/login`, `/panel` (admin & supervisor). Tidak ada scroll horizontal; focus ring 2px primary terlihat; emulasi `prefers-color-scheme: dark` tetap terang.
- [ ] **Step 4:** Commit `feat(ui): add tokens, fonts, panel and public layouts`.

### Task 8: Verifikasi & laporan fase

- [ ] `./vendor/bin/pint --test` → bersih
- [ ] `php artisan test` → semua PASS
- [ ] `npm run lint && npm run typecheck && npm run test && npm run build` → bersih
- [ ] `php artisan migrate:fresh --seed` di MySQL Docker → sukses
- [ ] `caveman-review` pada diff fase; `caveman-stats`
- [ ] Update bagian "Perintah" di `CLAUDE.md` bila berubah (mis. `docker compose up -d`)
- [ ] Laporan format `ROADMAP.md`, centang checklist Fase 1 di `docs/ROADMAP.md`
