# Deploy SI-LEMBUR — Hostinger Shared/Cloud Hosting

Target: paket Hostinger (atau Niagahoster) yang mendukung **PHP 8.3, SSH, Composer, MySQL/MariaDB**. Tidak ada Node, Redis, Docker, atau queue worker di server.

> Verifikasi ulang langkah di bawah dengan dokumentasi hPanel terbaru saat deploy; menu bisa berubah.

## 1. Persiapan sekali

1. hPanel → buat **database MySQL** + user. Catat host, nama DB, user, password.
2. hPanel → **PHP Configuration**: pilih PHP 8.3; pastikan ekstensi `pdo_mysql`, `mbstring`, `zip`, `gd`, `xml`, `fileinfo`, `bcmath` aktif.
3. hPanel → aktifkan **SSH**, tambahkan public key.
4. hPanel → **SSL** aktif untuk domain/subdomain.
5. Struktur folder di server:
   ```
   ~/domains/<domain>/
     lembur-app/          # seluruh project Laravel
     public_html/         # document root → diarahkan ke lembur-app/public
   ```
   Arahkan document root ke `lembur-app/public` (lewat pengaturan subdomain/website di hPanel). Bila tidak bisa, ganti `public_html` dengan symlink:
   ```bash
   rm -rf public_html && ln -s lembur-app/public public_html
   ```

## 2. Build di lokal / CI

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build          # menghasilkan public/build
```

Upload ke `lembur-app/` (via `git pull` di server + upload `public/build`, atau rsync/SFTP seluruh folder kecuali `node_modules`, `.env`, `storage/logs/*`).

## 3. Di server (SSH)

```bash
cd ~/domains/<domain>/lembur-app
cp .env.example .env            # pertama kali saja, lalu isi
php artisan key:generate        # pertama kali saja
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`.env` produksi minimal:
```
APP_NAME="SI-LEMBUR"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<domain>
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
DB_CONNECTION=mysql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_LEVEL=warning
```

Permission: `storage/` dan `bootstrap/cache/` bisa ditulis oleh PHP.

## 4. Update rilis berikutnya

```bash
php artisan down
# upload kode + public/build baru
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## 5. Backup

- Mingguan: export database dari hPanel / phpMyAdmin, simpan di luar server.
- Aktifkan backup otomatis hPanel bila paket mendukung.
- Sebelum setiap migrasi produksi: backup manual dulu.

## 6. Checklist go-live

- [ ] `APP_DEBUG=false`, HTTPS aktif, cookie secure
- [ ] Akun admin produksi dibuat, password kuat; akun seeder contoh dihapus
- [ ] Master departemen, karyawan, supervisor, kalender libur tahun berjalan terisi
- [ ] Uji form publik dari HP karyawan via jaringan pabrik
- [ ] Uji approve dari HP supervisor
- [ ] Uji export dengan template asli
- [ ] Backup pertama tersimpan
