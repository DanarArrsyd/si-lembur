# Business Rules — SI-LEMBUR

Semua aturan di sini wajib dijalankan di **server** (Service + Form Request). Frontend boleh menghitung ulang hanya untuk preview. Setiap aturan punya test.

Sumber waktu: jam server, timezone **`Asia/Jakarta`**. Jam browser/HP tidak pernah dipakai untuk validasi.

---

## 1. Tanggal lembur

Lembur selalu dicatat pada **tanggal mulai**.

### 1.1 Form publik (karyawan)

| Pilihan | Kapan tersedia |
|---|---|
| Hari ini | Selalu |
| Kemarin | Hanya bila jam server **sebelum `yesterday_cutoff_time`** (default **12:00**) |
| Tanggal lain / tanggal maju | Tidak pernah |

- Server memvalidasi ulang saat submit. Contoh: form dibuka 11:58, dikirim 12:01 dengan "Kemarin" → tolak dengan pesan "Batas input lembur kemarin sudah lewat (12:00). Hubungi supervisor untuk input lembur kemarin."
- Tujuan: lembur yang lewat tengah malam (23:00–02:00) bisa diinput pagi harinya dengan tanggal yang benar.

### 1.2 Input oleh Supervisor/Admin

- Boleh tanggal mundur dalam **bulan berjalan dan bulan sebelumnya**.
- Tanggal maju tidak diizinkan.

---

## 2. Perhitungan jam

```
start_at = overtime_date + jam_mulai
end_at   = overtime_date + jam_selesai
if jam_selesai <= jam_mulai: end_at += 1 hari          # lewat tengah malam
gross_minutes = end_at − start_at
total_minutes = gross_minutes − break_minutes
```

- Simpan `start_at`, `end_at` (datetime lengkap), `break_minutes`, `total_minutes` sebagai **integer menit**. Jangan menyimpan float.
- Tampilan: `"{jam} jam {menit} menit"` + desimal `"2,5 jam"`. Export: desimal 2 angka (`2,50`).

### 2.1 Validasi keras (ditolak)

| Kode | Aturan | Pesan |
|---|---|---|
| R1 | Jam mulai, jam selesai, istirahat kelipatan 15 menit | "Jam harus kelipatan 15 menit." |
| R2 | `jam_selesai ≠ jam_mulai` | "Jam selesai tidak boleh sama dengan jam mulai." |
| R3 | `break_minutes < gross_minutes` (total > 0) | "Istirahat harus lebih singkat dari lama lembur." |
| R4 | `gross_minutes ≤ max_gross_hours × 60` (default 16 jam) | "Lama lembur melebihi batas wajar. Periksa jam mulai dan jam selesai." |
| R5 | Karyawan aktif dan berada di departemen yang dipilih | "Nama tidak ditemukan di departemen ini." |
| R6 | Tidak overlap (lihat §5) | "Sudah ada lembur {nomor} di jam yang sama." |
| R7 | Aturan tanggal §1 | lihat §1 |
| R8 | Kegiatan lembur 5–500 karakter | "Tulis kegiatan lembur minimal 5 huruf." |

---

## 3. Jenis hari

Ditentukan otomatis dari `overtime_date`, **tidak diisi karyawan**:

1. Hari termasuk **hari istirahat mingguan** (`weekly_rest_days`, default `[Minggu]`) → `HARI_LIBUR`
2. Tanggal ada di tabel **holidays** → `HARI_LIBUR`
3. Selain itu → `HARI_KERJA`

- Disimpan sebagai snapshot `day_type` saat pengajuan dibuat/diedit.
- Bila Admin mengubah kalender/hari istirahat: pengajuan **MENUNGGU** di tanggal terkait dihitung ulang; pengajuan **DISETUJUI** tidak berubah.

---

## 4. Batas lembur (warning, tidak memblok)

Acuan: PP 35/2021 Pasal 26 — maksimal **4 jam/hari** dan **18 jam/minggu**, tidak termasuk lembur di hari istirahat mingguan/hari libur resmi.

- Hanya dihitung untuk `HARI_KERJA`.
- Harian = total menit karyawan di `overtime_date` yang sama (status MENUNGGU + DISETUJUI, termasuk pengajuan ini).
- Mingguan = total menit karyawan di minggu **Senin–Minggu** yang sama (MENUNGGU + DISETUJUI, `HARI_KERJA` saja).
- Simpan flag `exceeds_daily_limit` dan `exceeds_weekly_limit`. Hitung ulang flag untuk pengajuan lain di minggu yang sama saat ada pengajuan baru, diedit, ditolak, atau dibatalkan.
- Angka batas dari pengaturan: `daily_limit_hours` (4), `weekly_limit_hours` (18).
- Form karyawan: info netral "Total lembur melebihi 4 jam per hari. Pengajuan tetap dikirim dan akan diperiksa supervisor."
- Dashboard supervisor: label warning "Lewat batas harian" / "Lewat batas mingguan".

---

## 5. Overlap

Pengajuan baru/editan ditolak bila ada pengajuan lain milik karyawan yang sama, berstatus **MENUNGGU atau DISETUJUI**, dengan:

```
existing.start_at < new.end_at AND new.start_at < existing.end_at
```

Bersebelahan tanpa irisan (17:00–19:00 lalu 19:00–21:00) **boleh**. Pengajuan DITOLAK diabaikan.

---

## 6. Status & transisi

```
            submit (PUBLIC)                 submit (SUPERVISOR)
                 │                                  │
                 ▼                                  ▼
            MENUNGGU ── setujui ──────────────► DISETUJUI
               │  ▲                                 │
          tolak│  └───── batalkan persetujuan ──────┘
               ▼          (alasan wajib)
            DITOLAK  (final; karyawan mengajukan ulang sebagai pengajuan baru)
```

| Aksi | Dari | Ke | Siapa | Wajib |
|---|---|---|---|---|
| Kirim (publik) | – | MENUNGGU | Karyawan | – |
| Input atas nama | – | DISETUJUI | SPV dept / Admin | – |
| Setujui / setujui terpilih | MENUNGGU | DISETUJUI | SPV dept / Admin | – |
| Tolak | MENUNGGU | DITOLAK | SPV dept / Admin | Alasan |
| Batalkan persetujuan | DISETUJUI | MENUNGGU | SPV dept / Admin | Alasan |
| Edit | MENUNGGU | MENUNGGU | SPV dept / Admin | Validasi ulang semua aturan |

- Enum di kode: `PENDING`, `APPROVED`, `REJECTED`. Label UI: Menunggu, Disetujui, Ditolak.
- Tidak ada hard delete. Semua transisi masuk audit log.
- Nomor pengajuan `LBR-YYYYMMDD-NNNN` (tanggal = tanggal dibuat, NNNN urut per hari), dibuat di dalam transaksi DB dengan lock.

---

## 7. Test case wajib

### 7.1 Perhitungan

| # | Tanggal | Mulai | Selesai | Istirahat | Hasil |
|---|---|---|---|---|---|
| C1 | 09/11/2026 | 23:00 | 02:00 | 30 m | **150 menit = 2,5 jam**, end_at 10/11 02:00, tanggal 09/11 |
| C2 | 09/11/2026 | 17:00 | 20:00 | 0 | 180 menit = 3 jam |
| C3 | 09/11/2026 | 05:30 | 07:45 | 15 m | 120 menit = 2 jam |
| C4 | 09/11/2026 | 19:45 | 20:15 | 30 m | Ditolak R3 |
| C5 | 09/11/2026 | 08:00 | 08:00 | 0 | Ditolak R2 |
| C6 | 09/11/2026 | 08:10 | 10:00 | 0 | Ditolak R1 |
| C7 | 09/11/2026 | 06:00 | 23:00 | 0 | Ditolak R4 (17 jam) |

### 7.2 Tanggal

| # | Jam server | Pilihan | Hasil |
|---|---|---|---|
| D1 | 10/11 02:30 | Kemarin | Diterima, tanggal 09/11 |
| D2 | 10/11 11:59 | Kemarin | Diterima |
| D3 | 10/11 12:00 | Kemarin | Ditolak |
| D4 | 10/11 09:00 | 11/11 (maju) | Ditolak |
| D5 | SPV, 05/12 | 15/11 | Diterima (bulan sebelumnya) |
| D6 | SPV, 05/12 | 20/10 | Ditolak |

### 7.3 Jenis hari & batas

| # | Kondisi | Hasil |
|---|---|---|
| H1 | Minggu, rest day default | HARI_LIBUR, tanpa warning walau 6 jam |
| H2 | Tanggal ada di holidays | HARI_LIBUR |
| H3 | Hari kerja, total 4,5 jam | HARI_KERJA, `exceeds_daily_limit = true`, tetap tersimpan |
| H4 | Hari kerja, akumulasi Senin–Sabtu 18,5 jam | `exceeds_weekly_limit = true` |
| H5 | Pengajuan penyebab warning ditolak | Flag pengajuan lain di minggu itu dihitung ulang |

### 7.4 Overlap

| # | Existing | Baru | Hasil |
|---|---|---|---|
| O1 | 17:00–20:00 MENUNGGU | 19:00–21:00 | Ditolak |
| O2 | 17:00–19:00 DISETUJUI | 19:00–21:00 | Diterima |
| O3 | 17:00–20:00 DITOLAK | 17:00–20:00 | Diterima |
| O4 | 09/11 23:00–02:00 | 10/11 01:00–03:00 | Ditolak (lintas tanggal) |
