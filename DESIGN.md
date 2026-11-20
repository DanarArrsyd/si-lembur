# DESIGN.md — Industrial Blue

Design system milik pemilik proyek untuk software operasi manufaktur. Pakai untuk seluruh UI sistem lembur. Skill wajib saat membangun UI: `antislop`, `antislop-ui`, `antislop-layoutmobile`, `antislop-human`, `antislop-copywriting`.

## 1. Prinsip

- **Data adalah hero.** Jam, total, nama, dan status yang paling menonjol. Chrome tetap tenang.
- **Satu tema terang.** Tidak ada dark mode atau theme switch. Satu-satunya surface gelap: sidebar & panel login.
- **Flat dan bordered.** Struktur dari garis 1px dan `surface-sunken`, bukan shadow.
- **Mudah ditebak.** Kontrol yang sama, di tempat yang sama, perilaku yang sama di semua layar.
- **Dibuat manusia, bukan template.** Bila layar mirip dashboard SaaS generik, itu salah.
- **Untuk semua usia.** Karyawan pabrik lintas usia memakai HP murah. Besar, jelas, satu hal per langkah.

## 2. Token

Taruh di `resources/js/styles/tokens.css`, lalu petakan ke `tailwind` theme.

```css
:root {
  /* Warna */
  --canvas: #f4f6f9;          /* latar halaman */
  --surface: #ffffff;         /* kartu, form, tabel, dialog, top bar */
  --surface-sunken: #eef2f6;  /* header tabel, field read-only */
  --line: #d9e1e8;            /* border kartu & divider, dekoratif saja */
  --line-control: #7f8e9c;    /* border input/select/checkbox (≥3:1) */
  --ink: #1b2733;             /* teks utama; jangan pernah #000 */
  --ink-muted: #5b6b79;       /* label, helper, metadata (≥4.8:1) */
  --primary: #1e6fd9;         /* 1 tombol utama per layar, link, focus ring, item nav aktif */
  --primary-hover: #185bb3;
  --primary-soft: #e8f0fc;    /* baris terpilih, banner info, kartu total lembur */
  --on-primary: #ffffff;
  --sidebar: #0f1e2e;         /* sidebar & panel login */
  --sidebar-ink: #c3cfdb;
  --sidebar-hover: #1a2e44;
  --steel: #1f5f8b;           /* data sekunder, seri grafik 2 */
  --accent: #c97a2b;          /* highlight 1 angka kritis; tanda, bukan teks */
  --success: #15803d;  --success-soft: #eaf6ec;   /* Disetujui */
  --warning: #b45309;  --warning-soft: #fef3e0;   /* Menunggu, lewat batas */
  --danger:  #b91c1c;  --danger-soft:  #fdebec;   /* Ditolak, error */

  /* Tipografi */
  --font-sans: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
  --font-mono: "JetBrains Mono", ui-monospace, "Cascadia Mono", monospace;

  /* Spacing */
  --space-1: 4px;  --space-2: 8px;  --space-3: 12px;
  --space-4: 16px; --space-5: 24px; --space-6: 32px;

  /* Radius */
  --radius-sm: 4px;    /* input, checkbox */
  --radius-md: 6px;    /* tombol, kartu, dialog, item nav */
  --radius-pill: 999px;/* StatusPill & chip saja */

  /* Shadow: hanya untuk yang melayang */
  --shadow-float: 0 8px 24px rgba(15, 30, 46, 0.14);  /* dropdown, bottom sheet, dialog */
  --shadow-sticky: 2px 0 0 rgba(15, 30, 46, 0.06);    /* tepi kolom sticky */

  /* Ukuran (area panel) */
  --sidebar-width: 240px;
  --topbar-height: 56px;
  --control-height: 36px;
  --row-height: 40px;
}
```

## 3. Tipografi

- Teks interface: **Plus Jakarta Sans** 400/500/600/700.
- Semua angka, jam, tanggal di tabel, NIK, nomor pengajuan, total jam: **JetBrains Mono** 500/600 + `font-variant-numeric: tabular-nums`. Kolom angka rata kanan.

| Style | Size/LH | Weight | Pakai untuk |
|---|---|---|---|
| page-title | 22/30, -0.01em | 700 | 1 per layar |
| section-title | 16/24 | 600 | Judul kartu/section |
| body | 14/20 | 400 | Teks default panel |
| body-strong | 14/20 | 600 | Nama karyawan di tabel, label tombol |
| caption | 12/16 | 500 | Helper, metadata, timestamp |
| label | 11/16, 0.06em, UPPERCASE | 600 | Header tabel, label KPI, StatusPill |
| data | 13/20 mono | 500 | Angka di tabel |
| kpi | 28/32 mono, -0.02em | 600 | Angka utama KpiTile, maks 4 per layar |

## 4. Penyesuaian form publik (mobile, semua usia)

Form karyawan dan halaman cek status memakai ukuran yang lebih besar. Warna, font, radius tetap sama.

| Elemen | Panel | Form publik |
|---|---|---|
| Teks body & nilai input | 14px | **17px** (minimal 16px agar iOS tidak auto-zoom) |
| Label field | 12px | **15px**, 600 |
| Helper / error | 12px | **14px** |
| Tinggi input/select/picker | 36px | **52px** |
| Tombol utama | auto | **Lebar penuh, tinggi 52px**, sticky di bawah layar dengan safe-area inset |
| Baris pilihan picker/typeahead | – | **≥ 48px** |
| Total lembur | – | Kartu `primary-soft`, angka mono **28px** 600 + teks "2 jam 30 menit" |
| Lebar konten | – | Maks 480px, padding samping 16px |

Layout form publik:
- Satu kolom, satu field per baris, urutan sesuai alur kerja.
- Header sederhana: wordmark **SI-LEMBUR** + subjudul "Input Lembur · PT Kenco" (lihat §8.1). Tanpa sidebar.
- Tautan "Cek status pengajuan" kecil di bawah header.
- Error tampil tepat di bawah field + ringkasan di atas tombol bila ada lebih dari satu.

## 5. Layout panel

- Desktop: sidebar `--sidebar` 240px + top bar 56px + konten di `--canvas` dengan gutter 24px.
- < 1024px: sidebar jadi drawer (tombol menu di top bar).
- < 640px: tabel berubah jadi **daftar kartu** (`MobileCardList`) dengan tombol Setujui/Tolak tinggi 44px.
- Dashboard: deretan KpiTile (maks 4) lalu tabel pengajuan menunggu.
- Tabel panjang scroll di dalam kontainer; header tabel sticky.

## 6. Komponen

| Komponen | Aturan |
|---|---|
| Button | Varian: `primary` (1 per layar), `secondary` (border `line-control`), `ghost`, `danger` (outline merah). Radius 6px. Disabled opacity 50% |
| Field | Label di atas, helper di bawah. Border `line-control`, radius 4px. Read-only berlatar `surface-sunken`. Invalid: border `danger`, latar `danger-soft`, caption `danger` yang menjelaskan cara memperbaiki |
| TimePicker | Bottom sheet dengan daftar jam kelipatan 15 menit (atau `<select>` native, pilih satu dan konsisten). Auto-scroll ke jam terdekat. Mono untuk jam |
| Typeahead | ARIA combobox. Hasil: Nama (body-strong) + NIK (mono, ink-muted). Empty state: "Nama tidak ditemukan. Pastikan departemen sudah benar." |
| StatusPill | Pill tinggi 22px, label uppercase + titik warna. `MENUNGGU` warning, `DISETUJUI` success, `DITOLAK` danger. Selalu berisi kata |
| KpiTile | Kartu `surface` border `line`, label uppercase + angka mono 28px + meta caption |
| DataTable | Header `surface-sunken`, row 40px, divider `line`, baris terpilih `primary-soft`, kolom angka mono rata kanan |
| BottomSheet / Dialog | `surface`, radius 6px (atas saja untuk bottom sheet), `shadow-float`, overlay `rgba(15,30,46,.4)` tanpa blur |
| ConfirmDialog | Judul aksi + kalimat konsekuensi + tombol aksi & Batal. Untuk Tolak/Batalkan: textarea alasan wajib |
| SidebarNav | Latar `sidebar`, item 36px, hover `sidebar-hover`, aktif `primary` + `on-primary` |

## 7. State & aksesibilitas

- Focus ring: 2px solid `--primary`, offset 2px, di semua elemen interaktif.
- Kontras teks minimal WCAG AA; `line` tidak boleh jadi satu-satunya batas kontrol.
- Target sentuh ≥ 48px di form publik, ≥ 44px di panel mobile.
- Semua ikon-only button punya `aria-label` + tooltip.
- Status tidak hanya dari warna; selalu ada kata.
- Loading: tombol menampilkan teks "Mengirim…" dan disabled; jangan pakai skeleton berkedip berlebihan.
- Motion hanya untuk feedback state: 120–150ms, opacity/transform. `prefers-reduced-motion` → instan.

## 8. Ikon & gambar

- Lucide outline 1.5px: 16px di tabel, 20px di navigasi, warna mengikuti teks di sebelahnya.
- Ikon menemani label di navigasi dan tombol.
- Tidak ada ilustrasi stok, 3D, atau gambar dekoratif.

### 8.1 Wordmark SI-LEMBUR

Tidak ada logo gambar. Brand ditulis sebagai wordmark teks; jangan menggambar ikon/simbol untuknya.

| Tempat | Wordmark | Subjudul |
|---|---|---|
| Header form publik | `SI-LEMBUR` — Plus Jakarta Sans 700, 20px, `--ink`, letter-spacing 0.02em | "Input Lembur · PT Kenco" — 14px 500 `--ink-muted` |
| Sidebar panel | `SI-LEMBUR` — 700, 16px, `#ffffff` | "PT Kenco" — 12px `--sidebar-ink` |
| Panel login (latar `--sidebar`) | `SI-LEMBUR` — 700, 28px, `#ffffff` | "Sistem Informasi Lembur · PT Kenco Manufaktur Indonesia" — 14px `--sidebar-ink` |
| `<title>` halaman | `{Judul halaman} — SI-LEMBUR` | – |

- Selalu kapital dengan tanda hubung: **SI-LEMBUR**. Jangan "Si Lembur", "SiLembur", atau "SILEMBUR".
- Subjudul "Input Lembur" wajib tampil di form publik supaya fungsi aplikasi langsung jelas bagi semua karyawan.
- Favicon: huruf "SL" putih di kotak `--primary` radius 6px (SVG sederhana, bukan logo).

## 9. Copy

- Bahasa Indonesia formal-netral, **sentence case** ("Kirim lembur", bukan "Kirim Lembur"). Tanpa "kamu/lo", tanpa emoji.
- Form karyawan pakai kata sehari-hari yang pendek:

| Elemen | Teks |
|---|---|
| Brand + judul | SI-LEMBUR · Input Lembur |
| Field | Departemen · Nama · Tanggal lembur · Jam mulai · Jam selesai · Istirahat · Total lembur · Kegiatan lembur |
| Pilihan tanggal | Hari ini (20 Nov 2026) · Kemarin (19 Nov 2026) |
| Tombol | Lanjut periksa → Kirim lembur · Ubah |
| Sukses | "Lembur terkirim. Menunggu persetujuan supervisor." |
| Info lewat batas | "Total lembur melebihi 4 jam per hari. Pengajuan tetap dikirim dan akan diperiksa supervisor." |

- Angka format Indonesia: `2,5 jam`, `150 menit`. Tanggal `09 Nov 2026`. Jam 24 jam `23:00`.
- Konfirmasi menyebut konsekuensi, bukan "Yakin?":
  - Tolak: "Pengajuan ini akan ditolak. Karyawan bisa mengajukan ulang."
  - Batalkan persetujuan: "Pengajuan kembali ke status Menunggu dan keluar dari rekap sampai disetujui lagi."
- Empty state menyebut yang kosong + langkah berikutnya: "Belum ada pengajuan yang menunggu persetujuan."
- Pesan error menjelaskan cara memperbaiki (daftar pesan ada di `docs/BUSINESS_RULES.md` §2.1).

## 10. Dilarang

Gradient apa pun · glassmorphism / backdrop blur · neon glow · `#000000` · kartu dengan border kiri berwarna · warna status di luar success/warning/danger · dark mode / theme switch · emoji · ilustrasi stok, 3D blob, orang isometrik · shadow pada kartu biasa · lebih dari 1 tombol primary per layar · animasi berulang selain indikator LIVE.
