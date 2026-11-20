# PRD — SI-LEMBUR

**Nama produk:** SI-LEMBUR (Sistem Informasi Lembur) — PT Kenco Manufaktur Indonesia.

## 1. Latar belakang & tujuan

Pencatatan lembur karyawan pabrik masih manual, sehingga rekap bulanan untuk payroll lambat dan rawan salah. Sistem ini dibuat agar:
- karyawan bisa mencatat lembur sendiri dari HP dalam kurang dari 1 menit;
- supervisor menyetujui lembur departemennya dari satu dashboard;
- admin/HRD menarik rekap bulanan ke template Excel tanpa input ulang.

**Skala:** 150+ karyawan, beberapa departemen, 1 supervisor per departemen.
**Pengguna karyawan:** lintas usia, HP Android low–mid end (layar 360–412px), tidak semua terbiasa dengan aplikasi.

## 2. Role

| Role | Login | Hak akses |
|---|---|---|
| Karyawan | Tidak | Isi form lembur, cek status pengajuan miliknya |
| SUPERVISOR | Ya | Lihat, approve, reject lembur **departemennya sendiri**; input atas nama karyawan departemennya; batalkan approval; export departemennya |
| ADMIN | Ya | Semua hak supervisor untuk **semua departemen**; kelola karyawan, departemen, akun, kalender libur, pengaturan; lihat audit log |

- Akun hanya dibuat oleh Admin. Tidak ada registrasi publik.
- Akun SUPERVISOR wajib terhubung ke **tepat 1 departemen**; 1 departemen maksimal 1 supervisor aktif.

## 3. Alur utama

```
Karyawan buka link publik
  → pilih Departemen → ketik Nama (typeahead) → isi tanggal, jam, istirahat, kegiatan
  → layar "Periksa lagi" → Kirim
  → status MENUNGGU, tampil Nomor Pengajuan
        ↓
Supervisor login → daftar MENUNGGU
  → Setujui → DISETUJUI (terkunci, masuk export)
  → Tolak (alasan wajib) → DITOLAK → karyawan boleh ajukan ulang (pengajuan baru)

Koreksi setelah disetujui:
  Supervisor/Admin → "Batalkan persetujuan" (alasan wajib) → kembali MENUNGGU → edit → setujui ulang

Input atas nama karyawan (lupa input / tidak punya HP):
  Supervisor/Admin isi form → langsung DISETUJUI, sumber = SUPERVISOR
```

## 4. Halaman publik (tanpa login)

### 4.1 Form lembur

| Field | Kontrol | Catatan |
|---|---|---|
| Departemen | Select besar | Departemen aktif |
| Nama | Typeahead | Karyawan aktif di departemen terpilih, tampil Nama + NIK. Wajib memilih dari daftar |
| Tanggal lembur | Pilihan "Hari ini" / "Kemarin" | Aturan di `BUSINESS_RULES.md` §1 |
| Jam mulai | Picker 15 menit | 00:00–23:45 |
| Jam selesai | Picker 15 menit | 00:00–23:45, boleh lewat tengah malam |
| Istirahat | Picker 15 menit | 0 menit–4 jam, default 0 |
| Total lembur | Read-only | Real-time, contoh "2 jam 30 menit (2,5 jam)" |
| Kegiatan lembur | Textarea | Wajib, 5–500 karakter |

Perilaku:
- **Typeahead:** daftar karyawan per departemen di-load sekali, pencocokan di browser: case-insensitive, cocok di awal kata mana pun, toleran typo 1–2 huruf. Pola ARIA combobox, bisa pakai keyboard.
- **Picker jam:** bottom sheet berisi daftar yang bisa di-scroll (baris ≥48px) atau `<select>` native. Pilih satu pendekatan yang paling mudah di HP dan pakai konsisten. Saat dibuka, langsung scroll ke jam terdekat.
- Bila melewati batas lembur harian/mingguan, tampil info netral (tidak memblok). Lihat `BUSINESS_RULES.md` §4.
- Sebelum kirim tampil layar ringkasan "Periksa lagi" dengan tombol "Kirim" dan "Ubah".
- Setelah sukses: Nomor Pengajuan, ringkasan, status "Menunggu persetujuan supervisor", tombol "Isi lembur lagi" dan "Cek status". Form direset.

### 4.2 Cek status

- Pilih departemen → typeahead nama → daftar pengajuan **60 hari terakhir**: tanggal, jam, total, status, alasan penolakan.
- Read-only.

## 5. Halaman login (Supervisor & Admin)

Semua halaman responsif. Di HP, tabel berubah jadi daftar kartu dengan tombol aksi besar.

| Halaman | SPV | Admin | Isi |
|---|---|---|---|
| Login | ✓ | ✓ | Username + password. Tanpa "lupa password" via email; Admin yang reset |
| Dashboard | ✓ | ✓ | KPI (maks 4): Menunggu, Disetujui bulan ini, Total jam bulan ini, Lewat batas |
| Persetujuan | ✓ | ✓ | Daftar MENUNGGU + penanda lewat batas. Aksi: Setujui, Tolak (alasan wajib), Setujui terpilih (bulk) |
| Riwayat lembur | ✓ | ✓ | Semua status, filter bulan/status/karyawan. Aksi: Batalkan persetujuan, Edit (hanya MENUNGGU) |
| Input atas nama karyawan | ✓ | ✓ | Form seperti publik + tanggal mundur (`BUSINESS_RULES.md` §1.2). Langsung DISETUJUI |
| Export Excel | ✓ | ✓ | Pilih bulan (+ departemen untuk Admin) |
| Karyawan | – | ✓ | CRUD, import Excel (preview + error per baris), nonaktifkan |
| Departemen | – | ✓ | CRUD, nonaktifkan |
| Pengguna | – | ✓ | Buat/edit SUPERVISOR & ADMIN, pilih departemen, reset password, nonaktifkan |
| Kalender libur | – | ✓ | Hari istirahat mingguan + daftar tanggal libur (input / import) |
| Pengaturan | – | ✓ | Batas jam "kemarin", batas 4/18 jam, batas durasi maksimal |
| Audit log | – | ✓ | Siapa, kapan, aksi, sebelum/sesudah |

### 5.1 Master karyawan

- Field: NIK (unik), Nama, Departemen, Aktif.
- Import Excel kolom NIK, Nama, Departemen → preview + error per baris → simpan (upsert berdasarkan NIK).
- Karyawan yang punya data lembur tidak bisa dihapus → tawarkan Nonaktifkan.
- Pindah departemen: lembur lama tetap tercatat di departemen saat lembur.

## 6. Export Excel

- Periode: **bulan kalender** (tanggal 1 sampai akhir bulan, berdasarkan tanggal lembur).
- Hanya pengajuan **DISETUJUI**.
- Supervisor: departemennya. Admin: per departemen atau semua.
- **Wajib memakai file template Excel** yang dilampirkan pemilik proyek (load template, isi sel). Jangan membuat layout sendiri. Bila template butuh kolom yang tidak ada, tanya dulu.
- Data tersedia: Nomor Pengajuan, Tanggal, Hari, Jenis Hari, NIK, Nama, Departemen, Jam Mulai, Jam Selesai, Istirahat (jam desimal), Total (jam desimal, 2 angka di belakang koma), Kegiatan, Disetujui oleh, Waktu disetujui.
- Nama file: `Lembur_{Departemen}_{YYYY-MM}.xlsx`. Dijalankan sinkron.

## 7. Kebutuhan non-fungsional

- Form publik terisi < 1 menit di HP 360px, tanpa scroll horizontal dan tanpa zoom.
- Lighthouse mobile form publik: Accessibility ≥ 95, Performance ≥ 85.
- Kontras WCAG AA. Target sentuh ≥ 48px di form publik.
- Semua tanggal/jam menurut `Asia/Jakarta`.
- Aman dari akses lintas departemen, spam form, dan perubahan data tanpa jejak.

## 8. Acceptance criteria

1. Form publik bisa diisi di HP 360px tanpa scroll horizontal dan tanpa zoom.
2. 09/11 23:00–02:00, istirahat 30 menit → **2,5 jam**, tersimpan di tanggal 09/11.
3. Pilihan "Kemarin" hanya tersedia sebelum batas jam (default 12:00 WIB) menurut server, dan ditolak server bila lewat.
4. Typeahead menemukan "Budi Santoso" dari "budi", "santo", dan "santosoo".
5. Pengajuan yang overlap dengan pengajuan MENUNGGU/DISETUJUI milik karyawan yang sama ditolak dengan pesan jelas.
6. Supervisor departemen A mendapat 403 saat mengakses/menyetujui data departemen B.
7. Tolak tanpa alasan tidak bisa disimpan. Pengajuan ditolak bisa diajukan ulang.
8. Batalkan persetujuan → kembali MENUNGGU dan tercatat di audit log.
9. Input oleh supervisor langsung DISETUJUI dengan sumber SUPERVISOR.
10. Lembur di hari libur / hari istirahat mingguan tercatat HARI_LIBUR dan tidak memicu warning batas.
11. Export hanya berisi DISETUJUI di bulan terpilih, mengikuti template, angka desimal benar.
12. Target Lighthouse dan kontras §7 tercapai.

## 9. Di luar cakupan

- Pengajuan rencana lembur sebelum lembur.
- Approval berjenjang.
- Notifikasi WhatsApp/email/push.
- Perhitungan upah lembur (rupiah).
- Login karyawan, aplikasi native, mode offline.
- Integrasi dengan sistem lain (EORA, SINNIC, mesin absensi).
