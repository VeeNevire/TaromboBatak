# Deployment

Dokumen ini menjelaskan cara deploy TaromboBatak ke VPS dan apa yang harus
disiapkan sebelum release.

Kembali ke [README](../README.md).

---

## Kebutuhan Server

| Komponen | Keterangan |
| --- | --- |
| PHP-FPM 8.3 | Ekstensi: `mbstring`, `intl`, `xml`, `zip`, `bcmath`, `pdo_mysql`. `memory_limit=2G` advisable (upscaling gambar). |
| Nginx | Web server + reverse proxy ke Reverb bila perlu. |
| MySQL | Database aplikasi. |
| Node.js 22 | Hanya saat build asset. |
| Supervisor | Menjalankan listener MTProto, queue worker, dan Reverb. |
| Cron | Menjalankan `php artisan schedule:run` tiap menit. |

> Untuk MTProto, gunakan **Linux atau macOS**. MadelineProto berjalan ~10× lebih
> lambat di Windows.

---

## Alur Otomatis (GitHub Actions)

File: `.github/workflows/deploy.yml`. Trigger: push ke branch `main`.

Secret yang diperlukan di repository settings:

| Secret | Isi |
| --- | --- |
| `SERVER_HOST` | Alamat VPS. |
| `SERVER_USER` | User SSH (mis. `root`). |
| `SSH_PRIVATE_KEY` | Kunci privat SSH. |
| `SERVER_PATH` | Path project di VPS (mis. `/var/www/tarombobatak`). |

Langkah di VPS (`appleboy/ssh-action`):

1. `git pull origin main`
2. `composer install --no-dev --optimize-autoloader`
3. Muat NVM, `nvm use 22`
4. `npm install`
5. `php artisan migrate --force`
6. `php artisan route:clear`
7. `php artisan wayfinder:generate --with-form`
8. `npm run build`
9. `php artisan optimize:clear`
10. Cache ulang: `config:cache`, `route:cache`, `view:cache`
11. Permission: `chmod -R 777 storage bootstrap/cache`, `chown -R www-data:www-data storage bootstrap/cache`
12. Restart `php8.3-fpm`, reload `nginx`
13. Restart Supervisor Reverb + `php artisan queue:restart`

### Urutan yang penting

`route:clear` **harus** dijalankan sebelum `wayfinder:generate`, karena
perintah generate membaca daftar route dari cache. Jalankan
`optimize:clear` **setelah** build dan generate, sebelum caching ulang.

---

## Persiapan Manual (Sekali Saja)

### 1. Clone dan environment

```bash
cd /var/www/tarombobatak
git clone https://github.com/VeeNevire/TaromboBatak.git .
cp .env.example .env
php artisan key:generate
```

Isi `.env` untuk produksi. Lihat [configs.md](configs.md) untuk referensi
lengkap. Pastikan minimal:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tarombobatak
DB_USERNAME=...
DB_PASSWORD=...

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

> **`APP_DEBUG=false` wajib di produksi.** Jangan pernah membocorkan
> `APP_KEY` maupun kredensial database.

Buat database lalu migrasi dan seed awal:

```bash
php artisan migrate --force
php artisan db:seed        # hanya sekali, untuk akun admin pertama
```

Setelah seeding, **segera ganti password** akun `admin@example.com`.

### 2. Storage & permission

```bash
php artisan storage:link
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

Direktori berikut **wajib** bisa ditulis oleh `www-data`:

- `storage/app` (termasuk `storage/app/private/telegram/sessions`)
- `storage/framework/{cache,sessions,views}`
- `storage/logs`
- `bootstrap/cache`

> Storage session MTProto berisi kredensial akun Telegram. **Jangan pernah**
> mengekspos folder ini ke publik.

### 3. Supervisor

```bash
sudo cp deploy/supervisor/tarombobatak.conf /etc/supervisor/conf.d/tarombobatak.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

File contoh berisi tiga program:

| Program | Command | `numprocs` |
| --- | --- | --- |
| `tarombobatak-mtproto` | `php artisan telegram:mtproto-listen --all` | 1 |
| `tarombobatak-queue` | `php artisan queue:work database --sleep=3 --tries=3 --timeout=120 --max-time=3600` | 2 |
| `tarombobatak-reverb` | `php artisan reverb:start` | 1 |

> Program Reverb bernama `tarombobatak-reverb` (bukan `tarombo-reverb`).
> Sesuaikan perintah `supervisorctl` di `deploy.yml` bila Anda mengganti nama.

**Wajib menyesuaikan** di file Supervisor:

- `directory` — path project di VPS.
- `user` — user OS (biasanya `www-data`).
- `stdout_logfile` — path log (folder harus ada).
- Session path MTProto bila tidak memakai default.

`stopwaitsecs=3600` pada program MTProto disengaja: proses listener diberi waktu
menutup koneksi dengan rapi.

### 4. Cron scheduler

Untuk otomatisasi berita marga, scheduler harus berjalan tiap menit:

```cron
* * * * * cd /var/www/tarombobatak && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler menjalankan `MargaNewsAutomationRunner::runIfDue()` setiap menit,
lalu memanggil Hermes hanya bila waktu run berikutnya sudah tercapai. Leased
(`run_lease_until`) mencegah proses tumpang tindih.

Verifikasi:

```bash
php artisan schedule:list
```

### 5. Kompresi respons

Data pohon tarombo dikirim sebagai JSON Inertia yang bisa **melebihi 1 MB**
untuk silsilah besar. Aktifkan kompresi agar ukurannya menyusut drastis.

#### Apache

`public/.htaccess` sudah berisi blok `mod_deflate`. Pastikan modul aktif:

```apache
LoadModule deflate_module modules/mod_deflate.so
LoadModule filter_module modules/mod_filter.so
```

#### Nginx

Tambahkan di blok `server`:

```nginx
gzip on;
gzip_comp_level 5;
gzip_min_length 1024;
gzip_types application/json application/javascript text/css text/plain image/svg+xml;
```

> `application/json` penting karena halaman tarombo mengirim payload JSON
> Inertia.

---

## Peringatan Deployment

### Jangan pernah deploy `public/hot`

`public/hot` adalah penanda bahwa Vite dev server sedang dipakai. Bila file ini
ada di server, Laravel akan mengembalikan seluruh URL asset ke
dev server dan development berjalan di produksi.

- File ini sudah masuk `.gitignore`.
- CI memverifikasi `public/hot` tidak ada (`test ! -f public/hot`).
- Produksi **harus** memakai asset hasil `npm run build` di `public/build`.

### Jangan commit `.env`

`.env`, `.env.backup`, dan `.env.production` sudah masuk `.gitignore`. Secret
(token bot, API key, kredensial DB) hanya boleh ada di server.

### `optimize:clear` sebelum cache

Bila `config:cache` sudah aktif dan Anda mengubah `.env`, konfigurasi lama
 masih terpakai. Urutan yang aman:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Checklist Rilis

- [ ] `APP_DEBUG=false` dan `APP_ENV=production` di `.env`
- [ ] Password akun admin sudah diganti dari `password`
- [ ] `php artisan migrate --force` selesai tanpa error
- [ ] `npm run build` selesai, `public/build` terisi
- [ ] `public/hot` tidak ada
- [ ] `php artisan storage:link` sudah dibuat
- [ ] Permission `storage` dan `bootstrap/cache` benar (www-data)
- [ ] Supervisor: mtproto, queue, reverb berjalan
- [ ] Cron `schedule:run` tiap menit terpasang
- [ ] Kompresi (Apache `mod_deflate` atau Nginx `gzip`) aktif
- [ ] `php artisan config:cache` dan `route:cache` dijalankan

---

## Rollback

Tidak ada mekanisme rollback otomatis. Bila release bermasalah:

```bash
cd /var/www/tarombobatak
git log --oneline -5          # cari commit sebelum release
git revert <commit-hashes>    # buat commit revert, lalu push
```

Migrasi database yang sudah berjalan **tidak** di-rollback otomatis. Periksa
`git log -- database/migrations` untuk melihat perubahan skema yang menyertai
release, dan siapkan statement SQL manual bila perlu.

Untuk kebutuhan emergency restore data, backup database sebelum deploy:

```bash
mysqldump -u <user> -p <database> > backup-$(date +%Y%m%d-%H%M%S).sql
```

---

## Links

- [configs.md](configs.md) — referensi environment variable
- [telegram.md](telegram.md) — konfigurasi listener MTProto
- [hermes-agent-berita-marga.md](hermes-agent-berita-marga.md) — cron scheduler untuk berita
- `.github/workflows/deploy.yml` — pipeline deploy
- `deploy/supervisor/tarombobatak.conf` — contoh Supervisor
