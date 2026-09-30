# Integrasi Telegram

Dokumen ini menjelaskan dua jalur integrasi Telegram di TaromboBatak: **Bot API**
untuk pesan masuk bot dan **MTProto** untuk akun user. Keduanya dipakai
bersamaan.

Kembali ke [README](../README.md).

---

## Dua Jalur

| | Bot API | MTProto |
| --- | --- | --- |
| **Perpustakaan** | `TelegramBotApi` (wrapper HTTP) | `danog/madelineproto` |
| **Model akun** | Tidak perlu akun user | `telegram_accounts` |
| **Cara terima update** | Long polling (`telegram:poll`) | Listener persistent |
| **Cara kirim** | `sendMessage` | `sendMessage`, `sendDocument` |
| **Command** | `php artisan telegram:poll` | `php artisan telegram:mtproto-listen` |

Keduanyaalkan pesan ke tabel `telegram_messages` yang sama, sehingga UI
`/telegram/messages` menampilkan gabungan keduanya.

---

## Konfigurasi

Isi di `.env`:

```dotenv
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=

TELEGRAM_API_ID=
TELEGRAM_API_HASH=
TELEGRAM_MTPROTO_SESSION_PATH=storage/app/private/telegram/sessions
```

| Variabel | Dipakai oleh | Keterangan |
| --- | --- | --- |
| `TELEGRAM_BOT_TOKEN` | Bot API | Wajib untuk `telegram:poll`. |
| `TELEGRAM_BOT_USERNAME` | Tampilan | Nama bot. |
| `TELEGRAM_API_ID` / `TELEGRAM_API_HASH` | MTProto | Wajib. Ambil dari my.telegram.org. |
| `TELEGRAM_MTPROTO_SESSION_PATH` | MTProto | Lokasi file session lokal. Default `storage/app/private/telegram/sessions`. |

`TelegramMtproto::configured()` hanya mengembalikan `true` bila `TELEGRAM_API_ID`
dan `TELEGRAM_API_HASH` terisi. Bila tidak, fitur MTProto melempar
`App\Exceptions\TelegramNotConfigured`.

> Direktori session harus **tidak** dapat diakses publik. Jangan taruh di
> `public/`.

---

## Jalur 1 — Bot API (long polling)

```bash
php artisan telegram:poll          # berjalan terus
php artisan telegram:poll --once   # satu batch lalu berhenti
```

### Cara kerja

1. Command memeriksa `TELEGRAM_BOT_TOKEN`. Kosong → gagal dengan pesan jelas.
2. `getWebhookInfo()` dicek. Bila bot masih memakai webhook, command **berhenti**
   dan meminta webhook dihapus lebih dulu.
3. Update `pending` dan `failed` yang tertinggal di-dispatch ulang.
4. Loop: ambil distributed lock `telegram:poller` (TTL 40 detik) → baca offset
   dari `max(update_id)` → `getUpdates(offset)` → `firstOrCreate` setiap update
   → dispatch `ProcessTelegramUpdate`.

Lock memastikan hanya satu poller aktif, sehingga aman bila command dijalankan
di beberapa server.

### Idempotency

Tabel `telegram_updates` menjadi ledger idempotency:

| Kolom | Fungsi |
| --- | --- |
| `update_id` | Kunci unik dari Telegram. |
| `payload` | JSON mentah. |
| `status` | `pending` → `processed` / `failed`. |
| `attempts` | Jumlah percobaan. |
| `last_error`, `processed_at` | Diagnostik. |

`firstOrCreate(['update_id' => …])` memastikan update yang sama tidak diproses
dua kali.

### Pipeline

```
telegram:poll
    ↓
telegram_updates  (idempotency)
    ↓
ProcessTelegramUpdate  (job, ShouldBeUnique, uniqueFor 300, 5 tries,
                        backoff [2, 10, 30, 60])
    ↓
TelegramUpdateProcessor
    ↓
TelegramMessageImporter → telegram_messages
    ↓
TelegramMessageReceived (event, ShouldBroadcastNow → Reverb)
```

`TelegramMessageReceived` memakai `ShouldBroadcastNow` supaya UI menampilkan
pesan tanpa menunggu commit antrean.

---

## Jalur 2 — MTProto (akun user)

Pengguna menghubungkan akun Telegram-nya sendiri lewat
**Pengaturan → Koneksi Telegram MTProto**.

### Alur koneksi

| Langkah | Endpoint | Method controller |
| --- | --- | --- |
| Mulai (nomor telepon) | `POST /settings/telegram/mtproto` | `store` |
| Mulai login via QR | `POST .../qr` | `qr` |
| Cek status QR | `GET .../qr/status` | `qrStatus` |
| Verifikasi kode | `POST .../code` | `verifyCode` |
| Kirim ulang kode | `POST .../resend` | `resendCode` |
| Verifikasi password 2FA | `POST .../password` | `verifyPassword` |
| Putuskan sambungan | `DELETE ...` | `destroy` |

Semua endpoint ini **dibatasi throttle** (`5,3` atau `8,1`) karena melibatkan
kode rahasia.

Session login disimpan di `telegram_auth_sessions` (`qr_svg`,
`qr_expires_at`) sampai berhasil, lalu hasilnya disimpan di
`telegram_accounts.session_path` sebagai file session lokal.

### Status koneksi

`TelegramAccount::connection_status` punya tiga nilai:

| Status | Arti |
| --- | --- |
| `connected` | Session aktif, listener boleh jalan. |
| `disconnected` | Belum atau sudah diputus sambungannya. |
| `error` | Terjadi error; `last_error` menyimpan pesannya. |

`isMtprotoConnected()` mengecek session benar-benar terautentikasi, bukan
sekadar status di database.

### Menjalankan listener

Satu akun:

```bash
php artisan telegram:mtproto-listen 5          # ID akun atau ID user
```

Several akun paralel via sharding:

```bash
php artisan telegram:mtproto-listen --worker=0 --workers=4
```

Batas sharding: `MOD(id, workers) = worker`, sehingga setiap akun hanya
diproses satu worker. Worker yang tidak menemukan akun **menunggu** (tidur 15
detik) alih-alih langsung keluar — berguna saat beberapa worker di-restart.

Semua akun sekaligus (mode `--all`):

```bash
php artisan telegram:mtproto-listen --all
```

Mode `--all` memakai `pcntl_fork()` untuk membuat satu child process per akun
yang punya `session_path` dan berstatus `connected` atau `error`. Akun baru yang
connect akan **otomatis** diambil tanpa perlu restart, karena parent poll
database tiap 5 detik. Proses anak yang account-nya hilang akan diberi
`SIGTERM`.

> Mode `--all` butuh ekstensi `ext-pcntl` dan `ext-posix`. Tanpa keduanya,
> command gagal dengan pesan jelas. Child process membuka ulang koneksi
> database setelah fork (`app('db')->disconnect()`).

### Peringatan performa Windows

> **WARNING: MadelineProto runs around 10x slower on windows due to OS and PHP
> limitations. Make sure to deploy MadelineProto in production only on Linux or
> Mac OS machines for maximum performance.**

Command ini mencetak warning tersebut setiap kali dijalankan di Windows. Untuk
produksi, gunakan Linux atau macOS.

---

## Sinkronisasi Grup

`TelegramGroupSync::syncNext($account)` jelaskan dialog dan mengimpor pesan
dengan batas:

| Konstanta | Nilai |
| --- | --- |
| `PAGE_SIZE` | 10 |
| `MAX_DIALOGS` | 20 per sync |
| `HISTORY_LIMIT` | 20 pesan per dialog |

Error pada satu dialog tidak menghentikan dialog lain (isolasi error per
dialog).

Tautan grup ↔ grup aplikasi memakai `telegram_link_tokens`
(`token_hash`, `purpose`, `expires_at`, `used_at`) sehingga pengurutan tidak
perlu ID chat hard-coded.

Pengiriman pesan grup ke Telegram berjalan via job
`SendGroupMessageToTelegram` (unique), yang memicu event `GroupMessageSent`
pada channel `groups.{chatGroupId}`.

---

## Pengumuman

`TelegramAnnouncement` melakukan fan-out ke seluruh penerima. Tiap penerima
disimpan sebagai `TelegramAnnouncementRecipient` dengan `status` dan `error`
per pesan, sehingga kegagalan individual tidak menghentikan yang lain.

Job `SendTelegramAnnouncementRecipient` mengirim satu per satu. Ringkasan
`sent`, `failed`, `skipped_count` disimpan di pengumuman.

Route `announcements` dibatasi `10,1` (rate limit).

---

## Model (8 tabel)

| Model | Tabel | Fungsi |
| --- | --- | --- |
| `TelegramAccount` | `telegram_accounts` | Akun user + status session MTProto. |
| `TelegramAuthSession` | `telegram_auth_sessions` | Proses login (QR SVG, kode, 2FA). |
| `TelegramDialog` | `telegram_dialogs` | Percakapan (private/group/channel), `unread_count`, `last_read_at`. |
| `TelegramMessage` | `telegram_messages` | Pesan tersimpan, `is_outgoing`, `sent_at`, `chat_id`, `metadata`. |
| `TelegramUpdate` | `telegram_updates` | Ledger idempotency update Bot API. |
| `TelegramLinkToken` | `telegram_link_tokens` | Token deep-link berumur pendek. |
| `TelegramAnnouncement` | `telegram_announcements` | Header pengumuman + pengirim. |
| `TelegramAnnouncementRecipient` | `telegram_announcement_recipients` | Status per penerima. |

---

## Realtime

Kanal broadcast di `routes/channels.php`:

| Channel | Aturan |
| --- | --- |
| `users.{userId}` | Hanya user dengan ID tersebut. |
| `groups.{chatGroupId}` | Hanya anggota grup. |

Frontend subscribing lewat `useEcho` di `pages/telegram/messages.tsx`,
`pages/groups/show.tsx`, `pages/marga/chat.tsx`, dan
`pages/contacts/index.tsx`.

---

## Deployment

Listener MTProto dan worker dijalankan lewat Supervisor. Contoh tersedia di
`deploy/supervisor/tarombobatak.conf`:

```bash
sudo cp deploy/supervisor/tarombobatak.conf /etc/supervisor/conf.d/tarombobatak.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Sesuaikan `directory`, `user`, lokasi storage session Telegram, dan permission
log dengan server Anda. Detail ada di [deployment.md](deployment.md).

---

## Test

`tests/Feature/TelegramIntegrationTest.php` menguji alur integrasi Telegram
ter-mock sehingga tidak memerlukan token asli.
