# TaromboBatak

Aplikasi silsilah dan dokumentasi budaya Batak. Menyimpan data anggota marga,
memvisualisasikan silsilah dalam bentuk pohon tarombo radial, serta
menyediakan alur review kontribusi agar data silsilah tetap terverifikasi
sebelum dipakai publik.

Dibangun dengan Laravel 13 dan Inertia.js 3 + React 19, dengan realtime
berbasis Laravel Reverb dan integrasi Telegram (Bot API + MTProto).

---

## Fitur Utama

| Fitur | Keterangan |
| --- | --- |
| **Pohon Tarombo** | Visualisasi silsilah radial berbasis React Flow, dengan navigasi zoom/pan, multi-root, dan pencarian orang. |
| **Data Anggota** | CRUD anggota, hubungan ayah/ibu/pasangan/anak, urutan lahir, dan nomor chain. |
| **Marga** | Daftar marga, identitas person (tokoh marga), gambar & warna, dan silsilah bawah per marga. |
| **Family Tree** | Pohon milik akun, versioning (duplikat/versi alternatif), berbagi via kode, dan sinkronisasi keturunan. |
| **Review Kontribusi** | Alur pengajuan → verifikasi → persetujuan untuk data marga, identitas, event, cerita, dan penghapusan pohon. |
| **Chat & Grup** | Chat personal, chat marga, grup dengan sinkronisasi Telegram, dan pengumuman massal. |
| **Realtime** | Pesan langsung via Reverb + Echo pada halaman kontak, grup, chat marga, dan pesan Telegram. |
| **Telegram** | Two-way: MTProto untuk akun user (self-service via QR/kode), Bot API untuk polling, import pesan, dan pengumuman. |
| **Berita Marga** | Pengambilan berita otomatis lewat Hermes API, dengan topik, sumber website, dan review admin. |
| **Konten** | Cerita leluhur, event/kegiatan, news feed berstatus + like/komentar. |
| **Administrasi** | Manajemen akun, sub-admin, traffic monitor, log aktivitas, dan audit. |
| **Privasi** | `is_public` default private; hanya staff yang dapat mempublikasikan person. |

---

## Stack

**Backend** — Laravel `13.23` · PHP `^8.3` · Fortify `^1.37` · Socialite `^5.30` · Wayfinder `^0.1` · Reverb `^1.0` · MadelineProto `^8.7`

**Frontend** — Inertia.js `^3.0` + React `^19.2` · TypeScript `^5.7` · Tailwind CSS `^4.0` (CSS-first config) · Vite `^8.0` · React Flow (`@xyflow/react` `^12.11`) · Radix UI + shadcn/ui · Framer Motion · Sonner · Lucide React

**Kualitas** — Pest `^4.7` · Larastan/PHPStan level `7` · Laravel Pint (preset `laravel`) · ESLint + Prettier · GitHub Actions

**Database** — MySQL (dev/prod) · SQLite (testing, `:memory:`)

---

## Kebutuhan Sistem

- PHP 8.3 atau lebih baru, dengan ekstensi `mbstring`, `intl`, `xml`, `zip`, `bcmath`
- Composer 2
- Node.js 22 atau lebih baru
- MySQL untuk development dan production
- Redis (opsional, untuk cache dan queue bila dikonfigurasi)

---

## Instalasi

```bash
git clone https://github.com/VeeNevire/TaromboBatak.git
cd TaromboBatak

composer run setup
php artisan storage:link
php artisan db:seed
```

`composer run setup` menjalankan `composer install`, menyalin `.env.example` ke
`.env`, membuat `APP_KEY`, memigrasi database, `npm install`, dan build asset.

> **Penting:** `db:seed` membuat akun admin (`admin@example.com`) dan user uji
> (`user@example.com`) dengan password `password`, plus data contoh silsilah,
> marga, konten landing, dan topik berita. Ganti password sebelum dipakai di
> lingkungan nyata.

Untuk pengembangan lokal, jalankan empat proses sekaligus (server, queue, Vite,
Reverb):

```bash
composer run dev
```

Atau jalankan manual untuk environment yang lebih stabil:

```bash
php artisan serve
php artisan queue:work --tries=1 --timeout=0 --sleep=1
npm run dev
php artisan reverb:start
```

> Di Windows, jalankan Reverb secara terpisah bila perlu:
> `reverb-autostart.bat`.

---

## Perintah Harian

### Composer

| Perintah | Fungsi |
| --- | --- |
| `composer run setup` | Instalasi awal end-to-end |
| `composer run dev` | Jalankan server, queue, Vite, dan Reverb bersamaan |
| `composer run lint` | Format kode PHP (Pint) |
| `composer run lint:check` | Periksa format PHP tanpa mengubah |
| `composer run types:check` | PHPStan level 7 |
| `composer run test` | Config clear + lint check + types + Pest |
| `composer run ci:check` | Semua gate kualitas + test (dipakai CI) |

### NPM

| Perintah | Fungsi |
| --- | --- |
| `npm run dev` | Vite dev server dengan HMR |
| `npm run build` | Build asset production ke `public/build` |
| `npm run lint` / `lint:check` | ESLint |
| `npm run format` / `format:check` | Prettier |
| `npm run types:check` | TypeScript (`tsc --noEmit`) |

### Artisan

| Perintah | Fungsi |
| --- | --- |
| `php artisan people:recompute-chain` | Hitung ulang chain seluruh rumpun patrilineal (jalankan setelah import atau perbaikan data) |
| `php artisan people:unlink-pending-fathers` | Lepaskan keluarga yang ayahnya belum diketahui dari root sementara, lalu hitung ulang chain |
| `php artisan tarombo:audit-local-only-links` | Daftar node yang penempatan ayah-nya di pohon lokal berbeda dengan `father_id` global (read-only) |
| `php artisan tarombo:fix-local-only-links` | Hubungkan placement ayah lokal ke `father_id` global dan sinkronkan pohon |
| `php artisan telegram:mtproto-listen` | Dengarkan pesan akun Telegram user via MTProto |
| `php artisan telegram:poll` | Terima update Telegram via long-polling (Bot API) |
| `php artisan wayfinder:generate --with-form` | Regenerasi helper route frontend |
| `php artisan schedule:run` | Jalankan scheduler (dipakai cron untuk otomatisasi berita) |

---

## Role & Hak Akses

Sistem memakai lima role, disimpan pada `users.role`, dengan cakupan (scope)
berdasarkan marga.

| Role | Akses |
| --- | --- |
| `admin` | Semua akses staff global, manajemen akun dan sub-admin, traffic monitor, sumber/topik berita, frame tarombo. |
| `subadmin` | Akses staff global untuk person, marga, cerita, kegiatan, dan berita. Aktivitasnya diaudit ke `activity_logs`. |
| `contributor_main` | Kontributor utama: meninjau kontribusi, identitas, event, cerita, dan permintaan akses marga. |
| `contributor_member` | Kontributor anggota: cakupan review lebih terbatas, umumnya per marga. |
| `user` | Melihat data marganya, membuat keluarga sendiri, dan mengubah keluarga yang dibuatnya. Tidak dapat mempublikasikan. |

Cakupan role diterapkan di tiga lapis: middleware (alias `role.admin`,
`role.staff`, `role.contributor`), policy (9 policy di `app/Policies`), dan
helper pada model `User` (`isAdmin`, `isStaff`, `isContributor`,
`accessibleMargaIds`, dan lainnya).

> Hanya staff yang dapat mempublikasikan person. Data person baru bernilai
> private secara default.

---

## Struktur Project

```
app/
├── Actions/            Aksi reusable (Fortify, token link Telegram)
├── Concerns/           Trait validasi (password, profil)
├── Console/Commands/   6 artisan command
├── Contracts/          Interface (TelegramBot)
├── Events/             4 event broadcast
├── Exceptions/
├── Http/
│   ├── Controllers/    55 controller (Settings, Auth, Api)
│   ├── Middleware/     8 middleware
│   └── Requests/       55 form request
├── Jobs/               3 queued job
├── Models/             48 model Eloquent
├── Notifications/      6 notifikasi
├── Policies/           9 policy
├── Services/           32 service domain
└── Support/            Helper (IndonesiaRegions, PersonShareCode)

resources/
├── js/
│   ├── components/     90 komponen (ui, landing, people, tarombo, ...)
│   ├── data/           Modul data statis & algoritma tarombo-tree.ts
│   ├── hooks/          8 custom hook
│   ├── layouts/        9 layout
│   ├── lib/            Helper (utils, GA, komposisi canvas)
│   ├── pages/          64 halaman Inertia
│   ├── routes/         Dibuat Wayfinder (generated)
│   └── actions/        Dibuat Wayfinder (generated)
└── css/app.css         Tailwind v4 + token tema Batak (tb-*)

database/
├── migrations/         108 migrasi
├── seeders/            9 seeder
└── factories/          14 factory

tests/
├── Feature/            66 feature test
└── Unit/               1 unit test
```

Ringkasan: 55 controller, 32 service, 48 model, 64 halaman Inertia, 90
komponen, 108 migrasi, 67 file test.

---

## Testing & Quality Gate

```bash
composer run ci:check
```

Menjalankan, berurutan: `pint --test` (format PHP), `prettier --check`,
`tsc --noEmit` (type check), `phpstan analyse` (level 7), dan `php artisan test`
(Pest).

CI (`.github/workflows/ci.yml`) berjalan pada setiap PR dan push ke `main`:

- Build asset production (`npm run build`) — memastikan tidak ada error kompilasi
- `composer audit` dan `npm audit --audit-level=high`
- Memastikan `public/hot` tidak ada (mencegah artefak dev ikut ter-deploy)
- Testing memakai SQLite in-memory

Database testing memakai SQLite `:memory:`, sehingga test tidak memerlukan MySQL.

---

## Dokumentasi

Dokumentasi detail dipisah ke folder `docs/`:

| Dokumen | Isi |
| --- | --- |
| [docs/arsitektur.md](docs/arsitektur.md) | Layer aplikasi, alur request Inertia, peta controller/service/model, entry point frontend, token tema, Wayfinder. |
| [docs/domain-silsilah.md](docs/domain-silsilah.md) | Struktur data person, chain numbering, family tree & versioning, tree protection, batas payload, alur render pohon. |
| [docs/telegram.md](docs/telegram.md) | Bot API vs MTProto, pipeline pesan, grup, pengumuman, konfigurasi listener. |
| [docs/hermes-agent-berita-marga.md](docs/hermes-agent-berita-marga.md) | Otomatisasi berita marga via Hermes API. |
| [docs/deployment.md](docs/deployment.md) | Alur deploy ke VPS, Supervisor, cron scheduler, kompresi respons, permission. |
| [docs/configs.md](docs/configs.md) | Referensi seluruh environment variable dan konfigurasi. |
| [AUDIT_PROJECT_TAROMBOBATAK.md](AUDIT_PROJECT_TAROMBOBATAK.md) | Riwayat audit engineering: temuan, status implementasi, dan residual risk. |
| [tarombo-batak-chain-numbering.md](tarombo-batak-chain-numbering.md) | Spesifikasi konseptual sistem penomoran chain. |

---

## Deploy

Ringkasan alur deploy (push ke `main` → GitHub Actions → SSH ke VPS):

1. `.github/workflows/deploy.yml` melakukan SSH ke VPS, `git pull`, `composer install`, `npm run build`, `php artisan migrate --force`, generate Wayfinder, cache konfigurasi, restart PHP-FPM/Nginx/Reverb/queue.
2. Listener MTProto dan worker dijalankan lewat Supervisor (`deploy/supervisor/tarombobatak.conf`).
3. Scheduler berjalan tiap menit via cron untuk otomatisasi berita.

Detail lengkap, termasuk konfigurasi Nginx/Apache untuk kompresi respons,
tercantum di [docs/deployment.md](docs/deployment.md).

---

## Catatan Penting

- **`public/hot` tidak boleh ikut deploy.** File tersebut adalah penanda Vite
  dev server. Production harus menjalankan `npm run build` dan memakai asset
  dari `public/build`. CI sudah memverifikasi `public/hot` tidak ada.
- **Kompresi respons.** Data pohon tarombo dikirim sebagai JSON Inertia yang
  bisa melebihi 1 MB untuk silsilah besar. Aktifkan `mod_deflate` (Apache) atau
  `gzip` (Nginx) agar ukurannya mengecil.
- **MadelineProto (MTProto) berjalan ±10× lebih lambat di Windows** karena
  keterbatasan OS/PHP. Untuk performa maksimal, jalankan di Linux atau macOS.
- **Tidak ada test runner frontend.** Proyek hanya memiliki test PHP (Pest).
  Transformasi pohon di frontend diverifikasi melalui TypeScript, ESLint, dan
  build.

