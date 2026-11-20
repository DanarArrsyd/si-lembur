# Roadmap — SI-LEMBUR

Aturan: kerjakan satu fase sekali jalan. Awal fase → rencana singkat (`superpowers:writing-plans`) → tunggu persetujuan. Akhir fase → semua test, lint, typecheck lulus → laporan → tunggu konfirmasi.

Rencana dan laporan fase ditulis dengan skill `caveman` (singkat, fakta lengkap). Sebelum laporan: jalankan `caveman-review` pada diff fase dan sertakan ringkasan `caveman-stats`.

Format laporan akhir fase:
```
Fase N — selesai
- Yang dibuat: ...
- Test: X lulus, 0 gagal (php artisan test / npm run test)
- Lint/typecheck: bersih
- Usulan / pertanyaan: ...
```

---

## Fase 1 — Fondasi
- [ ] Laravel + starter kit React (Inertia, TS), registrasi dimatikan
- [ ] Tailwind + `tokens.css` dari `DESIGN.md`, font Plus Jakarta Sans & JetBrains Mono
- [ ] Enum: `OvertimeStatus`, `OvertimeSource`, `DayType`, `UserRole`
- [ ] Migrasi semua tabel (`ARCHITECTURE.md` §4), termasuk unique 1 SPV aktif per departemen
- [ ] Middleware role, policy dasar, layout panel (sidebar + top bar) dan layout publik
- [ ] Seeder: 1 admin, 3 departemen, 20 karyawan, 2 supervisor, contoh hari libur, pengaturan default
- [ ] `.env.example` dengan `APP_TIMEZONE=Asia/Jakarta`, driver database/sync

## Fase 2 — Logika inti (TDD)
- [ ] `OvertimeCalculator` — test C1–C7
- [ ] `OvertimeDateRule` — test D1–D6 (pakai `Carbon::setTestNow`)
- [ ] `DayTypeResolver` — test H1–H2
- [ ] `OvertimeLimitChecker` — test H3–H5
- [ ] `OverlapChecker` — test O1–O4
- [ ] `OvertimeNumberGenerator` — aman dari race (test paralel sederhana)
- [ ] `OvertimeWorkflow` — semua transisi status + audit log
- [ ] `lib/overtime.ts` mirror kalkulator + test Vitest dengan kasus yang sama

## Fase 3 — Form publik + cek status
- [ ] Endpoint lookup karyawan (field terbatas, throttle)
- [ ] Komponen `Typeahead` (fuse.js, ARIA combobox), `TimePicker`, `BottomSheet`
- [ ] Form + preview total real-time + info lewat batas
- [ ] Layar "Periksa lagi" → submit → halaman sukses (signed URL) → reset form
- [ ] Halaman cek status 60 hari
- [ ] Throttle, honeypot, pesan error sesuai `BUSINESS_RULES.md` §2.1
- [ ] Uji manual di viewport 360px & 412px; Lighthouse mobile

## Fase 4 — Area supervisor
- [ ] Login + throttle + akun nonaktif ditolak
- [ ] Dashboard KPI
- [ ] Persetujuan: setujui, tolak (alasan), setujui terpilih; tampilan kartu di mobile
- [ ] Riwayat + filter; edit (MENUNGGU), batalkan persetujuan (alasan)
- [ ] Input atas nama karyawan (tanggal mundur sesuai aturan, langsung DISETUJUI)
- [ ] Test otorisasi lintas departemen (403) untuk semua aksi, termasuk bulk

## Fase 5 — Area admin
- [ ] CRUD departemen + nonaktifkan
- [ ] CRUD karyawan + nonaktifkan + import Excel (preview, error per baris, upsert NIK)
- [ ] CRUD pengguna (pilih role & departemen, reset password, nonaktifkan)
- [ ] Kalender libur + hari istirahat mingguan + hitung ulang pengajuan MENUNGGU
- [ ] Pengaturan (cutoff "kemarin", batas 4/18, max durasi)
- [ ] Audit log + filter
- **Tanya pemilik proyek:** file data karyawan untuk import awal; hari istirahat mingguan (Minggu saja atau Sabtu & Minggu)

## Fase 6 — Export Excel
- **Tanya pemilik proyek:** file template Excel
- [ ] `OvertimeExporter` mengisi template dengan PhpSpreadsheet
- [ ] Filter bulan kalender + departemen; hanya DISETUJUI
- [ ] Test: isi, jumlah baris, angka desimal, nama file
- [ ] Audit log export

## Fase 7 — Hardening & deploy
- [ ] Review keamanan (`ARCHITECTURE.md` §5) dan seluruh acceptance criteria (`PRD.md` §8)
- [ ] Lighthouse & kontras final
- [ ] `DEPLOY.md` diuji di Hostinger; backup database
- [ ] Akun admin produksi, hapus data seeder contoh
- **Tanya pemilik proyek:** domain/subdomain
