# TaromboBatak

Aplikasi silsilah Batak berbasis Laravel, Inertia React, TypeScript, dan Tailwind CSS.

## Persyaratan

- PHP 8.3 atau lebih baru
- Composer
- Node.js 22 atau lebih baru
- MySQL untuk development/production, atau SQLite untuk testing

## Instalasi

```bash
composer run setup
php artisan storage:link
composer run dev
```

Salin dan sesuaikan `.env.example` sebelum menjalankan migrasi. Data person baru bersifat private secara default.

## Role

- `admin`: akses staff global dan manajemen sub-admin.
- `subadmin`: akses staff global untuk person, marga, cerita, dan kegiatan.
- `user`: melihat data marganya, membuat keluarga sendiri, dan mengubah keluarga yang dibuatnya.

Hanya staff yang dapat mempublikasikan person. Payload publik hanya memuat identitas genealogis ringkas dan dibatasi oleh konfigurasi `TAROMBO_PUBLIC_*`.

## Quality Checks

```bash
composer run ci:check
npm run build
```

## Maintenance

Hitung ulang seluruh chain silsilah setelah import atau perbaikan data:

```bash
php artisan people:recompute-chain
```

### Production Telegram MTProto

Gunakan Supervisor untuk menjalankan listener MTProto secara paralel. Mode
`--all` membuat child process untuk setiap akun yang memiliki `session_path`
MTProto dan `connection_status=connected`, lalu otomatis mengambil user baru
yang connect tanpa membutuhkan ID user.

```bash
sudo cp deploy/supervisor/tarombobatak.conf /etc/supervisor/conf.d/tarombobatak.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Pastikan `directory`, `user`, storage/session Telegram, dan permission log
disesuaikan dengan server production. Worker MTProto dan queue akan restart
otomatis jika proses berhenti.

Jangan deploy file `public/hot`. Deployment production harus menjalankan `npm run build` dan menggunakan asset dari `public/build`.

## Audit

Status audit engineering dan keputusan prioritas tersedia di `AUDIT_PROJECT_TAROMBOBATAK.md`.
