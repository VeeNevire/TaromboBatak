# Referensi Konfigurasi

Semua environment variable dan file konfigurasi yang relevan di TaromboBatak.

Kembali ke [README](../README.md).

---

## Sumber Konfigurasi

| File | Isi |
| --- | --- |
| `.env.example` | Template environment. Salin ke `.env` untuk mulai. |
| `config/tarombo.php` | Batas kedalaman & jumlah node pohon tarombo. |
| `config/services.php` | Kredensial layanan pihak ketiga. |
| `config/broadcasting.php`, `config/reverb.php` | Konfigurasi realtime. |
| `config/fortify.php` | Autentikasi (login, registrasi, rate limit). |
| `config/inertia.php` | Konfigurasi Inertia SSR/asset. |

---

## Aplikasi

| Variabel | Default | Keterangan |
| --- | --- | --- |
| `APP_NAME` | `TaromboBatak` | Nama aplikasi, dipakai juga sebagai judul halaman. |
| `APP_ENV` | `local` | Set `production` di server. |
| `APP_KEY` | — | Dibuat `php artisan key:generate`. **Rahasia.** |
| `APP_DEBUG` | `true` | **Set `false` di produksi.** |
| `APP_URL` | `http://localhost:8000` | URL dasar aplikasi. |
| `APP_LOCALE` | `id` | Locale aplikasi. |
| `APP_FALLBACK_LOCALE` | `id` | Locale cadangan. |
| `APP_FAKER_LOCALE` | `id_ID` | Locale data factory/testing. |
| `APP_MAINTENANCE_DRIVER` | `file` | Mode maintenance (`file` atau `database`). |
| `BCRYPT_ROUNDS` | `12` | Strength hash password. |

---

## Database

| Variabel | Default | Keterangan |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql` | Use `sqlite` untuk testing. |
| `DB_HOST` | `127.0.0.1` | |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | `tarombobatak` | |
| `DB_USERNAME` | `root` | |
| `DB_PASSWORD` | — | **Rahasia.** |

Untuk testing, `phpunit.xml` memaksa `sqlite` dengan `:memory:` sehingga test
tidak memerlukan MySQL.

---

## Session, Cache, Queue

| Variabel | Default | Keterangan |
| --- | --- | --- |
| `SESSION_DRIVER` | `database` | |
| `SESSION_LIFETIME` | `120` | Menit. |
| `SESSION_ENCRYPT` | `false` | |
| `QUEUE_CONNECTION` | `database` | Diperlukan untuk job Telegram & pengumuman. |
| `CACHE_STORE` | `database` | Menyimpan lock `ChainNumberingService` dan poller. |
| `BROADCAST_CONNECTION` | `reverb` | Wajib untuk realtime. |
| `FILESYSTEM_DISK` | `local` | Disk default untuk upload. |

> `CACHE_STORE` tidak boleh diganti ke `array` di produksi — distributed lock
> untuk chain numbering dan poller Telegram bergantung padanya.

---

## Redis (Opsional)

| Variabel | Default |
| --- | --- |
| `REDIS_CLIENT` | `phpredis` |
| `REDIS_HOST` | `127.0.0.1` |
| `REDIS_PASSWORD` | `null` |
| `REDIS_PORT` | `6379` |

Hanya dipakai bila `CACHE_STORE=redis` atau `QUEUE_CONNECTION=redis`.

---

## Batas Pohon Tarombo

Dikonfigurasi di `config/tarombo.php`:

| Variabel | Default config | Nilai `.env.example` | Arti |
| --- | --- | --- | --- |
| `TAROMBO_PUBLIC_MAX_DEPTH` | 11 | 6 | Kedalaman pohon publik. |
| `TAROMBO_PUBLIC_MAX_NODES` | 500 | 500 | Jumlah node pohon publik. |
| `TAROMBO_PERSON_MAX_DEPTH` | 5 | 5 | Kedalaman silsilah per orang. |
| `TAROMBO_PERSON_MAX_NODES` | 500 | 500 | Jumlah node silsilah per orang. |
| `TAROMBO_DASHBOARD_MAX_DEPTH` | 40 | — | Kedalaman pohon dashboard (butuh login). |
| `TAROMBO_DASHBOARD_MAX_NODES` | 3000 | — | Jumlah node pohon dashboard. |

Nilai di `.env.example` lebih ketat dari default config untuk development.
Tinggikan `TAROMBO_PUBLIC_*` dengan hati-hati karena memengaruhi ukuran payload
JSON yang dikirim ke browser.

---

## Telegram

| Variabel | Default | Keterangan |
| --- | --- | --- |
| `TELEGRAM_BOT_TOKEN` | — | Wajib untuk `telegram:poll` (Bot API). |
| `TELEGRAM_BOT_USERNAME` | — | Nama bot. |
| `TELEGRAM_API_ID` | — | Wajib untuk MTProto. |
| `TELEGRAM_API_HASH` | — | Wajib untuk MTProto. **Rahasia.** |
| `TELEGRAM_MTPROTO_SESSION_PATH` | `storage/app/private/telegram/sessions` | Lokasi file session. |

Detail ada di [telegram.md](telegram.md).

---

## Hermes (Berita Marga Otomatis)

| Variabel | Default | Keterangan |
| --- | --- | --- |
| `HERMES_BASE_URL` | — | Base URL API Hermes. Untuk tunnel lokal: `http://127.0.0.1:18642/v1`. |
| `HERMES_TOKEN` | — | Bearer token opsional. Falls back ke `API_SERVER_KEY`. |
| `API_SERVER_KEY` | — | Dipakai bila `HERMES_TOKEN` kosong. |
| `HERMES_RUNS_ENDPOINT` | `/runs` | Endpoint POST/GET run. |
| `HERMES_TIMEOUT` | `90` | Timeout HTTP (detik). |
| `HERMES_RUN_TIMEOUT` | `300` | Batas waktu total run (detik). |
| `HERMES_POLL_SECONDS` | `2` | Interval polling status run. |

Scheduler memanggil Hermes tiap menit bila waktu run tercapai. Prompt dan jeda
diatur dari dashboard admin, bukan dari `.env`.

Detail ada di [hermes-agent-berita-marga.md](hermes-agent-berita-marga.md).

---

## OpenAI (Upscale Gambar Frame)

| Variabel | Default | Keterangan |
| --- | --- | --- |
| `OPENAI_API_KEY` | — | Diperlukan oleh `TaromboFrameUpscaler`. **Rahasia.** |
| `OPENAI_IMAGE_MODEL` | `gpt-image-1.5` | Model yang dipakai untuk upscale. |

Bila kosong, fitur upscale melempar pesan "Generator AI belum dikonfigurasi".
Fitur lain tetap berjalan normal.

---

## Google (OAuth & Analytics)

| Variabel | Keterangan |
| --- | --- |
| `GOOGLE_CLIENT_ID` | OAuth client ID. |
| `GOOGLE_CLIENT_SECRET` | OAuth client secret. **Rahasia.** |
| `GOOGLE_REDIRECT_URI` | Default `${APP_URL}/auth/google/callback`. |
| `GOOGLE_ANALYTICS_MEASUREMENT_ID` | Aktifkan GA4 bila diisi. |
| `GOOGLE_ANALYTICS_REPORT_EMBED_URL` | URL embed laporan analytics di dashboard. |

Login Google meminta scope `openid profile email`. Email wajib terverifikasi.

> Tracking page-view memakai `send_page_view: false` di Blade karena Inertia
> mengirim page view via event `router.on('navigate')`.

---

## Cloudflare Turnstile

| Variabel | Keterangan |
| --- | --- |
| `TURNSTILE_SITE_KEY` | Kunci situs widget. |
| `TURNSTILE_SECRET_KEY` | Kunci verifikasi server. **Rahasia.** |

Bila keduanya terisi, formulir pendaftaran memverifikasi token Turnstile lewat
`app/Actions/Fortify/VerifyTurnstile.php`.

---

## Reverb (Realtime)

| Variabel | Default | Keterangan |
| --- | --- | --- |
| `REVERB_APP_ID` | `tarombo-local` | |
| `REVERB_APP_KEY` | `tarombo-local-key` | |
| `REVERB_APP_SECRET` | `tarombo-local-secret` | **Rahasia.** |
| `REVERB_HOST` | `localhost` | |
| `REVERB_PORT` | `8080` | |
| `REVERB_SCHEME` | `http` | Set `https` di produksi. |
| `REVERB_ALLOWED_ORIGINS` | `${APP_URL}` | Wajib di produksi. |

Variabel yang diteruskan ke frontend (`VITE_REVERB_*`) harus selalu disetel
bersamaan dengan `REVERB_*`, karena frontend memakai:

```dotenv
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

> Mengubah `REVERB_*` berarti harus menjalankan `npm run build` ulang agar
> `VITE_*` ikut ter-inline ke bundle.

---

## Frontend (Vite)

| Variabel | Keterangan |
| --- | --- |
| `VITE_APP_NAME` | Default `${APP_NAME}`. Dipakai sebagai template judul halaman. |

---

## Mail

| Variabel | Default | Keterangan |
| --- | --- | --- |
| `MAIL_MAILER` | `log` | Ganti untuk kirim email sungguhan. |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | |
| `MAIL_FROM_NAME` | `${APP_NAME}` | |

Driver yang tersedia di `config/services.php`: `postmark`, `resend`, `ses`
(AWS), `slack`. Untuk email, isi juga `POSTMARK_KEY`, `RESEND_KEY`, atau kredensial
AWS sesuai driver yang dipakai.

---

## AWS / S3

| Variabel | Default |
| --- | --- |
| `AWS_ACCESS_KEY_ID` | — |
| `AWS_SECRET_ACCESS_KEY` | — |
| `AWS_DEFAULT_REGION` | `us-east-1` |
| `AWS_BUCKET` | — |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false` |

Dipakai bila `FILESYSTEM_DISK=s3` atau driver mail `ses`. Rahasia:
`AWS_SECRET_ACCESS_KEY`.

---

## Variabel Rahasia

Semua nilai berikut **tidak boleh** masuk ke git:

```
APP_KEY
DB_PASSWORD
TELEGRAM_BOT_TOKEN
TELEGRAM_API_HASH
HERMES_TOKEN / API_SERVER_KEY
OPENAI_API_KEY
GOOGLE_CLIENT_SECRET
TURNSTILE_SECRET_KEY
REVERB_APP_SECRET
AWS_SECRET_ACCESS_KEY
POSTMARK_KEY / RESEND_KEY
```

`.env`, `.env.backup`, dan `.env.production` sudah masuk `.gitignore`. Untuk
production, simpan `.env` hanya di server.

---

## Testing

`phpunit.xml` mengoverride environment berikut agar test terisolasi:

```
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
BCRYPT_ROUNDS=4
BROADCAST_CONNECTION=null
CACHE_STORE=array
MAIL_MAILER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
```

Override ini tidak perlu disalin ke `.env` — biarkan default development tetap
MySQL.

---

## Perintah Konfigurasi

| Perintah | Fungsi |
| --- | --- |
| `php artisan key:generate` | Buat `APP_KEY` baru. |
| `php artisan config:clear` | Hapus cache konfigurasi. |
| `php artisan config:cache` | Cache konfigurasi (produksi). |
| `php artisan route:cache` | Cache route (produksi). |
| `php artisan route:clear` | Hapus cache route (wajib sebelum `wayfinder:generate`). |
| `php artisan view:cache` | Cache compiled view. |
| `php artisan optimize:clear` | Hapus semua cache. Jalankan setelah ubah `.env`. |
| `php artisan storage:link` | Symlink `public/storage`. |
| `php artisan about` | Ringkasan konfigurasi aplikasi. |
